<?php

namespace Modules\Subscription\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Subscription\Support\TenantModuleCatalog;
use Modules\Subscription\Support\TenantSubscriptionService;
use Modules\Tenancy\Models\Tenant;

class TenantModuleSubscriptionsSeeder extends Seeder
{
    public function run(TenantSubscriptionService $subscriptions): void
    {
        Tenant::query()->get()->each(function (Tenant $tenant) use ($subscriptions): void {
            $existing = \Modules\Subscription\Models\TenantSubscription::query()
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($existing) {
                $this->command?->line("   -> Skipping subscription seed for [{$tenant->id}] because a configuration already exists.");
                return;
            }

            $subscription = $subscriptions->ensureForTenant(
                $tenant,
                $this->subscriptionProfileFor($tenant),
                'database-seeder',
                true
            );

            $moduleCount = count($subscription->module_subscriptions['enabled_modules'] ?? []);
            $customCount = count($subscription->module_subscriptions['custom_modules'] ?? []);

            $this->command?->info("   -> Seeded {$moduleCount} catalog modules and {$customCount} custom modules for [{$tenant->id}].");
        });
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

