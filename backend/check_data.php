<?php

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\InventoryItem;

$tenantId = 'aquauno';

try {
    $products = DB::connection('central')->table('products')->where('tenant_id', $tenantId)->get();
    echo "Total Central Products for $tenantId: " . $products->count() . "\n";
    foreach ($products as $p) {
        echo "- {$p->name} (SKU: {$p->sku})\n";
    }

    $items = DB::connection('central')->table('inventory_items')->where('tenant_id', $tenantId)->get();
    echo "\nTotal Inventory Items for $tenantId: " . $items->count() . "\n";
    foreach ($items as $i) {
        echo "- {$i->name} (SKU: {$i->sku})\n";
    }

    // Check mapping
    echo "\nProducts missing in InventoryItems:\n";
    $itemSkus = $items->pluck('sku')->toArray();
    foreach ($products as $p) {
        if (!in_array($p->sku, $itemSkus)) {
            echo "!! Product SKU {$p->sku} has no InventoryItem\n";
        }
    }

    echo "\nInventoryItems missing in Products:\n";
    $productSkus = $products->pluck('sku')->toArray();
    foreach ($items as $i) {
        if (!in_array($i->sku, $productSkus)) {
            echo "!! InventoryItem SKU {$i->sku} has no Product record\n";
        }
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
