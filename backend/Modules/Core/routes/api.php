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
use Modules\Core\Http\Controllers\Dashboard\SystemOperationsController;
use Modules\Core\Http\Controllers\SystemAlertController;
use Modules\Core\Http\Controllers\Tools\FileConverterController; // 🚀 ADDED: File Converter
use Modules\Identity\Http\Controllers\UserController;
use Modules\Core\Models\Setting;

$centralDomains = ['localhost', '127.0.0.1', 'hive-os.com'];

// =========================================================================
// 1. CENTRAL COMMAND (Core)
// =========================================================================
foreach ($centralDomains as $domain) {
    Route::domain($domain)->prefix('v1')->group(function () {

        Route::get('/translations/{locale}', [LocalizationController::class, 'fetchTranslations']);
        Route::get('/settings/brand/public', [BrandSettingsController::class, 'getPublicBrandSettings']);

        Route::get('/system/status-ticker', function() {
            $message = Setting::where('key', 'maintenance_message')->value('value')
                       ?? 'HIVE.OS: System neural links are currently undergoing optimization.';
            return response()->json(['message' => $message]);
        });

        // 🚀 LARGE FILE DOWNLOAD ROUTE (Token Auth to avoid browser memory crash)
        Route::get('/system/backups/{id}/download', [SystemOperationsController::class, 'downloadBackup']);

        Route::middleware('auth:sanctum')->group(function () {
            Broadcast::routes();
            Route::post('/central/users/{id}/impersonate', [UserController::class, 'impersonate']);
            Route::get('/central/dashboard', [DashboardController::class, 'index']);
            Route::get('/search', [GlobalSearchController::class, 'search']);

            Route::prefix('system')->group(function () {
                Route::post('/flush-cache', [SystemOperationsController::class, 'flushCache']);
                Route::post('/trigger-backup', [SystemOperationsController::class, 'triggerBackup']);
                Route::post('/backup/schedule', [SystemOperationsController::class, 'updateSchedule']);
                Route::get('/backups', [SystemOperationsController::class, 'getBackups']);
                Route::delete('/backups/{id}', [SystemOperationsController::class, 'deleteBackup']);
                Route::get('/alerts', [SystemAlertController::class, 'index']);
                Route::delete('/alerts/{id}', [SystemAlertController::class, 'destroy']);
                Route::post('/alerts/clear-all', [SystemAlertController::class, 'clearAll']);
            });

            // 🔄 FILE CONVERTER ENGINE (Central)
            Route::prefix('convert')->group(function () {
                Route::post('/html-to-pdf', [FileConverterController::class, 'htmlToPdf']);
            });

            Route::prefix('settings')->group(function () {
                Route::get('/brand', [BrandSettingsController::class, 'getBrandSettings']);
                Route::post('/brand', [BrandSettingsController::class, 'updateBrandSettings']);
                Route::get('/general', [GeneralSettingsController::class, 'index']);
                Route::post('/general', [GeneralSettingsController::class, 'store']);
            });

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

    Route::get('/translations/{locale}', [LocalizationController::class, 'fetchTranslations']);
    Route::get('/settings/brand/public', [BrandSettingsController::class, 'getPublicBrandSettings']);
    Route::get('/tenant/settings/brand/public', [BrandSettingsController::class, 'getPublicBrandSettings']);

    Route::prefix('system')->group(function () {
        Route::get('/status-ticker', function() {
            $message = Setting::where('key', 'maintenance_message')->value('value')
                       ?? 'HIVE.OS: System neural links are currently undergoing optimization.';
            return response()->json(['message' => $message]);
        });
    });

    // 🚀 LARGE FILE DOWNLOAD ROUTE (Tenant)
    Route::get('/system/backups/{id}/download', [SystemOperationsController::class, 'downloadBackup']);

    Route::middleware('auth:sanctum')->group(function () {
        Broadcast::routes();
        Route::post('/users/{id}/impersonate', [UserController::class, 'impersonate']);
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/search', [GlobalSearchController::class, 'search']);

        Route::prefix('system')->group(function () {
            Route::post('/flush-cache', [SystemOperationsController::class, 'flushCache']);
            Route::post('/trigger-backup', [SystemOperationsController::class, 'triggerBackup']);
            Route::post('/backup/schedule', [SystemOperationsController::class, 'updateSchedule']);
            Route::get('/backups', [SystemOperationsController::class, 'getBackups']);
            Route::delete('/backups/{id}', [SystemOperationsController::class, 'deleteBackup']);
            Route::get('/alerts', [SystemAlertController::class, 'index']);
            Route::delete('/alerts/{id}', [SystemAlertController::class, 'destroy']);
            Route::post('/alerts/clear-all', [SystemAlertController::class, 'clearAll']);
        });

        // 🔄 FILE CONVERTER ENGINE (Tenant)
        Route::prefix('convert')->group(function () {
            Route::post('/html-to-pdf', [FileConverterController::class, 'htmlToPdf']);
        });

        Route::prefix('settings')->group(function () {
            Route::get('/brand', [BrandSettingsController::class, 'getBrandSettings']);
            Route::post('/brand', [BrandSettingsController::class, 'updateBrandSettings']);
            Route::get('/general', [GeneralSettingsController::class, 'index']);
            Route::post('/general', [GeneralSettingsController::class, 'store']);
        });

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
