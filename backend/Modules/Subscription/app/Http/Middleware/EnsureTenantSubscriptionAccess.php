<?php

namespace Modules\Subscription\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Subscription\Support\FeatureAccessService;
use Modules\Subscription\Support\TenantModuleCatalog;
use Modules\Tenancy\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionAccess
{
    public function __construct(
        protected FeatureAccessService $features,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = function_exists('tenant') ? tenant() : null;

        if (!$tenant || !$request->user()) {
            return $next($request);
        }

        $user = $request->user();
        if (method_exists($user, 'hasCentralControlOverride') && $user->hasCentralControlOverride()) {
            return $next($request);
        }

        $result = $this->features->checkRequest($tenant, $request);

        if ($result['allowed']) {
            return $next($request);
        }

        $catalog = TenantModuleCatalog::catalogMap();
        $moduleSlug = (string) ($result['module'] ?? '');
        $module = $catalog[$moduleSlug] ?? ['slug' => $moduleSlug, 'name' => Str::headline($moduleSlug)];
        $status = (string) ($result['status'] ?? 'inactive');
        $statusReason = $result['reason'] === 'subscription_status';

        return response()->json([
            'message' => $statusReason
                ? 'This tenant subscription is not active. Renew or reactivate it to continue.'
                : "{$module['name']} requires an active subscription for this tenant.",
            'code' => $statusReason
                ? 'TENANT_SUBSCRIPTION_NOT_ACTIVE'
                : 'TENANT_FEATURE_SUBSCRIPTION_REQUIRED',
            'module' => [
                'slug' => $module['slug'] ?? $moduleSlug,
                'name' => $module['name'] ?? Str::headline($moduleSlug),
                'monthly_price_etb' => (float) ($module['monthly_price_etb'] ?? 0),
            ],
            'feature' => $result['feature'] ?? null,
            'subscription' => [
                'status' => $status,
                'expires_at' => $result['subscription']['expires_at'] ?? null,
                'grace_ends_at' => $result['subscription']['grace_ends_at'] ?? null,
                'needs_renewal' => $result['subscription']['needs_renewal'] ?? true,
            ],
        ], 402);
    }
}
