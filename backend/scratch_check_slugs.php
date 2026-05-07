<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Subscription\Support\TenantModuleCatalog;

print_r(TenantModuleCatalog::slugs());
