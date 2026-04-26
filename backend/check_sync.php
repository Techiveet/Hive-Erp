<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$inventoryCount = DB::connection('central')->table('inventory_items')->where('tenant_id', 'aquauno')->count();
$productCount = DB::connection('central')->table('products')->where('tenant_id', 'aquauno')->count();

echo "Inventory Items (aquauno): $inventoryCount\n";
echo "Products (aquauno): $productCount\n";
