<?php

use App\Http\Middleware\InitializeTenantContext;
use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Http\Controllers\Export\TenantExportController;
use Modules\Tenancy\Http\Controllers\TenantController;

$centralDomains = ['localhost', '127.0.0.1', 'hive-os.com'];

// =========================================================================
// 1. CENTRAL COMMAND (Tenancy)
// =========================================================================
foreach ($centralDomains as $domain) {
    Route::domain($domain)->prefix('v1')->group(function () {
        Route::get('/check-tenant/{tenant_id}', function ($tenant_id) {
            $exists = \Modules\Tenancy\Models\Tenant::where('id', $tenant_id)->exists();

            return response()->json(['valid' => $exists], $exists ? 200 : 404);
        });

        Route::middleware(['auth:sanctum', 'active_status', 'dynamic_timeout'])->group(function () {
            Route::prefix('tenants')->group(function () {
                Route::get('/export', [TenantExportController::class, 'handleExport']);
                Route::post('/{id}/toggle-status', [TenantController::class, 'toggleStatus']);
                Route::post('/{id}/toggle-admin-status', [TenantController::class, 'toggleAdminStatus']);
            });

            Route::apiResource('tenants', TenantController::class);
        });
    });
}

// =========================================================================
// 2. TENANT NODE (Tenancy)
// =========================================================================
Route::middleware([
    InitializeTenantContext::class,
])->prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum', 'active_status', 'dynamic_timeout'])->group(function () {
        // Tenant subscription routes now live in the Subscription module.
    });
});
