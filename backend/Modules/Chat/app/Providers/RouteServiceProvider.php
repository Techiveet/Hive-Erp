<?php

namespace Modules\Chat\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->routes(function () {
            Route::middleware(['api', 'auth:sanctum', 'active_status'])
                ->prefix('api')
                ->group(__DIR__.'/../../routes/api.php');
        });
    }
}