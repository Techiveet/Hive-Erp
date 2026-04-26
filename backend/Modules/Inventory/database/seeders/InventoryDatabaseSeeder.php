<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\InventoryCategory;

class InventoryDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(InventoryTestingSeeder::class);
    }
}
