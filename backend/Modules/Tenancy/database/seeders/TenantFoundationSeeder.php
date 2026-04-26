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

        $businessType = tenant('business_type')
            ?? (tenant()->landing_page_template['meta']['business_type'] ?? null);

        if ($businessType === 'water-bottling') {
            $this->call(\Modules\Inventory\Database\Seeders\WaterQualityTestSeeder::class);
            $this->call(\Modules\Inventory\Database\Seeders\InventoryTestingSeeder::class);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
