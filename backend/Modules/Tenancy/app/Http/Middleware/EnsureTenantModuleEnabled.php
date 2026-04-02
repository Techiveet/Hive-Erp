<?php

namespace Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantModuleCatalog;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantModuleEnabled
{
    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = function_exists('tenant') ? tenant() : null;

        if (!$tenant) {
            return $next($request);
        }

        if (TenantModuleCatalog::isModuleActive(
            is_array($tenant->module_subscriptions) ? $tenant->module_subscriptions : null,
            $moduleSlug,
            $tenant->plan
        )) {
            return $next($request);
        }

        $catalog = TenantModuleCatalog::catalogMap();
        $module = $catalog[$moduleSlug] ?? ['slug' => $moduleSlug, 'name' => Str::headline($moduleSlug)];

        return response()->json([
            'message' => "{$module['name']} is not active for this tenant subscription.",
            'code' => 'TENANT_MODULE_SUBSCRIPTION_REQUIRED',
            'module' => [
                'slug' => $module['slug'],
                'name' => $module['name'],
                'monthly_price_etb' => (float) ($module['monthly_price_etb'] ?? 0),
            ],
        ], 402);
    }
}
