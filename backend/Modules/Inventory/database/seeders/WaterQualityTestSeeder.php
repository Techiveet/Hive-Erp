<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\InventoryEntityRecord;
use Modules\Inventory\Support\WaterQaProtocolCatalog;

class WaterQualityTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (WaterQaProtocolCatalog::baseline() as $test) {
            InventoryEntityRecord::updateOrCreate(
                ['entity_type' => 'qa_tests', 'code' => $test['code']],
                [
                    'name' => $test['name'],
                    'payload' => $test['payload'],
                    'is_active' => true,
                ]
            );
        }
    }
}
