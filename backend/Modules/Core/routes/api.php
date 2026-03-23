<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

use Modules\Core\Http\Controllers\ActivityLogController;
use Modules\Core\Http\Controllers\Settings\LocalizationController;
use Modules\Core\Http\Controllers\GlobalSearchController;
use Modules\Core\Http\Controllers\FileManagerController;
use Modules\Core\Http\Controllers\Settings\BrandSettingsController;
use Modules\Core\Http\Controllers\Settings\GeneralSettingsController;
use Modules\Core\Http\Controllers\Export\ActivityLogExportController;
use Modules\Core\Http\Controllers\Dashboard\DashboardController;
use Modules\Identity\Http\Controllers\UserController; // 🚀 ADDED: Import UserController

$centralDomains = ['localhost', '127.0.0.1', 'hive-os.com'];

// =========================================================================
// 1. CENTRAL COMMAND (Core)
// =========================================================================
foreach ($centralDomains as $domain) {
    Route::domain($domain)->prefix('v1')->group(function () {

        // Public
        Route::get('/translations/{locale}', [LocalizationController::class, 'fetchTranslations']);
        Route::get('/settings/brand/public', [BrandSettingsController::class, 'getPublicBrandSettings']);

        // Protected
        Route::middleware('auth:sanctum')->group(function () {

            // 🚀 Register Broadcast Auth for the Central Domain
            Broadcast::routes();

            // 🚀 USER IMPERSONATION (Central)
            Route::post('/central/users/{id}/impersonate', [UserController::class, 'impersonate']);

            // 🚀 DASHBOARD
            Route::get('/central/dashboard', [DashboardController::class, 'index']);
            Route::get('/search', [GlobalSearchController::class, 'search']);

            Route::prefix('settings')->group(function () {
                Route::get('/brand', [BrandSettingsController::class, 'getBrandSettings']);
                Route::post('/brand', [BrandSettingsController::class, 'updateBrandSettings']);
                Route::get('/general', [GeneralSettingsController::class, 'index']);
                Route::post('/general', [GeneralSettingsController::class, 'store']);
            });

            // 🌐 DYNAMIC LOCALIZATION MANAGEMENT
            Route::prefix('localization')->group(function () {
                Route::get('/languages', [LocalizationController::class, 'getLanguages']);
                Route::post('/languages', [LocalizationController::class, 'addLanguage']);
                Route::post('/languages/default', [LocalizationController::class, 'setDefaultLanguage']);
                Route::delete('/languages/{code}', [LocalizationController::class, 'destroyLanguage']);
                Route::get('/languages/{code}/translations', [LocalizationController::class, 'getTranslations']);
                Route::post('/translations/source', [LocalizationController::class, 'addSourceKey']);
                Route::post('/translations/source/delete', [LocalizationController::class, 'destroySourceKey']);
                Route::post('/translations/update', [LocalizationController::class, 'updateTranslation']);
                Route::post('/translations/delete', [LocalizationController::class, 'deleteTranslation']);
                Route::post('/publish', [LocalizationController::class, 'publishTranslations']);
            });

            // 📂 FILE MANAGER
            Route::prefix('files')->group(function () {
                Route::get('/', [FileManagerController::class, 'index']);
                Route::post('/folder', [FileManagerController::class, 'createFolder']);
                Route::post('/upload', [FileManagerController::class, 'uploadFile']);
                Route::post('/save-edited', [FileManagerController::class, 'saveEditedImage']);
                Route::post('/remove-background', [FileManagerController::class, 'removeBackground']);
                Route::post('/remove-logo-background', [FileManagerController::class, 'removeLogoBackground']);
                Route::post('/trash/empty', [FileManagerController::class, 'emptyTrash']);
                Route::post('/trash/restore', [FileManagerController::class, 'restoreItems']);
                Route::post('/trash/force-delete', [FileManagerController::class, 'forceDeleteItems']);
                Route::post('/rename', [FileManagerController::class, 'renameItem']);
                Route::post('/move', [FileManagerController::class, 'moveItems']);
                Route::post('/upload-subtitle/{id}', [FileManagerController::class, 'uploadSubtitle']);
                Route::get('/subtitle/{uuid}', [FileManagerController::class, 'serveSubtitle']);
                Route::delete('/subtitle/{uuid}', [FileManagerController::class, 'deleteSubtitle']);
                Route::get('/stream/{mediaId}/{filename}', [FileManagerController::class, 'serveStream']);
                Route::get('/{id}/download', [FileManagerController::class, 'downloadMedia']);
                Route::post('/{type}/{id}/share', [FileManagerController::class, 'generateShareLink'])->whereIn('type', ['file', 'folder']);
                Route::post('/{type}/{id}/favorite', [FileManagerController::class, 'toggleFavorite'])->whereIn('type', ['file', 'folder']);
                Route::delete('/{type}/{id}', [FileManagerController::class, 'destroy'])->whereIn('type', ['file', 'folder']);
            });

            // 🛡️ AUDIT LOGS
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
        });
    });
}

