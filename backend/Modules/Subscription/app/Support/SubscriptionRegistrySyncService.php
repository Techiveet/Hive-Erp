<?php

namespace Modules\Subscription\Support;

use Illuminate\Support\Facades\DB;
use Modules\Subscription\Models\SubscriptionFeature;
use Modules\Subscription\Models\SubscriptionModule;
use Modules\Subscription\Models\SubscriptionPlan;
use Modules\Subscription\Models\SubscriptionSubmodule;

class SubscriptionRegistrySyncService
{
    public function __construct(
        protected SubscriptionFeatureMap $featureMap,
    ) {
    }

    public function sync(): array
    {
        $connection = DB::connection('central');

        return $connection->transaction(function () {
            $modules = $this->syncModules();
            $submodules = $this->syncSubmodules($modules);
            $features = $this->syncFeatures($modules, $submodules);
            $plans = $this->syncPlans($features);

            return compact('modules', 'submodules', 'features', 'plans');
        });
    }

    private function syncModules(): array
    {
        return collect($this->featureMap->modules())
            ->mapWithKeys(function (array $module) {
                $record = SubscriptionModule::query()->updateOrCreate(
                    ['slug' => $module['slug']],
                    [
                        'name' => $module['name'],
                        'description' => $module['description'] ?? null,
                        'category' => $module['category'] ?? null,
                        'tone' => $module['tone'] ?? null,
                        'backend_module' => $module['backend_module'] ?? null,
                        'frontend_module' => $module['frontend_module'] ?? null,
                        'route_prefixes' => $module['route_prefixes'] ?? [],
                        'metadata' => [
                            'recommended_plans' => $module['recommended_plans'] ?? [],
                            'monthly_price_etb' => (float) ($module['monthly_price_etb'] ?? 0),
                            'billing_type' => TenantModuleCatalog::moduleBillingType($module['slug']),
                            'is_addon' => TenantModuleCatalog::moduleBillingType($module['slug']) === TenantModuleCatalog::BILLING_TYPE_ADDON,
                            'business_types' => $module['business_types'] ?? [],
                        ],
                    ]
                );

                return [$record->slug => $record];
            })
            ->all();
    }

    private function syncSubmodules(array $modules): array
    {
        return collect($this->featureMap->submodules())
            ->mapWithKeys(function (array $submodule) use ($modules) {
                $module = $modules[$submodule['module_slug']] ?? null;
                if (!$module) {
                    return [];
                }

                $record = SubscriptionSubmodule::query()->updateOrCreate(
                    [
                        'subscription_module_id' => $module->id,
                        'slug' => $submodule['slug'],
                    ],
                    [
                        'name' => $submodule['name'],
                        'description' => $submodule['description'] ?? null,
                        'route_prefixes' => $submodule['route_prefixes'] ?? [],
                        'permissions' => $submodule['permissions'] ?? [],
                        'metadata' => $submodule['metadata'] ?? [],
                    ]
                );

                return [$submodule['module_slug'] . ':' . $record->slug => $record];
            })
            ->all();
    }

    private function syncFeatures(array $modules, array $submodules): array
    {
        return collect($this->featureMap->features())
            ->mapWithKeys(function (array $feature) use ($modules, $submodules) {
                $module = $modules[$feature['module_slug']] ?? null;
                if (!$module) {
                    return [];
                }

                $submodule = $submodules[$feature['module_slug'] . ':' . $feature['submodule_slug']] ?? null;
                $record = SubscriptionFeature::query()->updateOrCreate(
                    ['slug' => $feature['slug']],
                    [
                        'subscription_module_id' => $module->id,
                        'subscription_submodule_id' => $submodule?->id,
                        'name' => $feature['name'],
                        'feature_type' => $feature['feature_type'] ?? 'route',
                        'route_name' => $feature['route_name'] ?? null,
                        'route_uri' => $feature['route_uri'] ?? null,
                        'http_methods' => $feature['http_methods'] ?? [],
                        'permission' => $feature['permission'] ?? null,
                        'module_gate' => $feature['module_gate'] ?? $feature['module_slug'],
                        'metadata' => $feature['metadata'] ?? [],
                    ]
                );

                return [$record->slug => $record];
            })
            ->all();
    }

    private function syncPlans(array $features): array
    {
        $pricing = TenantModuleCatalog::planPricing();
        $defaults = TenantModuleCatalog::planDefaults();
        $order = 0;

        return collect($pricing)
            ->mapWithKeys(function (array $plan, string $slug) use ($defaults, $features, &$order) {
                $record = SubscriptionPlan::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $plan['name'],
                        'description' => $plan['description'] ?? null,
                        'status' => ($plan['is_disabled'] ?? false) ? 'inactive' : 'active',
                        'billing_cycle' => 'monthly',
                        'monthly_price_etb' => (float) ($plan['monthly_price_etb'] ?? 0),
                        'mail_storage_quota_mb' => (int) ($plan['mail_storage_quota_mb'] ?? 512),
                        'trial_days' => $slug === 'larva' ? 14 : 0,
                        'sort_order' => $order++,
                        'metadata' => ['source' => 'tenant_module_catalog'],
                    ]
                );

                $allowedModules = $defaults[$slug] ?? [];
                $sync = collect($features)
                    ->filter(fn (SubscriptionFeature $feature) => in_array($feature->module?->slug, $allowedModules, true))
                    ->mapWithKeys(fn (SubscriptionFeature $feature) => [$feature->id => ['included' => true]])
                    ->all();

                $record->features()->sync($sync);

                return [$record->slug => $record];
            })
            ->all();
    }
}
