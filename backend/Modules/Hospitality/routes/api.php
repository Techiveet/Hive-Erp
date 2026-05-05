<?php

use Illuminate\Support\Facades\Route;
use Modules\Hospitality\Http\Controllers\HospitalityDashboardController;
use Modules\Hospitality\Http\Controllers\PublicReservationController;
use Modules\Hospitality\Http\Controllers\ReservationController;
use Modules\Hospitality\Http\Controllers\ServiceOrderController;
use Modules\Hospitality\Http\Controllers\TableController;
use Modules\Hospitality\Http\Controllers\MenuItemController;
use Modules\Hospitality\Http\Controllers\EventController;
use Modules\Hospitality\Http\Controllers\WaitlistController;
use Modules\Hospitality\Http\Controllers\CustomerProfileController;
use Modules\Hospitality\Http\Controllers\StaffShiftController;
use Modules\Hospitality\Http\Controllers\FeedbackController;
use Modules\Hospitality\Http\Controllers\BillSplitController;

Route::prefix('v1/public/hospitality')->name('api.public.hospitality.')->group(function (): void {
    Route::get('available-tables', [PublicReservationController::class, 'availableTables'])->name('tables');
    Route::post('reserve', [PublicReservationController::class, 'store'])->name('reserve');
});

Route::middleware([
    \App\Http\Middleware\InitializeTenantContext::class,
    'auth:sanctum',
    'active_status',
    'dynamic_timeout',
    'tenant_module:hospitality',
])
    ->prefix('v1/hospitality')
    ->name('api.hospitality.')
    ->group(function (): void {
        Route::get('overview', [HospitalityDashboardController::class, 'overview'])
            ->name('overview');

        // Space & Staff Management
        Route::prefix('space')->group(function () {
            Route::get('zones', [\Modules\Hospitality\Http\Controllers\SpaceManagementController::class, 'getFloorPlan']);
            Route::patch('locations/{id}/status', [\Modules\Hospitality\Http\Controllers\SpaceManagementController::class, 'updateLocationStatus']);
        });

        // Door & Promoter Management
        Route::prefix('door')->group(function () {
            Route::get('guest-list', [\Modules\Hospitality\Http\Controllers\DoorManagementController::class, 'getGuestList']);
            Route::post('check-in/{id}', [\Modules\Hospitality\Http\Controllers\DoorManagementController::class, 'checkIn']);
        });

        Route::get('tables', [TableController::class, 'index'])
            ->name('tables.index');
        Route::post('tables', [TableController::class, 'store'])
            ->name('tables.store');
        Route::get('tables/{id}', [TableController::class, 'show'])
            ->name('tables.show');
        Route::match(['put', 'patch'], 'tables/{id}', [TableController::class, 'update'])
            ->name('tables.update');
        Route::delete('tables/{id}', [TableController::class, 'destroy'])
            ->name('tables.destroy');

        Route::get('reservations', [ReservationController::class, 'index'])
            ->name('reservations.index');
        Route::post('reservations', [ReservationController::class, 'store'])
            ->name('reservations.store');
        Route::get('reservations/{id}', [ReservationController::class, 'show'])
            ->name('reservations.show');
        Route::match(['put', 'patch'], 'reservations/{id}', [ReservationController::class, 'update'])
            ->name('reservations.update');
        Route::delete('reservations/{id}', [ReservationController::class, 'destroy'])
            ->name('reservations.destroy');

        Route::get('service-orders', [ServiceOrderController::class, 'index'])
            ->name('service-orders.index');
        Route::post('service-orders', [ServiceOrderController::class, 'store'])
            ->name('service-orders.store');
        Route::get('service-orders/{id}', [ServiceOrderController::class, 'show'])
            ->name('service-orders.show');
        Route::match(['put', 'patch'], 'service-orders/{id}', [ServiceOrderController::class, 'update'])
            ->name('service-orders.update');
        Route::post('service-orders/{id}/close', [ServiceOrderController::class, 'close'])
            ->name('service-orders.close');
        Route::delete('service-orders/{id}', [ServiceOrderController::class, 'destroy'])
            ->name('service-orders.destroy');

        Route::apiResource('menu-items', MenuItemController::class);
        Route::get('menu-categories', [MenuItemController::class, 'categories'])
            ->name('menu.categories');

        Route::apiResource('events', EventController::class);
        Route::post('events/{id}/block-tables', [EventController::class, 'blockTables'])
            ->name('events.block-tables');
        Route::post('events/{id}/unblock-tables', [EventController::class, 'unblockTables'])
            ->name('events.unblock-tables');

        Route::apiResource('waitlist', WaitlistController::class);
        Route::post('waitlist/{id}/seat', [WaitlistController::class, 'seat'])
            ->name('waitlist.seat');

        Route::apiResource('customers', CustomerProfileController::class);
        Route::get('customers/{id}/history', [CustomerProfileController::class, 'history'])
            ->name('customers.history');

        Route::apiResource('staff-shifts', StaffShiftController::class);

        Route::apiResource('feedback', FeedbackController::class);

        Route::post('service-orders/{id}/split-bill', [BillSplitController::class, 'store'])
            ->name('bills.split');
        Route::get('service-orders/{id}/bills', [BillSplitController::class, 'index'])
            ->name('bills.index');
    });
