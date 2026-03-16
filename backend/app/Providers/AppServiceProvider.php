<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model; // <-- Add this import
use Stancl\Tenancy\Tenancy;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // This enables ALL strict modes (N+1 prevention, silent discard prevention, etc.)
        // But ONLY on your local machine, never in production.
        Model::shouldBeStrict(! app()->isProduction());
        if (app()->runningInConsole() && config('octane.server') === 'roadrunner') {
        app('events')->listen(\Laravel\Octane\Events\RequestTerminated::class, function () {
            // Forcefully clear the tenant from the worker's RAM
            if (app()->bound(Tenancy::class)) {
                app(Tenancy::class)->end();
            }
        });
    }

    // Implicitly grant "Super Admin" role all permissions
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin', 'sanctum') ? true : null;
        });
    }
}
