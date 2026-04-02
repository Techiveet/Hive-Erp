<?php

namespace Modules\Tenancy\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantModuleCatalog;

class TenantModuleSubscriptionsSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()->get()->each(function (Tenant $tenant): void {
            if ($this->hasSeededSubscriptions($tenant)) {
                $this->command?->line("   -> Skipping subscription seed for [{$tenant->id}] because a configuration already exists.");
                return;
            }

            $tenant->module_subscriptions = TenantModuleCatalog::normalizeForStorage(
                $this->subscriptionProfileFor($tenant),
                $tenant->plan,
                'database-seeder'
            );
            $tenant->save();

            $moduleCount = count($tenant->module_subscriptions['enabled_modules'] ?? []);
            $customCount = count($tenant->module_subscriptions['custom_modules'] ?? []);

            $this->command?->info("   -> Seeded {$moduleCount} catalog modules and {$customCount} custom modules for [{$tenant->id}].");
        });
    }

    protected function hasSeededSubscriptions(Tenant $tenant): bool
    {
        $subscriptions = $tenant->module_subscriptions;

        if (!is_array($subscriptions)) {
            return false;
        }

        return !empty($subscriptions['enabled_modules'] ?? []) || !empty($subscriptions['custom_modules'] ?? []);
    }

    protected function subscriptionProfileFor(Tenant $tenant): array
    {
        $defaults = TenantModuleCatalog::defaultsForPlan($tenant->plan);

        return match ($tenant->id) {
            'apple' => [
                'enabled_modules' => TenantModuleCatalog::defaultsForPlan('overlord'),
                'custom_modules' => [
                    [
                        'name' => 'Brand Review Suite',
                        'category' => 'Creative Suite',
                        'description' => 'Internal approval flow for campaign imagery, product videos, and polished marketing releases.',
                    ],
                ],
            ],
            'tesla' => [
                'enabled_modules' => array_values(array_unique([
                    ...$defaults,
                    'fleet_management',
                    'api_access',
                ])),
                'custom_modules' => [
                    [
                        'name' => 'Battery Diagnostics',
                        'category' => 'Operations',
                        'description' => 'Track battery health, charging readiness, and service flags across field vehicles.',
                    ],
                ],
            ],
            default => [
                'enabled_modules' => $defaults,
                'custom_modules' => [],
            ],
        };
    }
}
