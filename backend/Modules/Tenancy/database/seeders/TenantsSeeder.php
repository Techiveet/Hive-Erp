<?php

namespace Modules\Tenancy\Database\Seeders;

use Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

// 🚀 IMPORT seeders from the Identity Module
use Modules\Identity\Database\Seeders\TenantRolesSeeder;
use Modules\Identity\Database\Seeders\TenantUsersSeeder;
use Modules\Core\Database\Seeders\TenantBrandSettingsSeeder;
use Modules\Core\Database\Seeders\TenantGeneralSettingsSeeder;

class TenantsSeeder extends Seeder
{
    public function run(): void
    {
        // 🚀 FIX: Updated plans to match your Controller validation
        $tenants = [
            ['id' => 'apple', 'name' => 'Apple Inc', 'plan' => 'overlord'],
            ['id' => 'tesla', 'name' => 'Tesla Motors', 'plan' => 'business'],
        ];

        foreach ($tenants as $data) {
            $this->command->info("🛠️ Spawning Node: " . $data['name']);

            // 1. Find or Create the Tenant Record (Central DB)
            $tenant = Tenant::withoutEvents(function () use ($data) {
                return Tenant::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'name' => $data['name'],
                        'plan' => $data['plan'],
                        'is_active' => true,
                        'admin_email' => "admin@{$data['id']}.com",
                        'admin_active' => true,
                    ]
                );
            });

            // 2. Ensure Domain Exists
            $tenant->domains()->firstOrCreate([
                'domain' => $data['id'] . '.localhost',
            ]);

            // 3. Smart Database Check
            $dbManager = $tenant->database()->manager();
            $dbName = $tenant->database()->getName();

            if (! $dbManager->databaseExists($dbName)) {
                $this->command->info("   -> Allocating new database: {$dbName}");
                dispatch_sync(new CreateDatabase($tenant));
                dispatch_sync(new MigrateDatabase($tenant));
            } else {
                $this->command->warn("   -> Database {$dbName} already exists. Re-linking node.");
                dispatch_sync(new MigrateDatabase($tenant));
            }

            // 4. Seed Node internally (Crossing module boundaries via imports)
            $tenant->run(function () {
                app()[PermissionRegistrar::class]->forgetCachedPermissions();

                // 🚀 Calling seeders that live in Modules/Identity
                $this->call(TenantRolesSeeder::class);
                $this->call(TenantUsersSeeder::class);
                $this->call(TenantGeneralSettingsSeeder::class);
                $this->call(TenantBrandSettingsSeeder::class);
                app()[PermissionRegistrar::class]->forgetCachedPermissions();
            });

            $this->command->info("✅ Node " . $data['id'] . " is online.");
        }
    }
}