// =========================================================================
// 2. TENANT NODE (Core)
// =========================================================================
Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class
])->prefix('v1')->group(function () {

    // Public
    Route::get('/translations/{locale}', [LocalizationController::class, 'fetchTranslations']);
    Route::get('/settings/brand/public', [BrandSettingsController::class, 'getPublicBrandSettings']);
    Route::get('/tenant/settings/brand/public', [BrandSettingsController::class, 'getPublicBrandSettings']);

    // Protected
    Route::middleware('auth:sanctum')->group(function () {

        // 🚀 Register Broadcast Auth for the Tenant Domains
        Broadcast::routes();

        // 🚀 USER IMPERSONATION (Tenant)
        Route::post('/users/{id}/impersonate', [UserController::class, 'impersonate']);

        // 🚀 DASHBOARD
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/search', [GlobalSearchController::class, 'search']);

        Route::prefix('settings')->group(function () {
            Route::get('/brand', [BrandSettingsController::class, 'getBrandSettings']);
            Route::post('/brand', [BrandSettingsController::class, 'updateBrandSettings']);
            Route::get('/general', [GeneralSettingsController::class, 'index']);
            Route::post('/general', [GeneralSettingsController::class, 'store']);
        });

        // 🌐 DYNAMIC LOCALIZATION MANAGEMENT
        Route::prefix('localization')->group(function () {
            Route::get('/languages', [LocalizationController::class, 'getLanguages']);
            Route::post('/languages', [LocalizationController::class, 'addLanguage']);
            Route::post('/languages/default', [LocalizationController::class, 'setDefaultLanguage']);
            Route::delete('/languages/{code}', [LocalizationController::class, 'destroyLanguage']);
            Route::get('/languages/{code}/translations', [LocalizationController::class, 'getTranslations']);
            Route::post('/translations/source', [LocalizationController::class, 'addSourceKey']);
            Route::post('/translations/source/delete', [LocalizationController::class, 'destroySourceKey']);
            Route::post('/translations/update', [LocalizationController::class, 'updateTranslation']);
            Route::post('/translations/delete', [LocalizationController::class, 'deleteTranslation']);
            Route::post('/publish', [LocalizationController::class, 'publishTranslations']);
        });

        // 📂 FILE MANAGER
        Route::prefix('files')->group(function () {
            Route::get('/', [FileManagerController::class, 'index']);
            Route::post('/folder', [FileManagerController::class, 'createFolder']);
            Route::post('/upload', [FileManagerController::class, 'uploadFile']);
            Route::post('/save-edited', [FileManagerController::class, 'saveEditedImage']);
            Route::post('/remove-background', [FileManagerController::class, 'removeBackground']);
            Route::post('/remove-logo-background', [FileManagerController::class, 'removeLogoBackground']);
            Route::post('/trash/empty', [FileManagerController::class, 'emptyTrash']);
            Route::post('/trash/restore', [FileManagerController::class, 'restoreItems']);
            Route::post('/trash/force-delete', [FileManagerController::class, 'forceDeleteItems']);
            Route::post('/rename', [FileManagerController::class, 'renameItem']);
            Route::post('/move', [FileManagerController::class, 'moveItems']);
            Route::post('/upload-subtitle/{id}', [FileManagerController::class, 'uploadSubtitle']);
            Route::get('/subtitle/{uuid}', [FileManagerController::class, 'serveSubtitle']);
            Route::delete('/subtitle/{uuid}', [FileManagerController::class, 'deleteSubtitle']);
            Route::get('/stream/{mediaId}/{filename}', [FileManagerController::class, 'serveStream']);
            Route::get('/{id}/download', [FileManagerController::class, 'downloadMedia']);
            Route::post('/{type}/{id}/share', [FileManagerController::class, 'generateShareLink'])->whereIn('type', ['file', 'folder']);
            Route::post('/{type}/{id}/favorite', [FileManagerController::class, 'toggleFavorite'])->whereIn('type', ['file', 'folder']);
            Route::delete('/{type}/{id}', [FileManagerController::class, 'destroy'])->whereIn('type', ['file', 'folder']);
        });

        // 🛡️ AUDIT LOGS
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
    });
});
