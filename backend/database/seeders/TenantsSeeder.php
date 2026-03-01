<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

class TenantsSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            ['id' => 'apple', 'name' => 'Apple Inc', 'plan' => 'queen'],
            ['id' => 'tesla', 'name' => 'Tesla Motors', 'plan' => 'worker'],
        ];

        foreach ($tenants as $data) {
            $this->command->info("🛠️ Spawning Node: " . $data['name']);

            // Cleanup logic
            $dbName = 'tenant' . $data['id'];
            try {
                DB::statement("DROP DATABASE IF EXISTS \"$dbName\"");
            } catch (\Exception $e) {}

            if ($old = Tenant::find($data['id'])) $old->delete();

            // Create Tenant
            $tenant = Tenant::create([
                'id' => $data['id'],
                'plan' => $data['plan'],
            ]);

            $tenant->domains()->create([
                'domain' => $data['id'] . '.localhost',
            ]);

            // Infrastructure Deployment
            dispatch_sync(new CreateDatabase($tenant));
            dispatch_sync(new MigrateDatabase($tenant));

            // Seed Node internally
            $tenant->run(function () {
                // 🚀 This is the magic line that prevents the RoleDoesNotExist error
                app()[PermissionRegistrar::class]->forgetCachedPermissions();

                $this->call(TenantRolesSeeder::class);
                $this->call(TenantUsersSeeder::class);
            });

            $this->command->info("✅ Node " . $data['id'] . " is online.");
        }
    }
}
