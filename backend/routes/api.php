<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TwoFactorController; // 🚀 ADDED IMPORT
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\Export\UserExportController;
use App\Http\Controllers\Api\Export\RoleExportController;
use App\Http\Controllers\Api\Export\PermissionExportController;

// Fallback route
Route::get('/unauthorized', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

Route::prefix('v1')->group(function () {

    // ==========================================
    // 1. CENTRAL ROUTES (Accessed via localhost:8085)
    // URIs will be: /api/v1/...
    // ==========================================
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-2fa', [AuthController::class, 'verify2FA']); // 🚀 2FA: Public Verify
    Route::get('/password-policy', [AuthController::class, 'passwordPolicy']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::get('/check-tenant/{tenant_id}', function ($tenant_id) {
        $exists = \App\Models\Tenant::where('id', $tenant_id)->exists();
        return response()->json(['valid' => $exists], $exists ? 200 : 404);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // 🚀 2FA: Protected Management Routes
        Route::post('/2fa/enable', [TwoFactorController::class, 'enable']);
        Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm']);
        Route::post('/2fa/disable', [TwoFactorController::class, 'destroy']);

        Route::get('/central/dashboard', function () {
            return response()->json([
                'company' => 'HIVE.OS Central Command',
                'plan' => 'System Admin',
                'stats' => ['revenue' => 'System Active', 'active_users' => 12, 'pending_invoices' => 0],
                'recent_activity' => [['id' => 1, 'action' => 'New Tenant Node Provisioned', 'time' => '10 mins ago']]
            ]);
        });

        // CENTRAL ROLES
        Route::get('/roles/export', [RoleExportController::class, 'handleExport']);
        Route::get('/permissions/export', [PermissionExportController::class, 'handleExport']);
        Route::get('/permissions', [RoleController::class, 'permissions']);
        Route::apiResource('roles', RoleController::class);

        // CENTRAL USERS
        Route::get('/users/export', [UserExportController::class, 'handleExport']);
        Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::apiResource('users', UserController::class);
    });


    // ==========================================
    // 2. TENANT ROUTES (Accessed via apple.localhost:8085)
    // URIs will be: /api/v1/tenant/...
    // ==========================================
    Route::middleware([
        InitializeTenancyByDomain::class,
        PreventAccessFromCentralDomains::class
    ])->prefix('tenant')->group(function () {

        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/verify-2fa', [AuthController::class, 'verify2FA']); // 🚀 2FA: Public Verify
        Route::get('/password-policy', [AuthController::class, 'passwordPolicy']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/user', [AuthController::class, 'user']);
            Route::post('/logout', [AuthController::class, 'logout']);

            // 🚀 2FA: Protected Management Routes
            Route::post('/2fa/enable', [TwoFactorController::class, 'enable']);
            Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm']);
            Route::post('/2fa/disable', [TwoFactorController::class, 'destroy']);

            Route::get('/dashboard', function () {
                return response()->json([
                    'company' => ucfirst(tenant('id')) . ' Corp',
                    'plan' => tenant('plan') ?? 'Enterprise',
                    'stats' => ['revenue' => '24.5M ETB', 'active_users' => 420, 'pending_invoices' => 12],
                    'recent_activity' => [['id' => 1, 'action' => 'Freight Load #TRK-001 Delivered', 'time' => '2 hours ago']]
                ]);
            });

            // TENANT ROLES
            Route::get('/roles/export', [RoleExportController::class, 'handleExport']);
            Route::get('/permissions/export', [PermissionExportController::class, 'handleExport']);
            Route::get('/permissions', [RoleController::class, 'permissions']);
            Route::apiResource('roles', RoleController::class);

            // TENANT USERS
            Route::get('/users/export', [UserExportController::class, 'handleExport']);
            Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
            Route::apiResource('users', UserController::class);
        });

    });
});
