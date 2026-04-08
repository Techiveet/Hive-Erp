<?php

namespace Modules\Core\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\Setting;
use Modules\Subscription\Support\TenantModuleCatalog;

class SubscriptionPlanSettingsController extends Controller
{
    /**
     * Return the current plan configuration:
     *  - Base plan pricing (from TenantModuleCatalog)
     *  - Admin overrides stored in DB (price, storage_mb, label, description, disabled)
     *  - Full module catalog so the frontend can render checkboxes
     */
    public function index(): JsonResponse
    {
        $overrides = $this->loadOverrides();
        $basePricing = TenantModuleCatalog::basePlanPricing();
        $catalog = TenantModuleCatalog::catalog();
        $planDefaults = TenantModuleCatalog::basePlanDefaults();
        $planOrder = ['larva', 'startup', 'business', 'enterprise', 'overlord'];

        $plans = [];
        foreach ($planOrder as $key) {
            $base   = $basePricing[$key] ?? [];
            $over   = $overrides[$key]  ?? [];

            $plans[$key] = [
                'key'               => $key,
                'label'             => $over['label']        ?? $base['name']                   ?? ucfirst($key),
                'description'       => $over['description']  ?? $base['description']            ?? '',
                'monthly_price_etb' => (float)  ($over['monthly_price_etb'] ?? $base['monthly_price_etb'] ?? 0),
                'storage_mb'        => (int)    ($over['storage_mb']         ?? $base['mail_storage_quota_mb'] ?? 512),
                'enabled_modules'   => $over['enabled_modules'] ?? $planDefaults[$key]          ?? [],
                'is_disabled'       => (bool)   ($over['is_disabled']        ?? false),
                'is_free'           => (float)  ($over['monthly_price_etb'] ?? $base['monthly_price_etb'] ?? 0) == 0,
            ];
        }

        return response()->json([
            'data' => [
                'plans'   => $plans,
                'catalog' => $catalog,
            ],
        ]);
    }

    /**
     * Save plan overrides.
     * Expects: { plans: { larva: { label, description, monthly_price_etb, storage_mb, enabled_modules, is_disabled }, ... } }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plans'                              => 'required|array',
            'plans.*.label'                      => 'required|string|max:80',
            'plans.*.description'                => 'nullable|string|max:500',
            'plans.*.monthly_price_etb'          => 'required|numeric|min:0',
            'plans.*.storage_mb'                 => 'required|integer|min:1',
            'plans.*.enabled_modules'            => 'required|array',
            'plans.*.enabled_modules.*'          => ['string', 'in:' . implode(',', TenantModuleCatalog::slugs())],
            'plans.*.is_disabled'                => 'required|boolean',
        ]);

        // Load existing overrides, merge in new values
        $overrides = $this->loadOverrides();
        foreach ($validated['plans'] as $planKey => $planData) {
            $overrides[$planKey] = [
                'label'             => $planData['label'],
                'description'       => $planData['description'] ?? '',
                'monthly_price_etb' => (float) $planData['monthly_price_etb'],
                'storage_mb'        => (int)   $planData['storage_mb'],
                'enabled_modules'   => array_values(array_unique($planData['enabled_modules'])),
                'is_disabled'       => (bool)  $planData['is_disabled'],
            ];
        }

        Setting::on($this->centralConnection())->updateOrCreate(
            ['key' => TenantModuleCatalog::PLAN_OVERRIDES_SETTING_KEY],
            ['value' => json_encode($overrides)]
        );

        clear_system_settings_cache();

        return response()->json([
            'message' => 'Subscription plan configuration saved successfully.',
            'data'    => $overrides,
        ]);
    }

    /** Reset a single plan back to catalog defaults. */
    public function reset(Request $request, string $planKey): JsonResponse
    {
        $allowed = ['larva', 'startup', 'business', 'enterprise', 'overlord'];
        if (!in_array($planKey, $allowed, true)) {
            return response()->json(['message' => 'Invalid plan key.'], 422);
        }

        $overrides = $this->loadOverrides();
        unset($overrides[$planKey]);

        Setting::on($this->centralConnection())->updateOrCreate(
            ['key' => TenantModuleCatalog::PLAN_OVERRIDES_SETTING_KEY],
            ['value' => json_encode($overrides)]
        );

        clear_system_settings_cache();

        return response()->json(['message' => "Plan '{$planKey}' reset to catalog defaults."]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function loadOverrides(): array
    {
        $raw = Setting::on($this->centralConnection())
            ->where('key', TenantModuleCatalog::PLAN_OVERRIDES_SETTING_KEY)
            ->value('value');
        if (!$raw) return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function centralConnection(): string
    {
        return (string) config('tenancy.database.central_connection', 'central');
    }
}
