<?php

require __DIR__ . '/vendor/autoload.php';

use Modules\Inventory\Support\InventoryEntityCatalog;

// We need to simulate the environment enough to load the class
// Since it's a simple class with constants, we might be able to just include it if we can't run full laravel

$file = __DIR__ . '/Modules/Inventory/app/Support/InventoryEntityCatalog.php';
if (file_exists($file)) {
    require_once $file;
    echo "Regex: " . \Modules\Inventory\Support\InventoryEntityCatalog::routeRegex() . "\n";
} else {
    echo "File not found at $file\n";
}
