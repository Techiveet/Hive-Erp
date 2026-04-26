<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\IdentityDatabaseSeeder;
use Modules\Subscription\Database\Seeders\SubscriptionDatabaseSeeder;
use Modules\Tenancy\Database\Seeders\TenancyDatabaseSeeder;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Inventory\Database\Seeders\WaterQualityTestSeeder;
use Modules\Inventory\Database\Seeders\InventoryTestingSeeder;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            IdentityDatabaseSeeder::class,
            \Modules\Tenancy\Database\Seeders\BusinessTypeSeeder::class,
            TenancyDatabaseSeeder::class,
            SubscriptionDatabaseSeeder::class,
            CoreDatabaseSeeder::class,
        ]);

        $this->seedCentralInventoryModule();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function seedCentralInventoryModule(): void
    {
        $catalog = app(\Modules\Tenancy\Support\TenantLandingTemplateCatalog::class);
        $businessTypes = $catalog->businessTypeKeys();

        if (in_array('water-bottling', $businessTypes)) {
            $tenant = \Modules\Tenancy\Models\Tenant::find('aquauno');

            if ($tenant) {
                $tenant->run(function () {
                    $this->call([
                        WaterQualityTestSeeder::class,
                        InventoryTestingSeeder::class,
                    ]);
                });
            }
        }
    }
}
