<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
        JsonResource::withoutWrapping();

        // This enables ALL strict modes (N+1 prevention, silent discard prevention, etc.)
        // But ONLY on your local machine, never in production.
        Model::shouldBeStrict(! app()->isProduction());

        // Implicitly grant "Super Admin" role all permissions across central,
        // tenant, and Sanctum API guard contexts.
        Gate::before(function ($user, $ability) {
            return $this->hasSuperAdminRole($user) ? true : null;
        });

        RateLimiter::for('auth-login', function (Request $request) {
            $email = strtolower((string) $request->input('email', 'unknown'));

            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(5)->by('login:'.$email),
            ];
        });

        RateLimiter::for('auth-2fa', function (Request $request) {
            $email = strtolower((string) $request->input('email', 'unknown'));

            return [
                Limit::perMinute(12)->by($request->ip()),
                Limit::perMinute(6)->by('2fa:'.$email),
            ];
        });

        RateLimiter::for('auth-password-reset', function (Request $request) {
            $email = strtolower((string) $request->input('email', 'unknown'));

            return [
                Limit::perMinute(6)->by($request->ip()),
                Limit::perMinute(3)->by('password-reset:'.$email),
            ];
        });
    }

    protected function hasSuperAdminRole(mixed $user): bool
    {
        if (! is_object($user)) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin')) {
            return $user->isSuperAdmin();
        }

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
