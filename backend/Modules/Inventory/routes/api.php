<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\InventoryCategoryController;
use Modules\Inventory\Http\Controllers\InventoryBatchQaResultController;
use Modules\Inventory\Http\Controllers\InventoryBlueprintController;
use Modules\Inventory\Http\Controllers\InventoryDocumentController;
use Modules\Inventory\Http\Controllers\InventoryEntityRecordController;
use Modules\Inventory\Http\Controllers\InventoryExportController;
use Modules\Inventory\Http\Controllers\InventoryFileController;
use Modules\Inventory\Http\Controllers\InventoryItemController;
use Modules\Inventory\Http\Controllers\InventoryMigrationMatrixController;
use Modules\Inventory\Http\Controllers\InventoryOverviewController;
use Modules\Inventory\Http\Controllers\InventoryPublicVerificationController;
use Modules\Inventory\Http\Controllers\InventoryReportController;
use Modules\Inventory\Http\Controllers\InventoryStockLedgerController;
use Modules\Inventory\Http\Controllers\InventoryTransactionController;
use Modules\Inventory\Http\Controllers\InventoryWorkflowAliasController;
use Modules\Inventory\Http\Controllers\ProductCategoryController;
use Modules\Inventory\Http\Controllers\ProductController;
use Modules\Inventory\Http\Controllers\SupplierController;
use Modules\Inventory\Http\Controllers\TagController;
use Modules\Inventory\Support\InventoryEntityCatalog;
use Modules\Inventory\Support\InventoryWorkflowAliasCatalog;

