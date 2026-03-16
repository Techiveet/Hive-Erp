<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\LocalizationController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\FileManagerController;
use App\Http\Controllers\Api\Export\UserExportController;
use App\Http\Controllers\Api\Export\RoleExportController;
use App\Http\Controllers\Api\Export\PermissionExportController;
use App\Http\Controllers\Api\Export\TenantExportController;
use App\Http\Controllers\Api\Export\ActivityLogExportController;

/*
|--------------------------------------------------------------------------
| API Routes Configuration - HIVE.OS
|--------------------------------------------------------------------------
*/

Route::get('/unauthorized', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

$centralDomains = ['localhost', '127.0.0.1', 'hive-os.com'];

// =========================================================================
// 1. CENTRAL COMMAND ROUTES (System Admin Level)
// =========================================================================

foreach ($centralDomains as $domain) {
    Route::domain($domain)->prefix('v1')->group(function () {

        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/verify-2fa', [AuthController::class, 'verify2FA']);
        Route::get('/password-policy', [AuthController::class, 'passwordPolicy']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        // 🌍 PUBLIC LOCALIZATION: Fetch compiled dictionary
        Route::get('/translations/{locale}', [LocalizationController::class, 'fetchTranslations']);

        Route::get('/check-tenant/{tenant_id}', function ($tenant_id) {
            $exists = \App\Models\Tenant::where('id', $tenant_id)->exists();
            return response()->json(['valid' => $exists], $exists ? 200 : 404);
        });

        // --- Protected Central Routes ---
        Route::middleware('auth:sanctum')->group(function () {

            Route::get('/user', [AuthController::class, 'user']);
            Route::post('/logout', [AuthController::class, 'logout']);

            Route::prefix('2fa')->group(function () {
                Route::post('/enable', [TwoFactorController::class, 'enable']);
                Route::post('/confirm', [TwoFactorController::class, 'confirm']);
                Route::post('/disable', [TwoFactorController::class, 'destroy']);
            });

            Route::get('/central/dashboard', function () {
                return response()->json([
                    'node' => 'CENTRAL_COMMAND',
                    'stats' => ['active_tenants' => \App\Models\Tenant::count(), 'load' => 'optimal'],
                ]);
            });

            // 🚀 GLOBAL SEARCH ENGINE (Central)
            Route::get('/search', [GlobalSearchController::class, 'search']);

            // 📂 FILE MANAGER ROUTES
            Route::prefix('files')->group(function () {
                // 1. Static Routes (Must come FIRST)
                Route::get('/', [FileManagerController::class, 'index']);
                Route::post('/folder', [FileManagerController::class, 'createFolder']);
                Route::post('/upload', [FileManagerController::class, 'uploadFile']);
                Route::post('/save-edited', [FileManagerController::class, 'saveEditedImage']);

                // 2. Specific Parameter Routes
                Route::post('/upload-subtitle/{id}', [FileManagerController::class, 'uploadSubtitle']);
                Route::get('/subtitle/{uuid}', [FileManagerController::class, 'serveSubtitle']);
                Route::delete('/subtitle/{uuid}', [FileManagerController::class, 'deleteSubtitle']);
                Route::get('/stream/{mediaId}/{filename}', [FileManagerController::class, 'serveStream']);
                Route::get('/{id}/download', [FileManagerController::class, 'downloadMedia']); // 🚀 NEW ENDPOINT

                // 3. Generic Catch-All Parameter Routes (Must come LAST)
                Route::post('/{type}/{id}/favorite', [FileManagerController::class, 'toggleFavorite'])
                    ->whereIn('type', ['file', 'folder']);
                Route::delete('/{type}/{id}', [FileManagerController::class, 'destroy'])
                    ->whereIn('type', ['file', 'folder']);
            });

            // 🌐 DYNAMIC LOCALIZATION MANAGEMENT (Central Matrix)
            Route::prefix('localization')->group(function () {
                Route::get('/languages', [LocalizationController::class, 'getLanguages']);
                Route::post('/languages', [LocalizationController::class, 'addLanguage']);
                Route::post('/languages/default', [LocalizationController::class, 'setDefaultLanguage']);
                Route::delete('/languages/{code}', [LocalizationController::class, 'deleteLanguage']);
                Route::post('/translations/source', [LocalizationController::class, 'addSourceKey']);
                Route::post('/translations/update', [LocalizationController::class, 'updateTranslation']);
                Route::post('/translations/delete', [LocalizationController::class, 'deleteTranslation']);
                Route::post('/publish', [LocalizationController::class, 'publishTranslations']);
            });

            // 🛡️ SURVEILLANCE & AUDIT LOGS
            Route::prefix('logs')->group(function () {
                Route::get('/export', [ActivityLogExportController::class, 'handleExport']);
                Route::post('/client-action', [ActivityLogController::class, 'logClientAction']);

                Route::get('/archived', [ActivityLogController::class, 'archivedIndex']);
                Route::delete('/archived/{id}', [ActivityLogController::class, 'destroyArchived']);
                Route::post('/archived/bulk-delete', [ActivityLogController::class, 'bulkDestroyArchived']);

                Route::get('/settings', [ActivityLogController::class, 'getSettings']);
                Route::post('/settings', [ActivityLogController::class, 'updateSettings']);
                Route::post('/archive', [ActivityLogController::class, 'archiveOldLogs']);

                Route::get('/', [ActivityLogController::class, 'index']);
            });

            // 🚀 TENANT MANAGEMENT
            Route::prefix('tenants')->group(function() {
                Route::get('/export', [TenantExportController::class, 'handleExport']);
                Route::post('/{id}/toggle-status', [TenantController::class, 'toggleStatus']);
                Route::post('/{id}/toggle-admin-status', [TenantController::class, 'toggleAdminStatus']);
            });
            Route::apiResource('tenants', TenantController::class);

            // 🔑 ROLE & PERMISSION MANAGEMENT
            Route::prefix('roles')->group(function() {
                Route::get('/export', [RoleExportController::class, 'handleExport']);
            });
            Route::get('/permissions/export', [PermissionExportController::class, 'handleExport']);
            Route::get('/permissions', [RoleController::class, 'permissions']);
            Route::apiResource('roles', RoleController::class);

            // 👥 USER MANAGEMENT
            Route::prefix('users')->group(function() {
                Route::get('/export', [UserExportController::class, 'handleExport']);
                Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus']);
            });
            Route::apiResource('users', UserController::class);
        });
    });
}

// =========================================================================
// 2. TENANT NODE ROUTES (Isolated Environment)
// =========================================================================
Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class
])->prefix('v1')->group(function () {

    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-2fa', [AuthController::class, 'verify2FA']);
    Route::get('/password-policy', [AuthController::class, 'passwordPolicy']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // 🌍 PUBLIC LOCALIZATION: Fetch compiled dictionary
    Route::get('/translations/{locale}', [LocalizationController::class, 'fetchTranslations']);

    // --- Protected Tenant Routes ---
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::prefix('2fa')->group(function () {
            Route::post('/enable', [TwoFactorController::class, 'enable']);
            Route::post('/confirm', [TwoFactorController::class, 'confirm']);
            Route::post('/disable', [TwoFactorController::class, 'destroy']);
        });

        Route::get('/dashboard', function () {
            return response()->json([
                'company' => ucfirst(tenant('id')) . ' Corp',
                'node_id' => tenant('id'),
            ]);
        });

        // 🚀 GLOBAL SEARCH ENGINE (Tenant)
        Route::get('/search', [GlobalSearchController::class, 'search']);

        // 📂 FILE MANAGER ROUTES (Tenant - Strict Isolation)
        Route::prefix('files')->group(function () {
            // 1. Static Routes (Must come FIRST)
            Route::get('/', [FileManagerController::class, 'index']);
            Route::post('/folder', [FileManagerController::class, 'createFolder']);
            Route::post('/upload', [FileManagerController::class, 'uploadFile']);
            Route::post('/save-edited', [FileManagerController::class, 'saveEditedImage']);

            // 2. Specific Parameter Routes
            Route::post('/upload-subtitle/{id}', [FileManagerController::class, 'uploadSubtitle']);
            Route::get('/subtitle/{uuid}', [FileManagerController::class, 'serveSubtitle']);
            Route::delete('/subtitle/{uuid}', [FileManagerController::class, 'deleteSubtitle']);
            Route::get('/stream/{mediaId}/{filename}', [FileManagerController::class, 'serveStream']);
            Route::get('/{id}/download', [FileManagerController::class, 'downloadMedia']); // 🚀 NEW ENDPOINT

            // 3. Generic Catch-All Parameter Routes (Must come LAST)
            Route::post('/{type}/{id}/favorite', [FileManagerController::class, 'toggleFavorite'])
                ->whereIn('type', ['file', 'folder']);
            Route::delete('/{type}/{id}', [FileManagerController::class, 'destroy'])
                ->whereIn('type', ['file', 'folder']);
        });

        // 🌐 DYNAMIC LOCALIZATION MANAGEMENT (Tenant Matrix)
        Route::prefix('localization')->group(function () {
            Route::get('/languages', [LocalizationController::class, 'getLanguages']);
            Route::post('/languages', [LocalizationController::class, 'addLanguage']);
            Route::post('/languages/default', [LocalizationController::class, 'setDefaultLanguage']);
            Route::delete('/languages/{code}', [LocalizationController::class, 'deleteLanguage']);

            Route::post('/translations/source', [LocalizationController::class, 'addSourceKey']);
            Route::post('/translations/update', [LocalizationController::class, 'updateTranslation']);
            Route::post('/translations/delete', [LocalizationController::class, 'deleteTranslation']);
            Route::post('/publish', [LocalizationController::class, 'publishTranslations']);
        });

        // 🛡️ TENANT SURVEILLANCE & AUDIT LOGS
        Route::prefix('logs')->group(function () {
            Route::get('/export', [ActivityLogExportController::class, 'handleExport']);
            Route::post('/client-action', [ActivityLogController::class, 'logClientAction']);

            Route::get('/archived', [ActivityLogController::class, 'archivedIndex']);
            Route::delete('/archived/{id}', [ActivityLogController::class, 'destroyArchived']);
            Route::post('/archived/bulk-delete', [ActivityLogController::class, 'bulkDestroyArchived']);

            Route::get('/settings', [ActivityLogController::class, 'getSettings']);
            Route::post('/settings', [ActivityLogController::class, 'updateSettings']);
            Route::post('/archive', [ActivityLogController::class, 'archiveOldLogs']);

            Route::get('/', [ActivityLogController::class, 'index']);
        });

        // 🔑 ROLE & PERMISSION MANAGEMENT (Local)
        Route::prefix('roles')->group(function() {
            Route::get('/export', [RoleExportController::class, 'handleExport']);
        });
        Route::get('/permissions', [RoleController::class, 'permissions']);
        Route::apiResource('roles', RoleController::class);

        // 👥 USER MANAGEMENT (Local)
        Route::prefix('users')->group(function() {
            Route::get('/export', [UserExportController::class, 'handleExport']);
            Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        });
        Route::apiResource('users', UserController::class);
    });
});
