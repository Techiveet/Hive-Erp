<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class IdentityDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            CentralRolesSeeder::class,
            CentralUsersSeeder::class,
            // Note: TenantRolesSeeder and TenantUsersSeeder are omitted here
            // because they are triggered internally by the Tenancy module when a node spawns.
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
