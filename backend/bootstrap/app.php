<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 1. ✅ SANCTUM STATEFUL AUTH
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // 2. ✅ PROXY & CORS CONFIG
        // Set to '*' for Render to correctly handle HTTPS termination and tenant headers
        $middleware->trustProxies(at: '*');

        // 3. ✅ ALIASES FOR PERMISSIONS
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 4. ✅ CUSTOM TENANT ERROR RENDERING
        $exceptions->render(function (TenantCouldNotBeIdentifiedOnDomainException $e, Request $request) {
            $host = $request->getHost();
            $subdomain = explode('.', $host)[0];

            return response()->json([
                'status' => 'error',
                'meta' => [
                    'node' => gethostname(),
                    'timestamp' => now()->toIso8601String(),
                ],
                'message' => "The Hive workspace '{$subdomain}' is not initialized or does not exist."
            ], 404);
        });
    })->create();
