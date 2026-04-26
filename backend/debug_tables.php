<?php

use Modules\Tenancy\Models\Tenant;
use Modules\Inventory\Models\InventoryDocument;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tenant = Tenant::where('id', 'aquauno')->first();
tenancy()->initialize($tenant);

echo "Tenant initialized: " . $tenant->id . "\n";

try {
    $count = InventoryDocument::count();
    echo "Inventory documents count: " . $count . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
