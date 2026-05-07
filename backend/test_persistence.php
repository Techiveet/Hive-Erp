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

if (!$tenant) {
    echo "Tenant not found\n";
    exit(1);
}

$service = app(TenantSubscriptionService::class);
$subscription = TenantSubscription::where('tenant_id', $tenantId)->first();

if (!$subscription) {
    echo "Subscription not found\n";
    exit(1);
}

echo "Initial modules: " . implode(', ', $subscription->module_subscriptions['enabled_modules'] ?? []) . "\n";

// Disable hospitality
$currentModules = $subscription->module_subscriptions['enabled_modules'] ?? [];
$newModules = array_values(array_filter($currentModules, fn($m) => !in_array($m, ['hospitality', 'inventory_control'])));

echo "Updating to: " . implode(', ', $newModules) . "\n";

$service->assignPlan($tenant, $tenant->plan, [
    'enabled_modules' => $newModules,
    'catalog_version' => 3
]);

$subscription = TenantSubscription::where('tenant_id', $tenantId)->first();
echo "Post-update modules: " . implode(', ', $subscription->module_subscriptions['enabled_modules'] ?? []) . "\n";

if (in_array('hospitality', $subscription->module_subscriptions['enabled_modules'] ?? [], true)) {
    echo "BUG DETECTED: Hospitality is still there!\n";
} else {
    echo "SUCCESS: Hospitality disabled.\n";
}
