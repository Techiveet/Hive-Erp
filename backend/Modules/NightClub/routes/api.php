<?php

use Illuminate\Support\Facades\Route;
use Modules\NightClub\Http\Controllers\NightClubController;
use Modules\NightClub\Http\Controllers\PublicReservationController;
use Modules\NightClub\Http\Controllers\ReservationController;
use Modules\NightClub\Http\Controllers\ServiceOrderController;
use Modules\NightClub\Http\Controllers\TableController;

Route::prefix('v1/public/nightclub')->name('api.public.nightclub.')->group(function (): void {
    Route::get('available-tables', [PublicReservationController::class, 'availableTables'])->name('tables');
    Route::post('reserve', [PublicReservationController::class, 'store'])->name('reserve');
});

Route::middleware(['auth:sanctum', 'active_status', 'dynamic_timeout', 'tenant_module:lounge_club_management'])
    ->prefix('v1/nightclub')
    ->name('api.nightclub.')
    ->group(function (): void {
        Route::get('overview', [NightClubController::class, 'overview'])
            ->middleware('permission:view_nightclub_tables|view_nightclub_reservations,sanctum')
            ->name('overview');

        Route::get('tables', [TableController::class, 'index'])
            ->middleware('permission:view_nightclub_tables,sanctum')
            ->name('tables.index');
        Route::post('tables', [TableController::class, 'store'])
            ->middleware('permission:create_nightclub_tables,sanctum')
            ->name('tables.store');
        Route::get('tables/{id}', [TableController::class, 'show'])
            ->middleware('permission:view_nightclub_tables,sanctum')
            ->name('tables.show');
        Route::match(['put', 'patch'], 'tables/{id}', [TableController::class, 'update'])
            ->middleware('permission:edit_nightclub_tables,sanctum')
            ->name('tables.update');
        Route::delete('tables/{id}', [TableController::class, 'destroy'])
            ->middleware('permission:delete_nightclub_tables,sanctum')
            ->name('tables.destroy');

        Route::get('reservations', [ReservationController::class, 'index'])
            ->middleware('permission:view_nightclub_reservations,sanctum')
            ->name('reservations.index');
        Route::post('reservations', [ReservationController::class, 'store'])
            ->middleware('permission:create_nightclub_reservations,sanctum')
            ->name('reservations.store');
        Route::get('reservations/{id}', [ReservationController::class, 'show'])
            ->middleware('permission:view_nightclub_reservations,sanctum')
            ->name('reservations.show');
        Route::match(['put', 'patch'], 'reservations/{id}', [ReservationController::class, 'update'])
            ->middleware('permission:edit_nightclub_reservations|confirm_nightclub_reservations|complete_nightclub_reservations,sanctum')
            ->name('reservations.update');
        Route::delete('reservations/{id}', [ReservationController::class, 'destroy'])
            ->middleware('permission:delete_nightclub_reservations,sanctum')
            ->name('reservations.destroy');

        Route::get('service-orders', [ServiceOrderController::class, 'index'])
            ->middleware('permission:view_nightclub_service_orders|view_nightclub_reservations,sanctum')
            ->name('service-orders.index');
        Route::post('service-orders', [ServiceOrderController::class, 'store'])
            ->middleware('permission:create_nightclub_service_orders|create_nightclub_reservations,sanctum')
            ->name('service-orders.store');
        Route::get('service-orders/{id}', [ServiceOrderController::class, 'show'])
            ->middleware('permission:view_nightclub_service_orders|view_nightclub_reservations,sanctum')
            ->name('service-orders.show');
        Route::match(['put', 'patch'], 'service-orders/{id}', [ServiceOrderController::class, 'update'])
            ->middleware('permission:edit_nightclub_service_orders|edit_nightclub_reservations,sanctum')
            ->name('service-orders.update');
        Route::post('service-orders/{id}/close', [ServiceOrderController::class, 'close'])
            ->middleware('permission:close_nightclub_service_orders|complete_nightclub_reservations,sanctum')
            ->name('service-orders.close');
        Route::delete('service-orders/{id}', [ServiceOrderController::class, 'destroy'])
            ->middleware('permission:edit_nightclub_service_orders|delete_nightclub_reservations,sanctum')
            ->name('service-orders.destroy');
    });
