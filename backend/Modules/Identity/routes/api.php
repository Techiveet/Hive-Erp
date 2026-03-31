<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\InitializeTenantContext;

use Modules\Identity\Http\Controllers\AuthController;
use Modules\Identity\Http\Controllers\TwoFactorController;
use Modules\Identity\Http\Controllers\ProfileController;
use Modules\Identity\Http\Controllers\UserController;
use Modules\Identity\Http\Controllers\RoleController;
use Modules\Identity\Http\Controllers\Export\UserExportController;
use Modules\Identity\Http\Controllers\Export\RoleExportController;
use Modules\Identity\Http\Controllers\Export\PermissionExportController;

$centralDomains = ['localhost', '127.0.0.1', 'hive-os.com'];

// =========================================================================
// 1. CENTRAL COMMAND (Identity & Public System)
// =========================================================================
foreach ($centralDomains as $domain) {
    Route::domain($domain)->prefix('v1')->group(function () {

        // 🚀 NEW: PUBLIC SYSTEM ROUTES (For Maintenance Mode Ticker)
        Route::prefix('system')->group(function () {
            Route::get('/status-ticker', function() {
                return response()->json([
                    'message' => get_system_setting('maintenance_message', 'HIVE.OS: System neural links are currently undergoing optimization.')
                ]);
            });
        });

        // Public Auth
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/verify-2fa', [AuthController::class, 'verify2FA']);
        Route::get('/password-policy', [AuthController::class, 'passwordPolicy']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        // Protected Auth
        Route::middleware(['auth:sanctum', 'active_status', 'dynamic_timeout'])->group(function () {
            // Heartbeat Endpoint
            Route::post('/ping', [AuthController::class, 'ping']);

            Route::get('/user', [AuthController::class, 'user']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/profile/update', [ProfileController::class, 'updateProfile']);
            Route::get('/profile/avatar', [ProfileController::class, 'getAvatar']);

            Route::prefix('2fa')->group(function () {
                Route::post('/enable', [TwoFactorController::class, 'enable']);
                Route::post('/confirm', [TwoFactorController::class, 'confirm']);
                Route::post('/disable', [TwoFactorController::class, 'destroy']);
            });

            Route::prefix('roles')->group(function() {
                Route::get('/export', [RoleExportController::class, 'handleExport']);
            });
            Route::get('/permissions/export', [PermissionExportController::class, 'handleExport']);
            Route::get('/permissions', [RoleController::class, 'permissions']);
            Route::apiResource('roles', RoleController::class);

            Route::prefix('users')->group(function() {
                Route::get('/export', [UserExportController::class, 'handleExport']);
                Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus']);
            });
            Route::apiResource('users', UserController::class);
        });
    });
}

// =========================================================================
// 2. TENANT NODE (Identity & Public System)
// =========================================================================
Route::middleware([
    InitializeTenantContext::class,
])->prefix('v1')->group(function () {

    // 🚀 NEW: PUBLIC SYSTEM ROUTES FOR TENANTS (For Maintenance Mode Ticker)
    Route::prefix('system')->group(function () {
        Route::get('/status-ticker', function() {
            return response()->json([
                'message' => get_system_setting('maintenance_message', 'HIVE.OS: System neural links are currently undergoing optimization.')
            ]);
        });
    });

    // Public Auth
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/tenant/login', [AuthController::class, 'login']);
    Route::post('/verify-2fa', [AuthController::class, 'verify2FA']);
    Route::post('/tenant/verify-2fa', [AuthController::class, 'verify2FA']);
    Route::get('/password-policy', [AuthController::class, 'passwordPolicy']);
    Route::get('/tenant/password-policy', [AuthController::class, 'passwordPolicy']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/tenant/reset-password', [AuthController::class, 'resetPassword']);

    // Protected Auth
    Route::middleware(['auth:sanctum', 'active_status', 'dynamic_timeout'])->group(function () {
        // Heartbeat Endpoint
        Route::post('/ping', [AuthController::class, 'ping']);
        Route::post('/tenant/ping', [AuthController::class, 'ping']);

        Route::get('/user', [AuthController::class, 'user']);
        Route::get('/tenant/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/tenant/logout', [AuthController::class, 'logout']);
        Route::post('/profile/update', [ProfileController::class, 'updateProfile']);
        Route::post('/tenant/profile/update', [ProfileController::class, 'updateProfile']);
        Route::get('/profile/avatar', [ProfileController::class, 'getAvatar']);
        Route::get('/tenant/profile/avatar', [ProfileController::class, 'getAvatar']);

        Route::prefix('2fa')->group(function () {
            Route::post('/enable', [TwoFactorController::class, 'enable']);
            Route::post('/confirm', [TwoFactorController::class, 'confirm']);
            Route::post('/disable', [TwoFactorController::class, 'destroy']);
        });

        // THE FIX: If your frontend uses /tenant/ prefix, we define it here:
        Route::prefix('tenant')->group(function () {

            // 🚀 Ensure Tenant explicitly has the status ticker route under the /tenant prefix as well just in case!
            Route::get('/system/status-ticker', function() {
                return response()->json([
                    'message' => get_system_setting('maintenance_message', 'HIVE.OS: System neural links are currently undergoing optimization.')
                ]);
            });

            Route::prefix('roles')->group(function() {
                Route::get('/export', [RoleExportController::class, 'handleExport']);
            });
            Route::get('/permissions', [RoleController::class, 'permissions']);
            Route::apiResource('roles', RoleController::class);

            Route::prefix('users')->group(function() {
                Route::get('/export', [UserExportController::class, 'handleExport']);
                Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus']);
            });
            Route::apiResource('users', UserController::class);
        });

        // FALLBACK: Keep the standard paths just in case other parts of your app use them
        Route::prefix('roles')->group(function() {
            Route::get('/export', [RoleExportController::class, 'handleExport']);
        });
        Route::get('/permissions', [RoleController::class, 'permissions']);
        Route::apiResource('roles', RoleController::class);

        Route::prefix('users')->group(function() {
            Route::get('/export', [UserExportController::class, 'handleExport']);
            Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        });
        Route::apiResource('users', UserController::class);
    });
});
