<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Subscription\Support\TenantModuleCatalog;

$payload = [
    'enabled_modules' => ['mailbox'],
    'bypass_checks' => false,
];
$plan = 'business';
$businessType = null;

$resolved = TenantModuleCatalog::resolve($payload, $plan, [], $businessType);

echo "Resolved enabled modules: " . implode(', ', $resolved['enabled_modules']) . "\n";
