<?php

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

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
// 1. CENTRAL COMMAND (Identity)
// =========================================================================
foreach ($centralDomains as $domain) {
    Route::domain($domain)->prefix('v1')->group(function () {

        // Public
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/verify-2fa', [AuthController::class, 'verify2FA']);
        Route::get('/password-policy', [AuthController::class, 'passwordPolicy']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        // Protected
        Route::middleware('auth:sanctum')->group(function () {
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
// 2. TENANT NODE (Identity)
// =========================================================================
Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class
])->prefix('v1')->group(function () {

    // Public
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-2fa', [AuthController::class, 'verify2FA']);
    Route::get('/password-policy', [AuthController::class, 'passwordPolicy']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Protected
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/profile/update', [ProfileController::class, 'updateProfile']);
        Route::get('/profile/avatar', [ProfileController::class, 'getAvatar']);

        Route::prefix('2fa')->group(function () {
            Route::post('/enable', [TwoFactorController::class, 'enable']);
            Route::post('/confirm', [TwoFactorController::class, 'confirm']);
            Route::post('/disable', [TwoFactorController::class, 'destroy']);
        });

        // 🚀 THE FIX: If your frontend uses /tenant/ prefix, we define it here:
        Route::prefix('tenant')->group(function () {
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

        // 🚀 FALLBACK: Keep the standard paths just in case other parts of your app use them
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
