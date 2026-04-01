<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Middleware\InitializeTenantContext;

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

        Route::middleware(['auth:sanctum', 'active_status', 'dynamic_timeout'])->group(function () {
            Broadcast::routes();
            Route::post('/central/users/{id}/impersonate', [UserController::class, 'impersonate'])->middleware('permission:manage_users,sanctum');
            Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:view_system_dashboard,sanctum');
            Route::get('/central/dashboard', [DashboardController::class, 'index'])->middleware('permission:view_system_dashboard,sanctum');
            Route::get('/search', [GlobalSearchController::class, 'search'])->middleware('permission:view_system_dashboard,sanctum');

            Route::prefix('system')->group(function () {
                Route::post('/flush-cache', [SystemOperationsController::class, 'flushCache'])->middleware('permission:manage_system_settings,sanctum');
                Route::post('/trigger-backup', [SystemOperationsController::class, 'triggerBackup'])->middleware('permission:manage_backups,sanctum');
                Route::post('/backup/schedule', [SystemOperationsController::class, 'updateSchedule'])->middleware('permission:manage_backups,sanctum');
                Route::get('/backups', [SystemOperationsController::class, 'getBackups'])->middleware('permission:view_backups,sanctum');
                Route::delete('/backups/{id}', [SystemOperationsController::class, 'deleteBackup'])->middleware('permission:manage_backups,sanctum');
                Route::get('/alerts', [SystemAlertController::class, 'index'])->middleware('permission:view_alerts,sanctum');
                Route::delete('/alerts/{id}', [SystemAlertController::class, 'destroy'])->middleware('permission:manage_alerts,sanctum');
                Route::post('/alerts/clear-all', [SystemAlertController::class, 'clearAll'])->middleware('permission:manage_alerts,sanctum');
            });

            // 🔄 FILE CONVERTER ENGINE (Central)
            Route::prefix('convert')->middleware('permission:manage_storage,sanctum')->group(function () {
                Route::post('/html-to-pdf', [FileConverterController::class, 'htmlToPdf']);
            });

            Route::prefix('settings')->group(function () {
                Route::get('/brand', [BrandSettingsController::class, 'getBrandSettings']);
                Route::post('/brand', [BrandSettingsController::class, 'updateBrandSettings'])->middleware('permission:manage_brand_settings,sanctum');
                Route::get('/general', [GeneralSettingsController::class, 'index']);
                Route::post('/general', [GeneralSettingsController::class, 'store'])->middleware('permission:manage_general_settings,sanctum');
            });

            Route::prefix('localization')->group(function () {
                Route::get('/languages', [LocalizationController::class, 'getLanguages'])->middleware('permission:manage_localization,sanctum');
                Route::post('/languages', [LocalizationController::class, 'addLanguage'])->middleware('permission:manage_localization,sanctum');
                Route::post('/languages/default', [LocalizationController::class, 'setDefaultLanguage'])->middleware('permission:manage_localization,sanctum');
                Route::delete('/languages/{code}', [LocalizationController::class, 'destroyLanguage'])->middleware('permission:manage_localization,sanctum');
                Route::get('/languages/{code}/translations', [LocalizationController::class, 'getTranslations'])->middleware('permission:manage_localization,sanctum');
                Route::post('/translations/source', [LocalizationController::class, 'addSourceKey'])->middleware('permission:manage_localization,sanctum');
                Route::post('/translations/source/delete', [LocalizationController::class, 'destroySourceKey'])->middleware('permission:manage_localization,sanctum');
                Route::post('/translations/update', [LocalizationController::class, 'updateTranslation'])->middleware('permission:manage_localization,sanctum');
                Route::post('/translations/delete', [LocalizationController::class, 'deleteTranslation'])->middleware('permission:manage_localization,sanctum');
                Route::post('/publish', [LocalizationController::class, 'publishTranslations'])->middleware('permission:manage_localization,sanctum');
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
                Route::get('/export', [ActivityLogExportController::class, 'handleExport'])->middleware('permission:export_logs,sanctum');
                Route::get('/filter-options', [ActivityLogController::class, 'filterOptions'])->middleware('permission:view_logs,sanctum');
                Route::post('/client-action', [ActivityLogController::class, 'logClientAction']);
                Route::get('/archived', [ActivityLogController::class, 'archivedIndex'])->middleware('permission:view_logs,sanctum');
                Route::delete('/archived/{id}', [ActivityLogController::class, 'destroyArchived'])->middleware('permission:delete_archived_logs,sanctum');
                Route::post('/archived/bulk-delete', [ActivityLogController::class, 'bulkDestroyArchived'])->middleware('permission:delete_archived_logs,sanctum');
                Route::get('/settings', [ActivityLogController::class, 'getSettings'])->middleware('permission:manage_log_settings,sanctum');
                Route::post('/settings', [ActivityLogController::class, 'updateSettings'])->middleware('permission:manage_log_settings,sanctum');
                Route::post('/archive', [ActivityLogController::class, 'archiveOldLogs'])->middleware('permission:archive_logs,sanctum');
                Route::get('/', [ActivityLogController::class, 'index'])->middleware('permission:view_logs,sanctum');
            });
        });
    });
}

// Docker-safe central aliases.
// These are not bound to a specific central domain, so central API calls still
// work when the app is reached through a container hostname or reverse proxy.
Route::prefix('v1')->middleware(['auth:sanctum', 'active_status', 'dynamic_timeout'])->group(function () {
    Route::get('/central/dashboard', [DashboardController::class, 'index'])->middleware('permission:view_system_dashboard,sanctum');
});

