<?php

use Modules\Tenancy\Models\Tenant;
use Modules\Subscription\Support\TenantSubscriptionService;
use Modules\Subscription\Support\TenantModuleCatalog;
use Modules\Subscription\Models\TenantSubscription;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tenantId = 'techive';
$tenant = Tenant::where('id', $tenantId)->first();

$service = app(TenantSubscriptionService::class);
$snapshot = $service->currentSnapshotForTenant($tenant);

echo "Snapshot enabled_modules: " . implode(', ', $snapshot['enabled_modules'] ?? []) . "\n";

$isActive = false;
foreach ($snapshot['catalog_modules'] as $module) {
    if ($module['slug'] === 'hospitality') {
        $isActive = ($module['status'] === 'active');
        echo "Hospitality status in catalog: " . $module['status'] . "\n";
    }
}

if ($isActive) {
    echo "Hospitality is ACTIVE in snapshot.\n";
} else {
    echo "Hospitality is INACTIVE in snapshot.\n";
}
