<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

class TenantsSeeder extends Seeder
{
    public function run(): void
    {
        // 🚀 FIX: Updated plans to match your Controller validation (business, enterprise, overlord, startup)
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
                        'is_active' => true, // 🚀 FIX: Node is Online
                        'admin_email' => "admin@{$data['id']}.com", // 🚀 FIX: Matches the user seeder
                        'admin_active' => true, // 🚀 FIX: Admin is unlocked
                    ]
                );
            });

            // 2. Ensure Domain Exists
            $tenant->domains()->firstOrCreate([
                'domain' => $data['id'] . '.localhost',
            ]);

            // 3. Smart Database Check (Physical MySQL/Postgres Server)
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

            // 4. Seed Node internally
            $tenant->run(function () {
                app()[PermissionRegistrar::class]->forgetCachedPermissions();

                $this->call(TenantRolesSeeder::class);
                $this->call(TenantUsersSeeder::class);
            });

            $this->command->info("✅ Node " . $data['id'] . " is online.");
        }
    }
}
