<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Modules\Hospitality\Models\Table;
use Modules\Hospitality\Models\Reservation;
use Modules\Hospitality\Models\ServiceOrder;

$app->boot();

try {
    echo "Checking Table count: " . Table::count() . "\n";
    echo "Checking Reservation count: " . Reservation::count() . "\n";
    echo "Checking ServiceOrder count: " . ServiceOrder::count() . "\n";
    echo "Success!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
