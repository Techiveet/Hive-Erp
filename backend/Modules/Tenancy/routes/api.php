<?php

use App\Http\Middleware\InitializeTenantContext;
use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Http\Controllers\Export\TenantExportController;
use Modules\Tenancy\Http\Controllers\TenantController;
use Modules\Tenancy\Http\Controllers\TenantLandingController;

$centralDomains = collect(array_merge(
    config('tenancy.central_domains', []),
    ['hive-os.com']
))
    ->map(function ($domain) {
        $domain = trim((string) $domain);

        if ($domain === '') {
            return null;
        }

        $host = parse_url(str_contains($domain, '://') ? $domain : "http://{$domain}", PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    })
    ->filter()
    ->unique()
    ->values()
    ->all();

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
                    Route::post('/{id}/domains', [TenantController::class, 'storeDomain']);
                    Route::put('/{id}/domains/{domainId}', [TenantController::class, 'updateDomain']);
                    Route::post('/{id}/domains/{domainId}/verify', [TenantController::class, 'verifyDomain']);
                    Route::post('/{id}/domains/{domainId}/make-primary', [TenantController::class, 'makePrimaryDomain']);
                    Route::delete('/{id}/domains/{domainId}', [TenantController::class, 'destroyDomain']);
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
    Route::get('/tenant/public/landing', [TenantLandingController::class, 'showPublic']);

    Route::middleware(['auth:sanctum', 'active_status', 'dynamic_timeout'])->group(function () {
        // Tenant subscription routes now live in the Subscription module.
    });
});
