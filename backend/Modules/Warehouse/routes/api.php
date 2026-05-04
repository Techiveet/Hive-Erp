<?php

use Illuminate\Support\Facades\Route;
use Modules\Warehouse\Http\Controllers\WarehouseLocationController;
use Modules\Warehouse\Http\Controllers\WarehouseManagementController;
use Modules\Warehouse\Http\Controllers\WarehouseStockController;

Route::middleware(['auth:sanctum', 'active_status', 'dynamic_timeout', 'tenant_module:warehouse_management'])
    ->prefix('v1/warehouse')
    ->group(function () {
        Route::get('warehouses', [WarehouseManagementController::class, 'index'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum');
        Route::post('warehouses', [WarehouseManagementController::class, 'store'])
            ->middleware('permission:manage_inventory,sanctum');
        Route::get('warehouses/{id}', [WarehouseManagementController::class, 'show'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum');
        Route::match(['put', 'patch'], 'warehouses/{id}', [WarehouseManagementController::class, 'update'])
            ->middleware('permission:manage_inventory,sanctum');
        Route::delete('warehouses/{id}', [WarehouseManagementController::class, 'destroy'])
            ->middleware('permission:manage_inventory,sanctum');

        Route::get('locations', [WarehouseLocationController::class, 'index'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum');
        Route::post('locations', [WarehouseLocationController::class, 'store'])
            ->middleware('permission:manage_inventory,sanctum');
        Route::get('locations/{id}', [WarehouseLocationController::class, 'show'])
            ->middleware('permission:view_inventory|manage_inventory,sanctum');
        Route::match(['put', 'patch'], 'locations/{id}', [WarehouseLocationController::class, 'update'])
            ->middleware('permission:manage_inventory,sanctum');
        Route::delete('locations/{id}', [WarehouseLocationController::class, 'destroy'])
            ->middleware('permission:manage_inventory,sanctum');

        Route::prefix('stocks')->group(function () {
            Route::get('/', [WarehouseStockController::class, 'index'])
                ->middleware('permission:view_inventory|manage_inventory,sanctum');
            Route::get('/movements', [WarehouseStockController::class, 'movements'])
                ->middleware('permission:view_inventory|manage_inventory,sanctum');
        });
    });
