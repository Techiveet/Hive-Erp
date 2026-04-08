<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model; // <-- Add this import
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

        // Implicitly grant "Super Admin" role all permissions across central,
        // tenant, and Sanctum API guard contexts.
        Gate::before(function ($user, $ability) {
            return $this->hasSuperAdminRole($user) ? true : null;
        });
    }

    protected function hasSuperAdminRole(mixed $user): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        $guards = array_values(array_unique(array_filter([
            config('auth.defaults.guard'),
            $user->guard_name ?? null,
            'web',
            'tenant',
            'sanctum',
        ])));

        foreach ($guards as $guard) {
            try {
                if ($user->hasRole('Super Admin', $guard)) {
                    return true;
                }
            } catch (\Throwable) {
                // Keep checking other guards; Spatie throws when a role belongs
                // to a different guard than the one being tested.
            }
        }

        try {
            return $user->hasRole('Super Admin');
        } catch (\Throwable) {
            return false;
        }
    }
}
