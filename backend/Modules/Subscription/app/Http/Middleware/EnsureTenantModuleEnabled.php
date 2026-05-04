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

        $moduleSlugs = collect(preg_split('/[|,]/', $moduleSlug) ?: [])
            ->map(fn (string $slug) => trim($slug))
            ->filter()
            ->values()
            ->all();

        if ($moduleSlugs === []) {
            $moduleSlugs = [$moduleSlug];
        }

        if (in_array($status, TenantSubscriptionService::ACCESS_STATUSES, true)) {
            foreach ($moduleSlugs as $candidateSlug) {
                if (TenantModuleCatalog::isModuleActive(
                    $current['module_subscriptions'] ?? null,
                    $candidateSlug,
                    $tenant->plan
                )) {
                    return $next($request);
                }
            }
        }

        $catalog = TenantModuleCatalog::catalogMap();
        $primaryModuleSlug = $moduleSlugs[0] ?? $moduleSlug;
        $module = $catalog[$primaryModuleSlug] ?? ['slug' => $primaryModuleSlug, 'name' => Str::headline($primaryModuleSlug)];
        $moduleNames = collect($moduleSlugs)
            ->map(fn (string $slug) => $catalog[$slug]['name'] ?? Str::headline($slug))
            ->join(', ', ' or ');

        return response()->json([
            'message' => $status === 'expired'
                ? "The tenant subscription expired on {$current['expires_at']}. Renew it to restore module access."
                : "{$moduleNames} is not active for this tenant subscription.",
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

