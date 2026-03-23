<?php

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

use Modules\Tenancy\Http\Controllers\TenantController;
use Modules\Tenancy\Http\Controllers\Export\TenantExportController;

$centralDomains = ['localhost', '127.0.0.1', 'hive-os.com'];

// =========================================================================
// 1. CENTRAL COMMAND (Tenancy)
// =========================================================================
foreach ($centralDomains as $domain) {
    Route::domain($domain)->prefix('v1')->group(function () {

        // Public Check
        Route::get('/check-tenant/{tenant_id}', function ($tenant_id) {
            $exists = \Modules\Tenancy\Models\Tenant::where('id', $tenant_id)->exists();
            return response()->json(['valid' => $exists], $exists ? 200 : 404);
        });

        // Protected
        Route::middleware('auth:sanctum')->group(function () {

            // 🚀 REMOVED the hardcoded /central/dashboard closure from here!
            // It is now safely handled by the Core module's DashboardController.

            Route::prefix('tenants')->group(function() {
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
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class
])->prefix('v1')->group(function () {

    Route::middleware('auth:sanctum')->group(function () {

        // 🚀 REMOVED the hardcoded /dashboard closure from here!
        // It is now safely handled by the Core module's DashboardController.

        // Add any other tenant-specific tenancy routes here if needed in the future

    });
});