// =========================================================================
// 2. TENANT NODE (Core)
// =========================================================================
Route::middleware([
    InitializeTenantContext::class,
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

    Route::middleware(['auth:sanctum', 'active_status', 'dynamic_timeout'])->group(function () {
        Broadcast::routes();
        Route::post('/users/{id}/impersonate', [UserController::class, 'impersonate'])->middleware('permission:manage_users,sanctum');
        Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:view_system_dashboard,sanctum');
        Route::get('/tenant/dashboard', [DashboardController::class, 'index'])->middleware('permission:view_system_dashboard,sanctum');
        Route::get('/search', [GlobalSearchController::class, 'search'])->middleware('permission:view_system_dashboard,sanctum');

        Route::prefix('system')->group(function () {
            Route::post('/flush-cache', [SystemOperationsController::class, 'flushCache'])->middleware('permission:manage_system_settings,sanctum');
            Route::post('/trigger-backup', [SystemOperationsController::class, 'triggerBackup'])->middleware('permission:manage_backups,sanctum');
            Route::post('/backup/schedule', [SystemOperationsController::class, 'updateSchedule'])->middleware('permission:manage_backups,sanctum');
            Route::get('/backups', [SystemOperationsController::class, 'getBackups'])->middleware('permission:view_backups,sanctum');
            Route::delete('/backups/{id}', [SystemOperationsController::class, 'deleteBackup'])->middleware('permission:manage_backups,sanctum');
            Route::get('/alerts', [SystemAlertController::class, 'index'])->middleware('permission:view_alerts,sanctum');
            Route::delete('/alerts/{id}', [SystemAlertController::class, 'destroy'])->middleware('permission:manage_alerts,sanctum');
            Route::post('/alerts/clear-all', [SystemAlertController::class, 'clearAll'])->middleware('permission:manage_alerts,sanctum');
        });

        // 🔄 FILE CONVERTER ENGINE (Tenant)
        Route::prefix('convert')->middleware('permission:manage_storage,sanctum')->group(function () {
            Route::post('/html-to-pdf', [FileConverterController::class, 'htmlToPdf']);
        });

        Route::prefix('settings')->group(function () {
            Route::get('/brand', [BrandSettingsController::class, 'getBrandSettings']);
            Route::post('/brand', [BrandSettingsController::class, 'updateBrandSettings'])->middleware('permission:manage_brand_settings,sanctum');
            Route::get('/general', [GeneralSettingsController::class, 'index']);
            Route::post('/general', [GeneralSettingsController::class, 'store'])->middleware('permission:manage_general_settings,sanctum');
        });

        Route::prefix('localization')->group(function () {
            Route::get('/languages', [LocalizationController::class, 'getLanguages'])->middleware('permission:manage_localization,sanctum');
            Route::post('/languages', [LocalizationController::class, 'addLanguage'])->middleware('permission:manage_localization,sanctum');
            Route::post('/languages/default', [LocalizationController::class, 'setDefaultLanguage'])->middleware('permission:manage_localization,sanctum');
            Route::delete('/languages/{code}', [LocalizationController::class, 'destroyLanguage'])->middleware('permission:manage_localization,sanctum');
            Route::get('/languages/{code}/translations', [LocalizationController::class, 'getTranslations'])->middleware('permission:manage_localization,sanctum');
            Route::post('/translations/source', [LocalizationController::class, 'addSourceKey'])->middleware('permission:manage_localization,sanctum');
            Route::post('/translations/source/delete', [LocalizationController::class, 'destroySourceKey'])->middleware('permission:manage_localization,sanctum');
            Route::post('/translations/update', [LocalizationController::class, 'updateTranslation'])->middleware('permission:manage_localization,sanctum');
            Route::post('/translations/delete', [LocalizationController::class, 'deleteTranslation'])->middleware('permission:manage_localization,sanctum');
            Route::post('/publish', [LocalizationController::class, 'publishTranslations'])->middleware('permission:manage_localization,sanctum');
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
            Route::get('/export', [ActivityLogExportController::class, 'handleExport'])->middleware('permission:export_logs,sanctum');
            Route::get('/filter-options', [ActivityLogController::class, 'filterOptions'])->middleware('permission:view_logs,sanctum');
            Route::post('/client-action', [ActivityLogController::class, 'logClientAction']);
            Route::get('/archived', [ActivityLogController::class, 'archivedIndex'])->middleware('permission:view_logs,sanctum');
            Route::delete('/archived/{id}', [ActivityLogController::class, 'destroyArchived'])->middleware('permission:delete_archived_logs,sanctum');
            Route::post('/archived/bulk-delete', [ActivityLogController::class, 'bulkDestroyArchived'])->middleware('permission:delete_archived_logs,sanctum');
            Route::get('/settings', [ActivityLogController::class, 'getSettings'])->middleware('permission:manage_log_settings,sanctum');
            Route::post('/settings', [ActivityLogController::class, 'updateSettings'])->middleware('permission:manage_log_settings,sanctum');
            Route::post('/archive', [ActivityLogController::class, 'archiveOldLogs'])->middleware('permission:archive_logs,sanctum');
            Route::get('/', [ActivityLogController::class, 'index'])->middleware('permission:view_logs,sanctum');
        });
    });
});
