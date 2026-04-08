<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // 1. Spatie Permission Aliases
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'active_status' => \App\Http\Middleware\EnsureActiveStatus::class,
            'dynamic_timeout' => \Modules\Core\Http\Middleware\EnforceDynamicSessionTimeout::class,
            'tenant_module' => \Modules\Subscription\Http\Middleware\EnsureTenantModuleEnabled::class,
        ]);

        // 2. Bypass CSRF for API requests
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Initialize tenant context for every API request before auth runs.
        // The middleware safely no-ops on central domains.
        $middleware->api(prepend: [
            \App\Http\Middleware\InitializeTenantContext::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
