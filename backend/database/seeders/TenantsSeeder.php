<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

class TenantsSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            ['id' => 'apple', 'name' => 'Apple Inc', 'plan' => 'queen'],
            ['id' => 'tesla', 'name' => 'Tesla Motors', 'plan' => 'worker'],
            ['id' => 'spacex', 'name' => 'SpaceX', 'plan' => 'worker'],
        ];

        foreach ($tenants as $data) {
            $this->command->info("🛠️ Spawning Node: " . $data['name']);

            // 1. Cleanup existing DB
            $dbName = 'tenant' . $data['id'];
            try {
                DB::statement("DROP DATABASE IF EXISTS \"$dbName\"");
            } catch (\Exception $e) {}

            // 2. Fresh Record
            if ($old = Tenant::find($data['id'])) $old->delete();

            $tenant = Tenant::create([
                'id' => $data['id'],
                'plan' => $data['plan'],
            ]);

            $tenant->domains()->create([
                'domain' => $data['id'] . '.localhost',
            ]);

            // 3. Automate Infrastructure
            dispatch_sync(new CreateDatabase($tenant));
            dispatch_sync(new MigrateDatabase($tenant));

            // 4. Seed Internal Company Data
            $tenant->run(function () {
                $this->call(TenantRolesSeeder::class);
                $this->call(TenantUsersSeeder::class);
            });

            $this->command->info("✅ Node " . $data['id'] . " is online at " . $data['id'] . ".localhost");
        }
    }
}
