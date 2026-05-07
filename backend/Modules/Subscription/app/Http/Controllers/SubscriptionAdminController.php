<?php

namespace Modules\Subscription\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Setting;
use Modules\Subscription\Models\SubscriptionFeature;
use Modules\Subscription\Models\SubscriptionModule;
use Modules\Subscription\Models\SubscriptionPlan;
use Modules\Subscription\Support\SubscriptionRegistrySyncService;
use Modules\Subscription\Support\TenantModuleCatalog;
use Modules\Subscription\Support\TenantSubscriptionService;
use Modules\Tenancy\Models\Tenant;

class SubscriptionAdminController extends Controller
{
    public function __construct(
        protected SubscriptionRegistrySyncService $registry,
        protected TenantSubscriptionService $subscriptions,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->registry->sync();
        $priceOverrides = TenantModuleCatalog::priceOverrides();

        $tenants = Tenant::query()
            ->with('domains')
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($tenantQuery) use ($search) {
                    $tenantQuery
                        ->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('admin_email', 'like', "%{$search}%");
                });
            })
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(function (Tenant $tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name ?? ucfirst($tenant->id),
                    'plan' => $tenant->plan ?? 'business',
                    'business_type' => $tenant->business_type,
                    'admin_email' => $tenant->admin_email,
                    'is_active' => (bool) ($tenant->is_active ?? true),
                    'subscription' => $this->subscriptions->currentSnapshotForTenant($tenant),
                ];
            });

        return response()->json([
            'data' => [
                'plans' => SubscriptionPlan::query()->orderBy('sort_order')->get(),
                'modules' => $this->pricedModules(
                    SubscriptionModule::query()
                        ->with(['submodules.features', 'features'])
                        ->orderBy('category')
                        ->orderBy('name')
                        ->get(),
                    $priceOverrides
                ),
                'features' => $this->pricedFeatures(
                    SubscriptionFeature::query()
                        ->with(['module', 'submodule'])
                        ->orderBy('route_uri')
                        ->get(),
                    $priceOverrides
                ),
                'tenants' => $tenants,
                'catalog' => TenantModuleCatalog::catalogWithPricing($priceOverrides),
                'plan_defaults' => TenantModuleCatalog::planDefaults(),
                'plan_pricing' => TenantModuleCatalog::planPricing(),
                'feature_pricing' => $priceOverrides,
            ],
        ]);
    }

    public function updatePlans(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plans' => ['required', 'array'],
            'plans.*.name' => ['required', 'string', 'max:80'],
            'plans.*.description' => ['nullable', 'string', 'max:500'],
            'plans.*.monthly_price_etb' => ['required', 'numeric', 'min:0'],
            'plans.*.mail_storage_quota_mb' => ['required', 'integer', 'min:1'],
            'plans.*.status' => ['required', Rule::in(['active', 'inactive'])],
            'plans.*.trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'plans.*.enabled_modules' => ['required', 'array'],
            'plans.*.enabled_modules.*' => ['string', Rule::in(TenantModuleCatalog::slugs())],
        ]);

        $overrides = [];
        $priceOverrides = TenantModuleCatalog::priceOverrides();
        foreach ($validated['plans'] as $planKey => $plan) {
            $enabledModules = array_values(array_unique($plan['enabled_modules']));
            $overrides[$planKey] = [
                'label' => $plan['name'],
                'description' => $plan['description'] ?? '',
                'monthly_price_etb' => TenantModuleCatalog::planAmountForModules($enabledModules, $priceOverrides),
                'storage_mb' => (int) $plan['mail_storage_quota_mb'],
                'enabled_modules' => $enabledModules,
                'is_disabled' => $plan['status'] !== 'active',
                'trial_days' => (int) ($plan['trial_days'] ?? 0),
            ];
        }

        Setting::on($this->centralConnection())->updateOrCreate(
            ['key' => TenantModuleCatalog::PLAN_OVERRIDES_SETTING_KEY],
            ['value' => json_encode($overrides)]
        );

        if (function_exists('clear_system_settings_cache')) {
            clear_system_settings_cache();
        }

        $this->registry->sync();
        $syncedTenants = $this->subscriptions->syncPlanDefaultsToTenants(
            array_keys($overrides),
            auth()->user()?->email
        );

        return response()->json([
            'message' => 'Subscription plans updated.',
            'data' => [
                'plans' => SubscriptionPlan::query()->orderBy('sort_order')->get(),
                'plan_defaults' => TenantModuleCatalog::planDefaults(),
                'plan_pricing' => TenantModuleCatalog::planPricing(),
                'synced_tenants' => $syncedTenants,
            ],
        ]);
    }

    public function updatePricing(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'modules' => ['sometimes', 'array'],
            'submodules' => ['sometimes', 'array'],
            'features' => ['sometimes', 'array'],
        ]);

        $this->assertPricingPayloadIsNumeric($payload);
        $overrides = TenantModuleCatalog::normalizePriceOverrides($payload);

        Setting::on($this->centralConnection())->updateOrCreate(
            ['key' => TenantModuleCatalog::PRICE_OVERRIDES_SETTING_KEY],
            ['value' => json_encode($overrides)]
        );

        if (function_exists('clear_system_settings_cache')) {
            clear_system_settings_cache();
        }

        $this->registry->sync();

        return response()->json([
            'message' => 'Subscription module pricing updated.',
            'data' => [
                'catalog' => TenantModuleCatalog::catalogWithPricing($overrides),
                'feature_pricing' => $overrides,
            ],
        ]);
    }

    public function assignTenant(Request $request, string $tenantId): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::in(array_keys(TenantModuleCatalog::planPricing()))],
            'status' => ['required', Rule::in(['active', 'trial', 'inactive', 'expired', 'cancelled', 'suspended', 'pending_activation'])],
            ...TenantModuleCatalog::validationRules('module_subscriptions'),
            'reset_billing_window' => ['sometimes', 'boolean'],
        ]);

        $tenant = Tenant::query()->findOrFail($tenantId);
        \Log::info('Assigning tenant subscription', [
            'tenant_id' => $tenant->id,
            'payload' => $request->all(),
            'validated' => $validated,
        ]);

        $subscription = $this->subscriptions->assignPlan(
            $tenant,
            $validated['plan'],
            $validated['module_subscriptions'] ?? null,
            auth()->user()?->email,
            (bool) ($validated['reset_billing_window'] ?? false)
        );

        if ($validated['status'] !== $subscription->status) {
            $subscription = $this->subscriptions->setStatus($tenant->refresh(), $validated['status'], auth()->user()?->email);
        }

        activity('Subscription Admin')
            ->causedBy(auth()->user())
            ->event('assigned')
            ->withProperties([
                'tenant_id' => $tenant->id,
                'plan' => $validated['plan'],
                'status' => $validated['status'],
                'enabled_modules' => $subscription->module_subscriptions['enabled_modules'] ?? [],
            ])
            ->log("Assigned subscription plan [{$validated['plan']}] to tenant [{$tenant->id}].");

        return response()->json([
            'message' => 'Tenant subscription updated.',
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name ?? ucfirst($tenant->id),
                    'plan' => $tenant->plan,
                ],
                'subscription' => $this->subscriptions->currentForTenant($tenant->refresh()),
            ],
        ]);
    }

    private function centralConnection(): string
    {
        return (string) config('tenancy.database.central_connection', 'central');
    }

    private function pricedModules($modules, array $priceOverrides)
    {
        $catalogMap = TenantModuleCatalog::catalogMap($priceOverrides);

        return $modules->map(function (SubscriptionModule $module) use ($catalogMap, $priceOverrides) {
            $payload = $module->toArray();
            $payload['monthly_price_etb'] = (float) (
                $catalogMap[$module->slug]['monthly_price_etb']
                ?? ($module->metadata['monthly_price_etb'] ?? 0)
            );

            $payload['submodules'] = collect($module->submodules ?? [])
                ->map(function ($submodule) use ($module, $priceOverrides) {
                    $submodulePayload = $submodule->toArray();
                    $submoduleKey = "{$module->slug}:{$submodule->slug}";
                    $submodulePayload['monthly_price_etb'] = (float) (
                        $priceOverrides['submodules'][$submoduleKey]['monthly_price_etb']
                        ?? ($submodule->metadata['monthly_price_etb'] ?? 0)
                    );
                    $submodulePayload['features'] = collect($submodule->features ?? [])
                        ->map(fn (SubscriptionFeature $feature) => $this->pricedFeaturePayload($feature, $priceOverrides))
                        ->values()
                        ->all();

                    return $submodulePayload;
                })
                ->values()
                ->all();

            $payload['features'] = collect($module->features ?? [])
                ->map(fn (SubscriptionFeature $feature) => $this->pricedFeaturePayload($feature, $priceOverrides))
                ->values()
                ->all();

            return $payload;
        })->values();
    }

    private function pricedFeatures($features, array $priceOverrides)
    {
        return $features
            ->map(fn (SubscriptionFeature $feature) => $this->pricedFeaturePayload($feature, $priceOverrides))
            ->values();
    }

    private function pricedFeaturePayload(SubscriptionFeature $feature, array $priceOverrides): array
    {
        $payload = $feature->toArray();
        $payload['monthly_price_etb'] = (float) (
            $priceOverrides['features'][$feature->slug]['monthly_price_etb']
            ?? ($feature->metadata['monthly_price_etb'] ?? 0)
        );

        return $payload;
    }

    private function assertPricingPayloadIsNumeric(array $payload): void
    {
        foreach (['modules', 'submodules', 'features'] as $scope) {
            foreach (($payload[$scope] ?? []) as $key => $value) {
                if (!is_array($value) || !array_key_exists('monthly_price_etb', $value) || !is_numeric($value['monthly_price_etb'])) {
                    throw ValidationException::withMessages([
                        "{$scope}.{$key}.monthly_price_etb" => 'The monthly price must be a valid number.',
                    ]);
                }
            }
        }
    }
}
