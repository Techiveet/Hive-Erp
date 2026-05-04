<?php

namespace Modules\Subscription\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Setting;
use Modules\Subscription\Support\SubscriptionFeatureMap;
use Modules\Subscription\Support\SubscriptionRegistrySyncService;
use Modules\Subscription\Support\TenantModuleCatalog;

class SubscriptionPricingSeeder extends Seeder
{
    public function run(SubscriptionFeatureMap $featureMap, SubscriptionRegistrySyncService $registry): void
    {
        $registry->sync();

        $priceOverrides = $this->defaultPriceOverrides($featureMap);

        Setting::on($this->centralConnection())->updateOrCreate(
            ['key' => TenantModuleCatalog::PRICE_OVERRIDES_SETTING_KEY],
            ['value' => json_encode($priceOverrides)]
        );

        $this->syncPlanPriceOverrides($priceOverrides);

        if (function_exists('clear_system_settings_cache')) {
            clear_system_settings_cache();
        }

        $registry->sync();

        $this->command?->info(sprintf(
            '   -> Seeded subscription pricing for %d modules, %d submodules, and %d features.',
            count($priceOverrides['modules'] ?? []),
            count($priceOverrides['submodules'] ?? []),
            count($priceOverrides['features'] ?? [])
        ));
    }

    private function defaultPriceOverrides(SubscriptionFeatureMap $featureMap): array
    {
        $submodules = collect($featureMap->submodules())->groupBy('module_slug');
        $features = collect($featureMap->features())->groupBy(
            fn (array $feature) => $feature['module_slug'] . ':' . $feature['submodule_slug']
        );

        $overrides = [
            'modules' => [],
            'submodules' => [],
            'features' => [],
        ];

        foreach (TenantModuleCatalog::catalog() as $module) {
            $moduleSlug = $module['slug'];
            $modulePrice = max(0.0, (float) ($module['monthly_price_etb'] ?? 0));
            $moduleSubmodules = $submodules->get($moduleSlug, collect())->values();

            $overrides['modules'][$moduleSlug] = [
                'monthly_price_etb' => $modulePrice,
                'billing_type' => TenantModuleCatalog::moduleBillingType($moduleSlug),
                'is_addon' => TenantModuleCatalog::moduleBillingType($moduleSlug) === TenantModuleCatalog::BILLING_TYPE_ADDON,
            ];

            if ($moduleSubmodules->isEmpty()) {
                continue;
            }

            $submodulePrices = $this->splitAmount($modulePrice, $moduleSubmodules->count());

            foreach ($moduleSubmodules as $index => $submodule) {
                $submoduleKey = "{$moduleSlug}:{$submodule['slug']}";
                $submodulePrice = $submodulePrices[$index] ?? 0.0;
                $overrides['submodules'][$submoduleKey] = [
                    'monthly_price_etb' => $submodulePrice,
                ];

                $submoduleFeatures = $features->get($submoduleKey, collect())->values();
                if ($submoduleFeatures->isEmpty()) {
                    continue;
                }

                $featurePrices = $this->splitAmount($submodulePrice, $submoduleFeatures->count());
                foreach ($submoduleFeatures as $featureIndex => $feature) {
                    $overrides['features'][$feature['slug']] = [
                        'monthly_price_etb' => $featurePrices[$featureIndex] ?? 0.0,
                    ];
                }
            }
        }

        return TenantModuleCatalog::normalizePriceOverrides($overrides);
    }

    private function syncPlanPriceOverrides(array $priceOverrides): void
    {
        $currentOverrides = TenantModuleCatalog::planOverrides();
        $basePricing = TenantModuleCatalog::basePlanPricing();
        $baseDefaults = TenantModuleCatalog::basePlanDefaults();
        $overrides = [];

        foreach ($basePricing as $planKey => $plan) {
            $current = is_array($currentOverrides[$planKey] ?? null) ? $currentOverrides[$planKey] : [];
            $enabledModules = TenantModuleCatalog::normalizeRequestedModules(
                $current['enabled_modules'] ?? $baseDefaults[$planKey] ?? []
            );

            $overrides[$planKey] = [
                'label' => (string) ($current['label'] ?? $plan['name']),
                'description' => (string) ($current['description'] ?? $plan['description']),
                'monthly_price_etb' => TenantModuleCatalog::planAmountForModules($enabledModules, $priceOverrides),
                'storage_mb' => (int) ($current['storage_mb'] ?? $plan['mail_storage_quota_mb']),
                'enabled_modules' => $enabledModules,
                'is_disabled' => (bool) ($current['is_disabled'] ?? false),
                'trial_days' => (int) ($current['trial_days'] ?? ($planKey === 'larva' ? 14 : 0)),
            ];
        }

        Setting::on($this->centralConnection())->updateOrCreate(
            ['key' => TenantModuleCatalog::PLAN_OVERRIDES_SETTING_KEY],
            ['value' => json_encode($overrides)]
        );
    }

    private function splitAmount(float $amount, int $parts): array
    {
        if ($parts <= 0) {
            return [];
        }

        $base = floor(($amount / $parts) * 100) / 100;
        $values = array_fill(0, $parts, $base);
        $remainder = (int) round(($amount - ($base * $parts)) * 100);

        for ($index = 0; $index < $remainder; $index++) {
            $values[$index % $parts] += 0.01;
        }

        return array_map(fn (float $value) => round($value, 2), $values);
    }

    private function centralConnection(): string
    {
        return (string) config('tenancy.database.central_connection', 'central');
    }
}
