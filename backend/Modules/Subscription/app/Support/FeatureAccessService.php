<?php

namespace Modules\Subscription\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Subscription\Models\SubscriptionFeature;
use Modules\Subscription\Models\TenantFeatureOverride;
use Modules\Tenancy\Models\Tenant;

class FeatureAccessService
{
    public const ACTIVE_STATUSES = ['active', 'trial', 'grace_period'];

    public function __construct(
        protected TenantSubscriptionService $subscriptions,
        protected SubscriptionFeatureMap $featureMap,
    ) {
    }

    public function checkRequest(Tenant $tenant, Request $request): array
    {
        $current = $this->subscriptions->currentForTenant($tenant);
        $status = $current['status'];

        if (!in_array($status, self::ACTIVE_STATUSES, true)) {
            return [
                'allowed' => $this->isRenewalPath($request),
                'reason' => 'subscription_status',
                'status' => $status,
                'subscription' => $current,
            ];
        }

        $feature = $this->featureMap->featureForRequestPath($request->path());

        if (!$feature) {
            return [
                'allowed' => true,
                'reason' => 'unmapped',
                'status' => $status,
                'subscription' => $current,
            ];
        }

        $moduleSlug = (string) ($feature['module_slug'] ?? '');
        $moduleSlugs = collect($feature['module_slugs'] ?? [$moduleSlug])
            ->map(fn (string $slug) => trim($slug))
            ->filter()
            ->values()
            ->all();
        $moduleAllowed = collect($moduleSlugs)->contains(fn (string $slug) => TenantModuleCatalog::isModuleActive(
            $current['module_subscriptions'] ?? null,
            $slug,
            $tenant->plan
        ));

        $featureRecord = SubscriptionFeature::query()
            ->where('route_uri', trim($request->path(), '/'))
            ->orWhere('module_gate', $moduleSlug)
            ->orderByRaw('route_uri = ? desc', [trim($request->path(), '/')])
            ->first();

        $override = $featureRecord ? $this->activeOverride($tenant, $featureRecord) : null;

        if ($override?->status === 'deny') {
            return [
                'allowed' => false,
                'reason' => 'tenant_feature_denied',
                'status' => $status,
                'module' => $moduleSlug,
                'feature' => $feature,
                'subscription' => $current,
            ];
        }

        if ($override?->status === 'allow') {
            return [
                'allowed' => true,
                'reason' => 'tenant_feature_override',
                'status' => $status,
                'module' => $moduleSlug,
                'feature' => $feature,
                'subscription' => $current,
            ];
        }

        return [
            'allowed' => $moduleAllowed,
            'reason' => $moduleAllowed ? 'module_active' : 'module_required',
            'status' => $status,
            'module' => $moduleSlug,
            'feature' => $feature,
            'subscription' => $current,
        ];
    }

    public function hasModule(?Tenant $tenant, string $moduleSlug): bool
    {
        if (!$tenant) {
            return true;
        }

        $current = $this->subscriptions->currentForTenant($tenant);

        return in_array($current['status'], self::ACTIVE_STATUSES, true)
            && TenantModuleCatalog::isModuleActive($current['module_subscriptions'] ?? null, $moduleSlug, $tenant->plan);
    }

    public function hasFeature(?Tenant $tenant, string $featureSlug): bool
    {
        if (!$tenant) {
            return true;
        }

        $feature = SubscriptionFeature::query()
            ->with('module')
            ->where('slug', Str::slug($featureSlug))
            ->first();

        if (!$feature) {
            return false;
        }

        $override = $this->activeOverride($tenant, $feature);
        if ($override?->status === 'deny') {
            return false;
        }
        if ($override?->status === 'allow') {
            return true;
        }

        return $this->hasModule($tenant, (string) $feature->module?->slug);
    }

    private function activeOverride(Tenant $tenant, SubscriptionFeature $feature): ?TenantFeatureOverride
    {
        $now = now();

        return TenantFeatureOverride::query()
            ->where('tenant_id', $tenant->id)
            ->where('subscription_feature_id', $feature->id)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->first();
    }

    private function isRenewalPath(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return str_starts_with($path, 'api/v1/subscriptions')
            || str_starts_with($path, 'api/v1/tenant/subscriptions')
            || str_starts_with($path, 'api/v1/public/subscriptions')
            || in_array($path, [
                'api/v1/user',
                'api/v1/tenant/user',
                'api/v1/logout',
                'api/v1/tenant/logout',
                'api/v1/ping',
                'api/v1/tenant/ping',
            ], true);
    }
}
