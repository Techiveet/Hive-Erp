<?php

namespace Modules\Subscription\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Subscription\Models\TenantSubscription;
use Modules\Subscription\Support\TenantModuleCatalog;
use Modules\Tenancy\Models\Tenant;

class MigrateHospitalityModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('   Migrating lounge/nightclub to hospitality module...');

        $tenants = Tenant::query()
            ->whereIn('plan', ['business', 'enterprise', 'overlord'])
            ->get();

        $migratedCount = 0;

        foreach ($tenants as $tenant) {
            $subscription = TenantSubscription::query()
                ->where('tenant_id', $tenant->id)
                ->first();

            if (!$subscription || !is_array($subscription->module_subscriptions)) {
                continue;
            }

            $payload = $subscription->module_subscriptions;
            $enabledModules = $payload['enabled_modules'] ?? [];

            if (!in_array('hospitality', $enabledModules, true)) {
                $enabledModules[] = 'hospitality';
                $enabledModules = array_values(array_unique($enabledModules));

                $subscription->module_subscriptions = TenantModuleCatalog::normalizeForStorage(
                    [
                        'enabled_modules' => $enabledModules,
                        'custom_modules' => $payload['custom_modules'] ?? [],
                    ],
                    $subscription->plan,
                    'migration-seeder',
                    $tenant->business_type
                );

                $subscription->save();
                $migratedCount++;

                $this->command?->line("   -> Added hospitality to [{$tenant->id}]");
            }
        }

        $this->command?->info("   migration complete: {$migratedCount} tenants updated.");
    }
}