<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\InventoryCategory;

class InventoryDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Beverages', 'description' => 'Alcoholic and non-alcoholic drinks.'],
            ['name' => 'Consumables', 'description' => 'Food, mixers, and quickly consumed products.'],
            ['name' => 'Supplies', 'description' => 'Operational supplies and service materials.'],
        ] as $category) {
            InventoryCategory::query()->firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
