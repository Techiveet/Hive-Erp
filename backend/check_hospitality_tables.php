<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tables = [
    'hospitality_zones',
    'hospitality_locations',
    'hospitality_zone_assignments',
    'hospitality_guest_lists',
    'hospitality_promoter_commissions'
];

foreach ($tables as $table) {
    echo "Table: $table\n";
    if (Schema::hasTable($table)) {
        echo "  Exists: Yes\n";
        $columns = Schema::getColumnListing($table);
        echo "  Columns: " . implode(', ', $columns) . "\n";
    } else {
        echo "  Exists: No\n";
    }
    echo "\n";
}
