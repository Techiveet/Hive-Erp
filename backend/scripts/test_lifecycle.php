<?php

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\InventoryEntityRecord;
use Modules\Warehouse\Models\StockMovement;
use Modules\Warehouse\Models\WarehouseStock;
use Modules\Warehouse\Models\WarehouseLocation;
use Modules\Workflow\Services\WorkflowService;
use Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Ensure we have a user to act as approver
$user = User::first();
if (!$user) {
    echo "ERROR: No user found in database. Please seed the database first.\n";
    exit(1);
}

$workflowService = app(WorkflowService::class);
$tenantId = 'central';

echo "Using User: {$user->name} (ID: {$user->id})\n";
echo "Using Tenant: {$tenantId}\n";

// 1. Create a Product
echo "\n--- Step 1: Creating Product ---\n";
$product = Product::create([
    'tenant_id' => $tenantId,
    'name' => 'Test Workflow Product ' . uniqid(),
    'sku' => 'TEST-SKU-' . rand(1000, 9999),
    'status' => 'draft',
    'quantity' => 0,
]);
echo "Product created: {$product->name} (ID: {$product->id}, Status: {$product->status})\n";

// 2. Apply and Approve Workflow
echo "\n--- Step 2: Applying Workflow to Product ---\n";
$product->requestApproval($user->id, 'QA', null, 1);
$approval = $product->approvals()->first();
echo "Approval requested (ID: {$approval->id}, Sequence: {$approval->sequence})\n";
var_dump($approval->toArray());

$workflowService->actionApproval($approval, 'approved', 'Looks good!');
$product->refresh();
echo "Product Status after approval: {$product->status}\n";

if ($product->status !== 'published') {
    echo "ERROR: Product status should be 'published'!\n";
    exit(1);
}

// 3. Create a Batch
echo "\n--- Step 3: Creating Inventory Batch ---\n";
// We need a warehouse and a location
$warehouse = \Modules\Warehouse\Models\Warehouse::where('tenant_id', $tenantId)->first();
if (!$warehouse) {
    $warehouse = \Modules\Warehouse\Models\Warehouse::create([
        'tenant_id' => $tenantId,
        'name' => 'Main Warehouse',
        'code' => 'WH-MAIN',
        'is_active' => true,
    ]);
}
echo "Using Warehouse: {$warehouse->name} (ID: {$warehouse->id})\n";

$location = WarehouseLocation::where('warehouse_id', $warehouse->id)->first();
if (!$location) {
    $location = WarehouseLocation::create([
        'tenant_id' => $tenantId,
        'warehouse_id' => $warehouse->id,
        'name' => 'Test Warehouse Location',
        'code' => 'T-LOC-1',
        'type' => 'storage',
    ]);
}
echo "Using Location: {$location->name} (ID: {$location->id})\n";

$batch = InventoryEntityRecord::create([
    'tenant_id' => $tenantId,
    'entity_type' => 'batch',
    'name' => 'Batch ' . uniqid(),
    'code' => 'B-' . rand(1000, 9999),
    'payload' => [
        'product_id' => $product->id,
        'quantity' => 100,
        'target_location_id' => $location->id,
    ],
]);
echo "Batch created: {$batch->code} (ID: {$batch->id})\n";

// 4. Approve Batch Workflow
echo "\n--- Step 4: Approving Batch (Shelving) ---\n";
$batch->requestApproval($user->id, 'Warehouse', null, 1);
$batchApproval = $batch->approvals()->first();
echo "Batch Approval requested (ID: {$batchApproval->id})\n";

$workflowService->actionApproval($batchApproval, 'approved', 'Shelve it!');
echo "Batch workflow approved.\n";

// 5. Verify Stock
echo "\n--- Step 5: Verifying Stock ---\n";
$stock = WarehouseStock::where('product_id', $product->id)
    ->where('warehouse_location_id', $location->id)
    ->where('batch_number', $batch->code)
    ->first();

if ($stock) {
    echo "Stock record found: ID {$stock->id}, On Hand: {$stock->on_hand}\n";
    if (floatval($stock->on_hand) === 100.0) {
        echo "SUCCESS: Stock successfully updated to 100!\n";
    } else {
        echo "ERROR: Stock quantity incorrect! Expected 100, got {$stock->on_hand}\n";
        exit(1);
    }
} else {
    echo "ERROR: Stock record not found!\n";
    exit(1);
}

echo "\n--- SUCCESS: All tests passed! ---\n";
