<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryCategory;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\InventoryEntityRecord;
use Modules\Inventory\Models\InventoryBatchQaResult;
use Modules\Warehouse\Models\Warehouse;
use Modules\Warehouse\Models\WarehouseLocation;
use Modules\Warehouse\Models\WarehouseStock;
use Modules\Inventory\Models\Tag;

class InventoryTestingSeeder extends Seeder
{
    protected string $tenantId;

    public function run(): void
    {
        $this->tenantId = tenant('id') ?? 'aquauno';

        DB::transaction(function () {
            $warehouses = $this->seedWarehouses();
            $categories = $this->seedCategories();
            $suppliers = $this->seedSuppliers();
            $tags = $this->seedTags();
            $products = $this->seedProducts($categories, $suppliers, $tags);
            $batches = $this->seedProductBatches($products);
            $this->seedQaResults($batches);
            $this->seedInitialStock($warehouses, $products, $batches);
        });
    }

    protected function seedWarehouses(): array
    {
        $data = [
            ['name' => 'Main Warehouse', 'code' => 'WH-MAIN', 'type' => 'main'],
            ['name' => 'Raw Materials Store', 'code' => 'WH-RAW', 'type' => 'main'],
            ['name' => 'Quarantine Area', 'code' => 'WH-QUAR', 'type' => 'quarantine'],
        ];

        $warehouses = [];
        foreach ($data as $item) {
            $wh = Warehouse::withoutGlobalScopes()->updateOrCreate(
                ['code' => $item['code'], 'tenant_id' => $this->tenantId],
                [
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'is_active' => true,
                    'metadata' => []
                ]
            );
            $warehouses[$item['code']] = $wh;

            for ($i = 1; $i <= 3; $i++) {
                WarehouseLocation::withoutGlobalScopes()->updateOrCreate(
                    ['code' => "{$item['code']}-LOC-{$i}", 'tenant_id' => $this->tenantId],
                    [
                        'warehouse_id' => $wh->id,
                        'name' => "Aisle {$i}, Bin " . ($i * 10),
                        'is_active' => true,
                        'type' => 'storage'
                    ]
                );
            }
        }

        return $warehouses;
    }

    protected function seedCategories(): array
    {
        $data = [
            ['name' => 'Water Products', 'description' => 'Finished bottled water products.'],
            ['name' => 'Packaging', 'description' => 'Bottles, caps, and labels.'],
            ['name' => 'Chemicals', 'description' => 'Water treatment chemicals.'],
        ];

        $categories = [];
        foreach ($data as $item) {
            $cat = ProductCategory::withoutGlobalScopes()->updateOrCreate(
                ['name' => $item['name'], 'tenant_id' => $this->tenantId],
                ['is_active' => true]
            );
            $categories[$item['name']] = $cat;

            InventoryCategory::updateOrCreate(
                ['name' => $item['name']],
                ['description' => $item['description'], 'is_active' => true]
            );
        }

        return $categories;
    }

    protected function seedSuppliers(): array
    {
        $data = [
            ['name' => 'AquaSource Systems', 'email' => 'sales@aquasource.test', 'code' => 'SUP-AQUA'],
            ['name' => 'PET Packaging Solutions', 'email' => 'support@petpkg.test', 'code' => 'SUP-PET'],
        ];

        $suppliers = [];
        foreach ($data as $item) {
            $sup = Supplier::withoutGlobalScopes()->updateOrCreate(
                ['name' => $item['name'], 'tenant_id' => $this->tenantId],
                ['email' => $item['email'], 'code' => $item['code'], 'is_active' => true]
            );
            $suppliers[$item['name']] = $sup;
        }

        return $suppliers;
    }

    protected function seedTags(): array
    {
        $data = [
            ['name' => 'Premium', 'slug' => 'premium'],
            ['name' => 'Essential', 'slug' => 'essential'],
            ['name' => 'Bulk', 'slug' => 'bulk'],
        ];

        $tags = [];
        foreach ($data as $item) {
            $tag = Tag::withoutGlobalScopes()->updateOrCreate(
                ['slug' => $item['slug'], 'tenant_id' => $this->tenantId],
                ['name' => $item['name'], 'is_active' => true]
            );
            $tags[$item['name']] = $tag;
        }

        return $tags;
    }

