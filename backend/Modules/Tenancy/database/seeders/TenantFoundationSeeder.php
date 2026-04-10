<?php

namespace Modules\Tenancy\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\TenantBrandSettingsSeeder;
use Modules\Core\Database\Seeders\TenantGeneralSettingsSeeder;
use Modules\Identity\Database\Seeders\TenantRolesSeeder;
use Spatie\Permission\PermissionRegistrar;

class TenantFoundationSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->call([
            TenantRolesSeeder::class,
            TenantGeneralSettingsSeeder::class,
            TenantBrandSettingsSeeder::class,
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
