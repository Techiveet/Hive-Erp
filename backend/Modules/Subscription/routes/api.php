<?php

use App\Http\Middleware\InitializeTenantContext;
use Illuminate\Support\Facades\Route;
use Modules\Subscription\Http\Controllers\TenantSubscriptionCheckoutController;
use Modules\Subscription\Http\Controllers\TenantSubscriptionController;
use Modules\Subscription\Http\Controllers\SubscriptionAdminController;

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

foreach ($centralDomains as $domain) {
    Route::domain($domain)->prefix('v1')->group(function () {
        Route::prefix('public/subscriptions')->group(function () {
            Route::get('/catalog', [TenantSubscriptionCheckoutController::class, 'publicCatalog']);
            Route::post('/checkout', [TenantSubscriptionCheckoutController::class, 'publicCheckout']);
            Route::get('/orders/{token}', [TenantSubscriptionCheckoutController::class, 'publicOrderStatus']);
            Route::match(['get', 'post'], '/orders/{token}/notify', [TenantSubscriptionCheckoutController::class, 'publicNotify']);
        });

        Route::prefix('public/demo-requests')->group(function () {
            Route::post('/', [Modules\Subscription\Http\Controllers\DemoRequestController::class, 'store']);
        });

        Route::middleware(['auth:sanctum', 'active_status', 'dynamic_timeout'])->group(function () {
            Route::prefix('subscriptions')->group(function () {
                Route::get('/catalog', [TenantSubscriptionController::class, 'catalog'])
                    ->middleware('permission:view_tenants|manage_tenants,sanctum');
                Route::get('/admin', [SubscriptionAdminController::class, 'index'])
                    ->middleware('permission:view_tenants|manage_tenants,sanctum');
                Route::put('/admin/plans', [SubscriptionAdminController::class, 'updatePlans'])
                    ->middleware('permission:manage_tenants|provision_tenants,sanctum');
                Route::put('/admin/pricing', [SubscriptionAdminController::class, 'updatePricing'])
                    ->middleware('permission:manage_tenants|provision_tenants,sanctum');
                Route::put('/admin/tenants/{tenantId}', [SubscriptionAdminController::class, 'assignTenant'])
                    ->middleware('permission:manage_tenants|provision_tenants,sanctum');
            });
        });
    });
}

Route::prefix('v1')->middleware(['auth:sanctum', 'active_status', 'dynamic_timeout'])->group(function () {
    Route::prefix('subscriptions')->group(function () {
        Route::get('/admin', [SubscriptionAdminController::class, 'index'])
            ->middleware('permission:view_tenants|manage_tenants,sanctum');
        Route::put('/admin/plans', [SubscriptionAdminController::class, 'updatePlans'])
            ->middleware('permission:manage_tenants|provision_tenants,sanctum');
        Route::put('/admin/pricing', [SubscriptionAdminController::class, 'updatePricing'])
            ->middleware('permission:manage_tenants|provision_tenants,sanctum');
        Route::put('/admin/tenants/{tenantId}', [SubscriptionAdminController::class, 'assignTenant'])
            ->middleware('permission:manage_tenants|provision_tenants,sanctum');
    });
});

Route::middleware([
    InitializeTenantContext::class,
])->prefix('v1')->group(function () {
    Route::prefix('public/subscriptions')->group(function () {
        Route::get('/catalog', [TenantSubscriptionCheckoutController::class, 'publicCatalog']);
        Route::get('/orders/{token}', [TenantSubscriptionCheckoutController::class, 'publicOrderStatus']);
        Route::match(['get', 'post'], '/orders/{token}/notify', [TenantSubscriptionCheckoutController::class, 'publicNotify']);
    });

    Route::middleware(['auth:sanctum', 'active_status', 'dynamic_timeout'])->group(function () {
        Route::prefix('subscriptions')->group(function () {
            Route::get('/catalog', [TenantSubscriptionController::class, 'catalog'])
                ->middleware('permission:view_module_subscriptions|manage_module_subscriptions,sanctum');
            Route::get('/current', [TenantSubscriptionController::class, 'current'])
                ->middleware('permission:view_module_subscriptions|manage_module_subscriptions,sanctum');
            Route::put('/current', [TenantSubscriptionController::class, 'updateCurrent'])
                ->middleware('permission:manage_module_subscriptions,sanctum');
            Route::post('/current/checkout', [TenantSubscriptionCheckoutController::class, 'startCurrentCheckout'])
                ->middleware('permission:manage_module_subscriptions,sanctum');
            Route::post('/current/renewal', [TenantSubscriptionCheckoutController::class, 'startCurrentRenewalCheckout'])
                ->middleware('permission:manage_module_subscriptions,sanctum');
            Route::post('/current/checkout/{token}/sync', [TenantSubscriptionCheckoutController::class, 'syncCurrentCheckout'])
                ->middleware('permission:manage_module_subscriptions,sanctum');
        });

        Route::prefix('tenant/subscriptions')->group(function () {
            Route::get('/catalog', [TenantSubscriptionController::class, 'catalog'])
                ->middleware('permission:view_module_subscriptions|manage_module_subscriptions,sanctum');
            Route::get('/current', [TenantSubscriptionController::class, 'current'])
                ->middleware('permission:view_module_subscriptions|manage_module_subscriptions,sanctum');
            Route::put('/current', [TenantSubscriptionController::class, 'updateCurrent'])
                ->middleware('permission:manage_module_subscriptions,sanctum');
            Route::post('/current/checkout', [TenantSubscriptionCheckoutController::class, 'startCurrentCheckout'])
                ->middleware('permission:manage_module_subscriptions,sanctum');
            Route::post('/current/renewal', [TenantSubscriptionCheckoutController::class, 'startCurrentRenewalCheckout'])
                ->middleware('permission:manage_module_subscriptions,sanctum');
            Route::post('/current/checkout/{token}/sync', [TenantSubscriptionCheckoutController::class, 'syncCurrentCheckout'])
                ->middleware('permission:manage_module_subscriptions,sanctum');
        });
    });
});