    protected function seedProducts(array $categories, array $suppliers, array $tags): array
    {
        $data = [
            [
                'name' => 'Purified Water 500ml',
                'sku' => 'WTR-500ML',
                'cat' => 'Water Products',
                'sup' => 'AquaSource Systems',
                'uom' => 'Bottle',
                'price' => 1.50
            ],
            [
                'name' => 'Blue Cap (28mm)',
                'sku' => 'CAP-BLU-28',
                'cat' => 'Packaging',
                'sup' => 'PET Packaging Solutions',
                'uom' => 'Piece',
                'price' => 0.05
            ],
            [
                'name' => 'PET Preform 500ml',
                'sku' => 'PET-PRE-500',
                'cat' => 'Packaging',
                'sup' => 'PET Packaging Solutions',
                'uom' => 'Piece',
                'price' => 0.20
            ],
        ];

        $products = [];
        foreach ($data as $item) {
            $catId = $categories[$item['cat']]->id;
            $supId = $suppliers[$item['sup']]->id;

            $prod = Product::withoutGlobalScopes()->updateOrCreate(
                ['sku' => $item['sku'], 'tenant_id' => $this->tenantId],
                [
                    'name' => $item['name'],
                    'product_category_id' => $catId,
                    'supplier_id' => $supId,
                    'uom' => $item['uom'],
                    'unit_price' => $item['price'],
                    'status' => 'published',
                    'track_inventory' => true
                ]
            );

            // Attach tags
            $prodTags = [];
            if ($item['sku'] === 'WTR-500ML') {
                $prodTags[] = $tags['Premium']->id;
                $prodTags[] = $tags['Essential']->id;
            } else {
                $prodTags[] = $tags['Bulk']->id;
            }
            $prod->tags()->sync($prodTags);

            $products[$item['sku']] = $prod;

            InventoryItem::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'unit' => $item['uom'],
                    'selling_price' => $item['price']
                ]
            );
        }

        return $products;
    }

    protected function seedProductBatches(array $products): array
    {
        $batches = [];
        $wtr500 = $products['WTR-500ML'];

        $batchConfigs = [
            ['num' => 'B2024-001', 'status' => 'qa_passed', 'date' => now()->subDays(5)],
            ['num' => 'B2024-002', 'status' => 'qa_passed', 'date' => now()->subDays(2)],
            ['num' => 'B2024-003', 'status' => 'pending', 'date' => now()->subDays(1)],
            ['num' => 'B2024-004', 'status' => 'qa_failed', 'date' => now()],
        ];

        foreach ($batchConfigs as $cfg) {
            $batch = InventoryEntityRecord::withoutGlobalScopes()->updateOrCreate(
                [
                    'entity_type' => 'product_batches',
                    'code' => $cfg['num']
                ],
                [
                    'name' => "Batch {$cfg['num']}",
                    'payload' => [
                        'batch_number' => $cfg['num'],
                        'product_id' => $wtr500->id,
                        'product_name' => $wtr500->name,
                        'production_date' => $cfg['date']->format('Y-m-d'),
                        'expiry_date' => $cfg['date']->copy()->addYear()->format('Y-m-d'),
                        'qa_status' => $cfg['status']
                    ]
                ]
            );
            $batches[] = $batch;
        }

        return $batches;
    }

    protected function seedQaResults(array $batches): void
    {
        $protocols = InventoryEntityRecord::withoutGlobalScopes()
            ->where('entity_type', 'qa_tests')
            ->get();

        $service = app(\Modules\Inventory\Services\QualityComplianceService::class);

        foreach ($batches as $batch) {
            $status = $batch->payload['qa_status'];
            if ($status === 'pending') continue;

            $isPass = $status === 'qa_passed';
            $results = [];

            foreach ($protocols as $protocol) {
                $results[$protocol->code] = $this->getRandomTestValue($protocol, $isPass);
            }

            $analysis = $service->validateResults($results);

            InventoryBatchQaResult::create([
                'batch_record_id' => $batch->id,
                'result' => $analysis['passed'] ? 'passed' : 'failed',
                'notes' => $analysis['passed'] ? 'Meets standards' : 'Out of range',
                'tested_at' => now(),
                'tested_by_id' => 1, // Assuming admin
                'payload' => [
                    'raw_input' => $results,
                    'tests' => $analysis['results'],
                ]
            ]);
        }
    }

    protected function seedInitialStock(array $warehouses, array $products, array $batches): void
    {
        $mainWh = $warehouses['WH-MAIN'];
        $rawWh = $warehouses['WH-RAW'];
        
        $mainLoc = WarehouseLocation::withoutGlobalScopes()
            ->where('warehouse_id', $mainWh->id)
            ->where('tenant_id', $this->tenantId)
            ->first();
            
        $rawLoc = WarehouseLocation::withoutGlobalScopes()
            ->where('warehouse_id', $rawWh->id)
            ->where('tenant_id', $this->tenantId)
            ->first();

        if (!$mainLoc || !$rawLoc) {
            throw new \Exception("Locations not found during stock seeding.");
        }

        foreach ($batches as $batch) {
            if ($batch->payload['qa_status'] !== 'qa_passed') continue;

            WarehouseStock::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $this->tenantId,
                    'warehouse_location_id' => $mainLoc->id,
                    'product_id' => $batch->payload['product_id'],
                    'batch_number' => $batch->payload['batch_number']
                ],
                [
                    'on_hand' => 5000.00,
                    'expiry_date' => $batch->payload['expiry_date']
                ]
            );
        }

        $rawItems = [
            ['sku' => 'PET-PRE-500', 'qty' => 50000.00, 'batch' => 'RAW-PRE-001'],
            ['sku' => 'CAP-BLU-28', 'qty' => 60000.00, 'batch' => 'RAW-CAP-001'],
        ];

        foreach ($rawItems as $item) {
            WarehouseStock::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $this->tenantId,
                    'warehouse_location_id' => $rawLoc->id,
                    'product_id' => $products[$item['sku']]->id,
                    'batch_number' => $item['batch']
                ],
                ['on_hand' => $item['qty']]
            );
        }
    }

    protected function getRandomTestValue($protocol, bool $shouldPass): string
    {
        $payload = is_string($protocol->payload) ? json_decode($protocol->payload, true) : $protocol->payload;
        
        if (isset($payload['type']) && ($payload['type'] === 'numeric' || $payload['type'] === 'numeric_range')) {
            $min = $payload['min'] ?? 0;
            $max = $payload['max'] ?? 100;
            if ($shouldPass) {
                return (string) (($min + $max) / 2);
            } else {
                return (string) ($max + 5);
            }
        }
        return $shouldPass ? ($payload['target'] ?? 'Pass') : 'Unsatisfactory';
    }
}
