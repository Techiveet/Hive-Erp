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

            Route::get('/user', [AuthController::class, 'user'])->middleware('permission:view_profile|edit_profile,sanctum');
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/profile/update', [ProfileController::class, 'updateProfile'])->middleware('permission:edit_profile,sanctum');
            Route::post('/profile/tour-complete', [ProfileController::class, 'completeWelcomeTour'])->middleware('permission:edit_profile,sanctum');
            Route::get('/profile/avatar', [ProfileController::class, 'getAvatar'])->middleware('permission:view_profile|edit_profile,sanctum');

            Route::prefix('2fa')->group(function () {
                Route::post('/enable', [TwoFactorController::class, 'enable'])->middleware('permission:edit_profile,sanctum');
                Route::post('/confirm', [TwoFactorController::class, 'confirm'])->middleware('permission:edit_profile,sanctum');
                Route::post('/disable', [TwoFactorController::class, 'destroy'])->middleware('permission:edit_profile,sanctum');
            });

            Route::prefix('roles')->group(function() {
                Route::get('/export', [RoleExportController::class, 'handleExport'])->middleware('permission:view_roles|manage_roles,sanctum');
            });
            Route::get('/permissions/export', [PermissionExportController::class, 'handleExport'])->middleware('permission:view_permissions,sanctum');
            Route::get('/permissions', [RoleController::class, 'permissions'])->middleware('permission:view_permissions,sanctum');

            // Lightweight company directory for dropdowns (accessible to all authenticated users)
            Route::get('/directory/users', [UserController::class, 'directory']);

            Route::apiResource('roles', RoleController::class)
                ->only(['index', 'show'])
                ->middleware('permission:view_roles|manage_roles,sanctum');
            Route::apiResource('roles', RoleController::class)
                ->only(['store'])
                ->middleware('permission:create_roles|manage_roles,sanctum');
            Route::apiResource('roles', RoleController::class)
                ->only(['update'])
                ->middleware('permission:edit_roles|manage_roles,sanctum');
            Route::apiResource('roles', RoleController::class)
                ->only(['destroy'])
                ->middleware('permission:delete_roles|manage_roles,sanctum');

            Route::prefix('users')->group(function() {
                Route::get('/export', [UserExportController::class, 'handleExport'])->middleware('permission:view_users|manage_users,sanctum');
                Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:edit_users|manage_users,sanctum');
            });
            Route::apiResource('users', UserController::class)
                ->only(['index', 'show'])
                ->middleware('permission:view_users|manage_users,sanctum');
            Route::apiResource('users', UserController::class)
                ->only(['store'])
                ->middleware('permission:create_users|manage_users,sanctum');
            Route::apiResource('users', UserController::class)
                ->only(['update'])
                ->middleware('permission:edit_users|manage_users,sanctum');
            Route::apiResource('users', UserController::class)
                ->only(['destroy'])
                ->middleware('permission:delete_users|manage_users,sanctum');
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

        Route::get('/user', [AuthController::class, 'user'])->middleware('permission:view_profile|edit_profile,sanctum');
        Route::get('/tenant/user', [AuthController::class, 'user'])->middleware('permission:view_profile|edit_profile,sanctum');
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/tenant/logout', [AuthController::class, 'logout']);
        Route::post('/profile/update', [ProfileController::class, 'updateProfile'])->middleware('permission:edit_profile,sanctum');
        Route::post('/tenant/profile/update', [ProfileController::class, 'updateProfile'])->middleware('permission:edit_profile,sanctum');
        Route::post('/profile/tour-complete', [ProfileController::class, 'completeWelcomeTour'])->middleware('permission:edit_profile,sanctum');
        Route::post('/tenant/profile/tour-complete', [ProfileController::class, 'completeWelcomeTour'])->middleware('permission:edit_profile,sanctum');
        Route::get('/profile/avatar', [ProfileController::class, 'getAvatar'])->middleware('permission:view_profile|edit_profile,sanctum');
        Route::get('/tenant/profile/avatar', [ProfileController::class, 'getAvatar'])->middleware('permission:view_profile|edit_profile,sanctum');

        Route::prefix('2fa')->group(function () {
            Route::post('/enable', [TwoFactorController::class, 'enable'])->middleware('permission:edit_profile,sanctum');
            Route::post('/confirm', [TwoFactorController::class, 'confirm'])->middleware('permission:edit_profile,sanctum');
            Route::post('/disable', [TwoFactorController::class, 'destroy'])->middleware('permission:edit_profile,sanctum');
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
                Route::get('/export', [RoleExportController::class, 'handleExport'])->middleware('permission:view_roles|manage_roles,sanctum');
            });
            Route::get('/permissions/export', [PermissionExportController::class, 'handleExport'])->middleware('permission:view_permissions,sanctum');
            Route::get('/permissions', [RoleController::class, 'permissions'])->middleware('permission:view_permissions,sanctum');

            // Lightweight company directory for dropdowns (accessible to all authenticated users)
            Route::get('/directory/users', [UserController::class, 'directory']);

            Route::apiResource('roles', RoleController::class)
                ->only(['index', 'show'])
                ->middleware('permission:view_roles|manage_roles,sanctum');
            Route::apiResource('roles', RoleController::class)
                ->only(['store'])
                ->middleware('permission:create_roles|manage_roles,sanctum');
            Route::apiResource('roles', RoleController::class)
                ->only(['update'])
                ->middleware('permission:edit_roles|manage_roles,sanctum');
            Route::apiResource('roles', RoleController::class)
                ->only(['destroy'])
                ->middleware('permission:delete_roles|manage_roles,sanctum');

            Route::prefix('users')->group(function() {
                Route::get('/export', [UserExportController::class, 'handleExport'])->middleware('permission:view_users|manage_users,sanctum');
                Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:edit_users|manage_users,sanctum');
            });
            Route::apiResource('users', UserController::class)
                ->only(['index', 'show'])
                ->middleware('permission:view_users|manage_users,sanctum');
            Route::apiResource('users', UserController::class)
                ->only(['store'])
                ->middleware('permission:create_users|manage_users,sanctum');
            Route::apiResource('users', UserController::class)
                ->only(['update'])
                ->middleware('permission:edit_users|manage_users,sanctum');
            Route::apiResource('users', UserController::class)
                ->only(['destroy'])
                ->middleware('permission:delete_users|manage_users,sanctum');
        });

        // FALLBACK: Keep the standard paths just in case other parts of your app use them
        Route::prefix('roles')->group(function() {
            Route::get('/export', [RoleExportController::class, 'handleExport'])->middleware('permission:view_roles|manage_roles,sanctum');
        });
        Route::get('/permissions/export', [PermissionExportController::class, 'handleExport'])->middleware('permission:view_permissions,sanctum');
        Route::get('/permissions', [RoleController::class, 'permissions'])->middleware('permission:view_permissions,sanctum');

        // Lightweight company directory for dropdowns (accessible to all authenticated users)
        Route::get('/directory/users', [UserController::class, 'directory']);

        Route::apiResource('roles', RoleController::class)
            ->only(['index', 'show'])
            ->middleware('permission:view_roles|manage_roles,sanctum');
        Route::apiResource('roles', RoleController::class)
            ->only(['store'])
            ->middleware('permission:create_roles|manage_roles,sanctum');
        Route::apiResource('roles', RoleController::class)
            ->only(['update'])
            ->middleware('permission:edit_roles|manage_roles,sanctum');
        Route::apiResource('roles', RoleController::class)
            ->only(['destroy'])
            ->middleware('permission:delete_roles|manage_roles,sanctum');

        Route::prefix('users')->group(function() {
            Route::get('/export', [UserExportController::class, 'handleExport'])->middleware('permission:view_users|manage_users,sanctum');
            Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:edit_users|manage_users,sanctum');
        });
        Route::apiResource('users', UserController::class)
            ->only(['index', 'show'])
            ->middleware('permission:view_users|manage_users,sanctum');
        Route::apiResource('users', UserController::class)
            ->only(['store'])
            ->middleware('permission:create_users|manage_users,sanctum');
        Route::apiResource('users', UserController::class)
            ->only(['update'])
            ->middleware('permission:edit_users|manage_users,sanctum');
        Route::apiResource('users', UserController::class)
            ->only(['destroy'])
            ->middleware('permission:delete_users|manage_users,sanctum');
    });
});
