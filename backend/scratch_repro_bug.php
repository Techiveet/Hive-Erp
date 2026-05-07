<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Subscription\Support\TenantSubscriptionService;
use Modules\Tenancy\Models\Tenant;
use Modules\Subscription\Support\TenantModuleCatalog;

$tenantId = 'selam-bistro'; // Example tenant ID
$tenant = Tenant::find($tenantId);

if (!$tenant) {
    echo "Tenant not found\n";
    exit;
}

$service = app(TenantSubscriptionService::class);

echo "Initial enabled modules: " . implode(', ', $tenant->subscription?->module_subscriptions['enabled_modules'] ?? []) . "\n";

$payload = [
    'enabled_modules' => ['mailbox'], // Try to disable everything except mailbox
    'bypass_checks' => false,
];

echo "Updating modules to only mailbox...\n";
$service->assignPlan($tenant, $tenant->plan, $payload, 'admin@test.com');

$tenant->refresh();
echo "Enabled modules after update: " . implode(', ', $tenant->subscription?->module_subscriptions['enabled_modules'] ?? []) . "\n";

if (in_array('hospitality', $tenant->subscription?->module_subscriptions['enabled_modules'] ?? [])) {
    echo "BUG REPRODUCED: hospitality is still there!\n";
} else {
    echo "hospitality was correctly disabled.\n";
}
