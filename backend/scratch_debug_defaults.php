<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Subscription\Support\TenantModuleCatalog;

$plan = 'business';
$defaults = TenantModuleCatalog::defaultsForPlan($plan);

echo "Defaults for $plan: " . implode(', ', $defaults) . "\n";