Route::middleware(['auth:sanctum'])
    ->prefix('v1/inventory')
    ->name('api.inventory.')
    ->group(function (): void {
        Route::get('overview', InventoryOverviewController::class)
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('overview');
        Route::get('dashboard', InventoryOverviewController::class)
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('dashboard');

        Route::get('blueprint', InventoryBlueprintController::class)
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('blueprint');
        Route::get('migration-matrix', InventoryMigrationMatrixController::class)
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('migration-matrix');

        Route::get('transactions', [InventoryTransactionController::class, 'index'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('transactions.index');

        Route::get('stock-ledger', [InventoryStockLedgerController::class, 'ledger'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('stock-ledger.index');
        Route::get('stock-adjustments', [InventoryStockLedgerController::class, 'stockAdjustments'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('stock-adjustments.index');
        Route::post('stock-adjustments', [InventoryStockLedgerController::class, 'createStockAdjustment'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('stock-adjustments.store');

        Route::get('documents', [InventoryDocumentController::class, 'index'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('documents.index');
        Route::post('documents', [InventoryDocumentController::class, 'store'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('documents.store');
        Route::get('documents/{id}', [InventoryDocumentController::class, 'show'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('documents.show');
        Route::match(['put', 'patch'], 'documents/{id}', [InventoryDocumentController::class, 'update'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('documents.update');
        Route::post('documents/{id}/actions/{action}', [InventoryDocumentController::class, 'action'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('documents.action');
        Route::post('documents/{id}/assets', [InventoryDocumentController::class, 'storeAsset'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('documents.assets.store');

        Route::get('reports', [InventoryReportController::class, 'index'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('reports.index');
        Route::get('reports/{report}', [InventoryReportController::class, 'show'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('reports.show');
        Route::get('exports/{resource}/{id}/pdf', [InventoryWorkflowAliasController::class, 'pdf'])
            ->where('resource', InventoryWorkflowAliasCatalog::routeRegex())
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('exports.pdf');
        Route::get('{resource}/{id}/pdf', [InventoryWorkflowAliasController::class, 'pdf'])
            ->where('resource', InventoryWorkflowAliasCatalog::routeRegex())
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('workflow.pdf');
        Route::post('delivery-notes/{id}/routes', [InventoryWorkflowAliasController::class, 'assignDeliveryRoutes'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('delivery-notes.routes');

        Route::get('product-batches/{batchId}/qa-results', [InventoryBatchQaResultController::class, 'index'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('product-batches.qa-results.index');
        Route::post('product-batches/{batchId}/qa-results', [InventoryBatchQaResultController::class, 'store'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('product-batches.qa-results.store');

        Route::get('categories', [InventoryCategoryController::class, 'index'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('categories.index');
        Route::post('categories', [InventoryCategoryController::class, 'store'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('categories.store');
        Route::get('categories/{id}', [InventoryCategoryController::class, 'show'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('categories.show');
        Route::match(['put', 'patch'], 'categories/{id}', [InventoryCategoryController::class, 'update'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('categories.update');
        Route::delete('categories/{id}', [InventoryCategoryController::class, 'destroy'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('categories.destroy');

        Route::get('items', [InventoryItemController::class, 'index'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('items.index');
        Route::post('items', [InventoryItemController::class, 'store'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('items.store');
        Route::get('items/{id}', [InventoryItemController::class, 'show'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('items.show');
        Route::match(['put', 'patch'], 'items/{id}', [InventoryItemController::class, 'update'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('items.update');
        Route::delete('items/{id}', [InventoryItemController::class, 'destroy'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('items.destroy');
        Route::post('items/{id}/adjust-stock', [InventoryItemController::class, 'adjustStock'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('items.adjust-stock');

        Route::get('product-categories', [ProductCategoryController::class, 'index'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('product-categories.index');
        Route::get('product-categories/export', [InventoryExportController::class, 'productCategories'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('product-categories.export');
        Route::post('product-categories', [ProductCategoryController::class, 'store'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('product-categories.store');
        Route::get('product-categories/{id}', [ProductCategoryController::class, 'show'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('product-categories.show');
        Route::match(['put', 'patch'], 'product-categories/{id}', [ProductCategoryController::class, 'update'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('product-categories.update');
        Route::delete('product-categories/{id}', [ProductCategoryController::class, 'destroy'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('product-categories.destroy');

        Route::get('tags', [TagController::class, 'index'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('tags.index');
        Route::get('tags/export', [InventoryExportController::class, 'tags'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('tags.export');
        Route::post('tags', [TagController::class, 'store'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('tags.store');
        Route::get('tags/{id}', [TagController::class, 'show'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('tags.show');
        Route::match(['put', 'patch'], 'tags/{id}', [TagController::class, 'update'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('tags.update');
        Route::delete('tags/{id}', [TagController::class, 'destroy'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('tags.destroy');

        Route::get('suppliers', [SupplierController::class, 'index'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('suppliers.index');
        Route::get('suppliers/export', [InventoryExportController::class, 'suppliers'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('suppliers.export');
        Route::post('suppliers', [SupplierController::class, 'store'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('suppliers.store');
        Route::get('suppliers/{id}', [SupplierController::class, 'show'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('suppliers.show');
        Route::match(['put', 'patch'], 'suppliers/{id}', [SupplierController::class, 'update'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('suppliers.update');
        Route::post('suppliers/{id}/deactivate', [SupplierController::class, 'deactivate'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('suppliers.deactivate');
        Route::delete('suppliers/{id}', [SupplierController::class, 'destroy'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('suppliers.destroy');

        Route::get('products/summary', [ProductController::class, 'summary'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('products.summary');
        Route::get('products/export', [InventoryExportController::class, 'products'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('products.export');
        Route::get('products/options', [ProductController::class, 'options'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('products.options');
        Route::post('products/generate-barcode', [ProductController::class, 'generateBarcode'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('products.generate-barcode');
        Route::post('products/bulk-delete', [ProductController::class, 'bulkDelete'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('products.bulk-delete');
        Route::post('products/bulk-status', [ProductController::class, 'bulkUpdateStatus'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('products.bulk-status');
        Route::get('products', [ProductController::class, 'index'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('products.index');
        Route::post('products', [ProductController::class, 'store'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('products.store');
        Route::get('products/{id}', [ProductController::class, 'show'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('products.show');
        Route::match(['put', 'patch'], 'products/{id}', [ProductController::class, 'update'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('products.update');
        Route::delete('products/{id}', [ProductController::class, 'destroy'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('products.destroy');
        Route::post('products/{id}/tags', [ProductController::class, 'syncTags'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('products.tags');

        Route::group(['middleware' => 'permission:manage_inventory,sanctum'], function () {
            // Explicit routes using closures to ensure correct parameter mapping
            Route::get('warehouses', [InventoryEntityRecordController::class, 'index'])->defaults('resource', 'warehouses');
            Route::post('warehouses', [InventoryEntityRecordController::class, 'store'])->defaults('resource', 'warehouses');
            Route::get('warehouses/{id}', fn($id) => app(InventoryEntityRecordController::class)->show('warehouses', $id));
            Route::match(['put', 'patch'], 'warehouses/{id}', fn(\Illuminate\Http\Request $request, $id) => app(InventoryEntityRecordController::class)->update($request, 'warehouses', $id));
            Route::delete('warehouses/{id}', fn($id) => app(InventoryEntityRecordController::class)->destroy('warehouses', $id));

            Route::get('shelves', [InventoryEntityRecordController::class, 'index'])->defaults('resource', 'shelves');
            Route::post('shelves', [InventoryEntityRecordController::class, 'store'])->defaults('resource', 'shelves');
            Route::get('shelves/{id}', fn($id) => app(InventoryEntityRecordController::class)->show('shelves', $id));
            Route::match(['put', 'patch'], 'shelves/{id}', fn(\Illuminate\Http\Request $request, $id) => app(InventoryEntityRecordController::class)->update($request, 'shelves', $id));
            Route::delete('shelves/{id}', fn($id) => app(InventoryEntityRecordController::class)->destroy('shelves', $id));

            Route::get('shelf-boxes', [InventoryEntityRecordController::class, 'index'])->defaults('resource', 'shelf-boxes');
            Route::post('shelf-boxes', [InventoryEntityRecordController::class, 'store'])->defaults('resource', 'shelf-boxes');
            Route::get('shelf-boxes/{id}', fn($id) => app(InventoryEntityRecordController::class)->show('shelf-boxes', $id));
            Route::match(['put', 'patch'], 'shelf-boxes/{id}', fn(\Illuminate\Http\Request $request, $id) => app(InventoryEntityRecordController::class)->update($request, 'shelf-boxes', $id));
            Route::delete('shelf-boxes/{id}', fn($id) => app(InventoryEntityRecordController::class)->destroy('shelf-boxes', $id));
        });
        Route::get('{resource}', [InventoryEntityRecordController::class, 'index'])
            ->where('resource', InventoryEntityCatalog::routeRegex())
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('entity.index');
        Route::get('{resource}/export', [InventoryExportController::class, 'entities'])
            ->where('resource', InventoryEntityCatalog::routeRegex())
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('entity.export');
        Route::post('{resource}', [InventoryEntityRecordController::class, 'store'])
            ->where('resource', InventoryEntityCatalog::routeRegex())
            ->middleware('permission:manage_inventory,sanctum')
            ->name('entity.store');
        Route::get('{resource}/{id}', [InventoryEntityRecordController::class, 'show'])
            ->where('resource', InventoryEntityCatalog::routeRegex())
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('entity.show');
        Route::match(['put', 'patch'], '{resource}/{id}', [InventoryEntityRecordController::class, 'update'])
            ->where('resource', InventoryEntityCatalog::routeRegex())
            ->middleware('permission:manage_inventory,sanctum')
            ->name('entity.update');
        Route::delete('{resource}/{id}', [InventoryEntityRecordController::class, 'destroy'])
            ->where('resource', InventoryEntityCatalog::routeRegex())
            ->middleware('permission:manage_inventory,sanctum')
            ->name('entity.destroy');
        Route::post('goods/{id}/suppliers', [InventoryEntityRecordController::class, 'attachRelation'])
            ->defaults('resource', 'goods')
            ->defaults('relation', 'suppliers')
            ->middleware('permission:manage_inventory,sanctum')
            ->name('goods.suppliers');
        Route::post('routes/{id}/delivery-notes', [InventoryEntityRecordController::class, 'attachRelation'])
            ->defaults('resource', 'routes')
            ->defaults('relation', 'delivery_notes')
            ->middleware('permission:manage_inventory,sanctum')
            ->name('routes.delivery-notes');
        Route::post('routes/{id}/optimize', [InventoryEntityRecordController::class, 'optimizeRoute'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('routes.optimize');
        Route::post('shelf-boxes/{id}/assign', [InventoryEntityRecordController::class, 'assignShelfBox'])
            ->middleware('permission:manage_inventory,sanctum')
            ->name('shelf-boxes.assign');
        Route::get('{resource}/{id}/maintenance-logs', [InventoryEntityRecordController::class, 'logs'])
            ->where('resource', InventoryEntityCatalog::routeRegex())
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('entity.logs.index');
        Route::post('{resource}/{id}/maintenance-logs', [InventoryEntityRecordController::class, 'appendLog'])
            ->where('resource', InventoryEntityCatalog::routeRegex())
            ->middleware('permission:manage_inventory,sanctum')
            ->name('entity.logs.store');

        Route::get('{resource}', [InventoryWorkflowAliasController::class, 'index'])
            ->where('resource', InventoryWorkflowAliasCatalog::routeRegex())
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('workflow.index');
        Route::post('{resource}', [InventoryWorkflowAliasController::class, 'store'])
            ->where('resource', InventoryWorkflowAliasCatalog::routeRegex())
            ->middleware('permission:manage_inventory,sanctum')
            ->name('workflow.store');
        Route::get('{resource}/{id}', [InventoryWorkflowAliasController::class, 'show'])
            ->where('resource', InventoryWorkflowAliasCatalog::routeRegex())
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('workflow.show');
        Route::match(['put', 'patch'], '{resource}/{id}', [InventoryWorkflowAliasController::class, 'update'])
            ->where('resource', InventoryWorkflowAliasCatalog::routeRegex())
            ->middleware('permission:manage_inventory,sanctum')
            ->name('workflow.update');
        Route::post('{resource}/{id}/{action}', [InventoryWorkflowAliasController::class, 'action'])
            ->where('resource', InventoryWorkflowAliasCatalog::routeRegex())
            ->middleware('permission:manage_inventory,sanctum')
            ->name('workflow.action');

        Route::get('files/preview', [InventoryFileController::class, 'preview'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('files.preview');
        Route::get('files/download', [InventoryFileController::class, 'download'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum')
            ->name('files.download');
    });

Route::get('v1/public/inventory/verify/{resource}/{id}', [InventoryPublicVerificationController::class, 'verify'])
    ->where('resource', InventoryWorkflowAliasCatalog::routeRegex())
    ->name('api.public.inventory.verify');
