<?php

namespace Modules\Subscription\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Subscription\Support\TenantModuleCatalog;
use Modules\Subscription\Support\TenantSubscriptionService;
use Modules\Tenancy\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantModuleEnabled
{
    public function __construct(
        protected TenantSubscriptionService $subscriptions,
    ) {
    }

    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = function_exists('tenant') ? tenant() : null;

        if (!$tenant) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && method_exists($user, 'hasCentralControlOverride') && $user->hasCentralControlOverride()) {
            return $next($request);
        }

        $current = $this->subscriptions->currentForTenant($tenant);
        $status = $current['status'];

        if (in_array($status, ['active', 'grace_period'], true) && TenantModuleCatalog::isModuleActive(
            $current['module_subscriptions'] ?? null,
            $moduleSlug,
            $tenant->plan
        )) {
            return $next($request);
        }

        $catalog = TenantModuleCatalog::catalogMap();
        $module = $catalog[$moduleSlug] ?? ['slug' => $moduleSlug, 'name' => Str::headline($moduleSlug)];

        return response()->json([
            'message' => $status === 'expired'
                ? "The tenant subscription expired on {$current['expires_at']}. Renew it to restore module access."
                : "{$module['name']} is not active for this tenant subscription.",
            'code' => $status === 'expired'
                ? 'TENANT_SUBSCRIPTION_EXPIRED'
                : 'TENANT_MODULE_SUBSCRIPTION_REQUIRED',
            'module' => [
                'slug' => $module['slug'],
                'name' => $module['name'],
                'monthly_price_etb' => (float) ($module['monthly_price_etb'] ?? 0),
            ],
            'subscription' => [
                'status' => $status,
                'expires_at' => $current['expires_at'],
                'grace_ends_at' => $current['grace_ends_at'],
                'needs_renewal' => $current['needs_renewal'],
            ],
        ], 402);
    }
}

