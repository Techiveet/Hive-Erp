<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Tenancy\Models\Tenant;
use Modules\Subscription\Support\TenantSubscriptionService;

$tenantId = 'selam-bistro';
$tenant = Tenant::find($tenantId);
$service = app(TenantSubscriptionService::class);
$snapshot = $service->currentForTenant($tenant);

echo "Snapshot for $tenantId:\n";
echo json_encode($snapshot, JSON_PRETTY_PRINT) . "\n";
