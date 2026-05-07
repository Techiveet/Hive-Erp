<?php

namespace Modules\Subscription\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Subscription\Models\TenantSubscription;
use Modules\Tenancy\Models\Tenant;

class TenantSubscriptionService
{
    public const ACCESS_STATUSES = ['active', 'trial', 'grace_period'];
    public const MANUAL_STATUSES = ['trial', 'inactive', 'expired', 'cancelled', 'suspended', 'pending_activation'];

    public function ensureForTenant(Tenant $tenant, ?array $requestedPayload = null, ?string $updatedBy = null, bool $resetBillingWindow = false): TenantSubscription
    {
        $subscription = TenantSubscription::query()->firstOrNew([
            'tenant_id' => $tenant->id,
        ]);

        $plan = strtolower((string) ($tenant->plan ?: $subscription->plan ?: 'business'));
        $existingPayload = is_array($subscription->module_subscriptions) ? $subscription->module_subscriptions : null;
        $legacyPayload = is_array($tenant->module_subscriptions) ? $tenant->module_subscriptions : null;
        $seedPayload = $requestedPayload ?? $existingPayload ?? $legacyPayload;

        if (!$subscription->id) {
            $subscription->id = (string) Str::ulid();
        }

        $subscription->plan = $plan;
        $subscription->billing_cycle = 'monthly';
        $subscription->renewal_mode = 'manual';
        $subscription->updated_by = $updatedBy ?? $subscription->updated_by;

        if ($requestedPayload !== null || $existingPayload === null) {
            $subscription->module_subscriptions = TenantModuleCatalog::normalizeForStorage(
                $seedPayload,
                $plan,
                $updatedBy ?? $tenant->admin_email,
                $tenant->business_type
            );
        } elseif ($existingPayload !== null) {
            $subscription->module_subscriptions = $this->preserveStoredPayload($existingPayload, $plan, $updatedBy, $tenant->business_type);
        }

        if ($resetBillingWindow || !$subscription->started_at || !$subscription->expires_at) {
            $window = SubscriptionLifecycle::startWindow($plan, $tenant->created_at ?? now());
            $subscription->started_at = $window['started_at'];
            $subscription->renewal_window_starts_at = $window['renewal_window_starts_at'];
            $subscription->expires_at = $window['expires_at'];
            $subscription->grace_ends_at = $window['grace_ends_at'];
            $subscription->last_renewed_at = $subscription->last_renewed_at ?? $subscription->started_at;
        } else {
            $window = SubscriptionLifecycle::windowForExpiry($plan, $subscription->expires_at);
            $subscription->renewal_window_starts_at = $window['renewal_window_starts_at'];
            $subscription->grace_ends_at = $window['grace_ends_at'];
        }

        if (!in_array((string) $subscription->status, self::MANUAL_STATUSES, true)) {
            $subscription->status = SubscriptionLifecycle::statusFor(
                $subscription->expires_at,
                $subscription->grace_ends_at
            );
        }

        $subscription->save();

        return $subscription->refresh();
    }

    public function updateModules(Tenant $tenant, ?array $payload, ?string $updatedBy = null): TenantSubscription
    {
        // Pass the payload to ensureForTenant so it properly updates module_subscriptions
        // instead of preserving the existing payload
        $subscription = $this->ensureForTenant($tenant, $payload, $updatedBy);
        $subscription->updated_by = $updatedBy;
        
        // Only save if module_subscriptions was actually changed
        if ($subscription->isDirty('module_subscriptions')) {
            $subscription->save();
        }

        return $subscription->refresh();
    }

    public function appendModules(Tenant $tenant, array $moduleSlugs, ?string $updatedBy = null): TenantSubscription
    {
        $subscription = $this->ensureForTenant($tenant, null, $updatedBy);
        $resolved = TenantModuleCatalog::resolve(
            is_array($subscription->module_subscriptions) ? $subscription->module_subscriptions : null,
            $subscription->plan,
            [],
            $tenant->business_type
        );

        $subscription->module_subscriptions = TenantModuleCatalog::normalizeForStorage([
            'enabled_modules' => collect($resolved['enabled_modules'])
                ->concat(TenantModuleCatalog::normalizeRequestedModules($moduleSlugs))
                ->unique()
                ->values()
                ->all(),
            'custom_modules' => $resolved['custom_modules'],
        ], $subscription->plan, $updatedBy, $tenant->business_type);
        $subscription->updated_by = $updatedBy;
        $subscription->save();

        return $subscription->refresh();
    }

    public function syncPlanDefaultsToTenants(array $planKeys, ?string $updatedBy = null): int
    {
        $normalizedPlans = collect($planKeys)
            ->map(fn (string $plan) => strtolower(trim($plan)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($normalizedPlans === []) {
            return 0;
        }

        $synced = 0;

        Tenant::query()
            ->whereIn('plan', $normalizedPlans)
            ->chunk(100, function ($tenants) use (&$synced, $updatedBy) {
                foreach ($tenants as $tenant) {
                    $subscription = $this->ensureForTenant($tenant, null, $updatedBy);
                    $subscription->module_subscriptions = TenantModuleCatalog::normalizeForStorage(
                        null,
                        $subscription->plan,
                        $updatedBy,
                        $tenant->business_type
                    );
                    $subscription->updated_by = $updatedBy;
                    $subscription->save();
                    $synced++;
                }
            });

        return $synced;
    }

    public function renew(Tenant $tenant, Carbon|string|null $paidAt = null, ?string $updatedBy = null): TenantSubscription
    {
        $subscription = $this->ensureForTenant($tenant, null, $updatedBy);
        $window = SubscriptionLifecycle::renewWindow($subscription->plan, $subscription->expires_at, $paidAt ?? now());

        $subscription->last_renewed_at = $window['last_renewed_at'];
        $subscription->renewal_window_starts_at = $window['renewal_window_starts_at'];
        $subscription->expires_at = $window['expires_at'];
        $subscription->grace_ends_at = $window['grace_ends_at'];
        $subscription->status = 'active';
        $subscription->renewal_reminder_sent_at = null;
        $subscription->grace_reminder_sent_at = null;
        $subscription->expired_notice_sent_at = null;
        $subscription->updated_by = $updatedBy;
        $subscription->save();

        return $subscription->refresh();
    }

    public function refreshState(TenantSubscription $subscription, Carbon|string|null $at = null): TenantSubscription
    {
        if (in_array((string) $subscription->status, self::MANUAL_STATUSES, true)) {
            return $subscription;
        }

        $moment = $at instanceof Carbon ? $at : Carbon::parse($at ?? now());
        $status = SubscriptionLifecycle::statusFor($subscription->expires_at, $subscription->grace_ends_at, $moment);

        if ($subscription->status !== $status) {
            $subscription->status = $status;
            $subscription->save();
        }

        return $subscription;
    }

    public function assignPlan(Tenant $tenant, string $plan, ?array $payload = null, ?string $updatedBy = null, bool $resetBillingWindow = true): TenantSubscription
    {
        $tenant->plan = strtolower($plan);
        $tenant->save();

        return $this->ensureForTenant($tenant->refresh(), $payload, $updatedBy, $resetBillingWindow);
    }

    public function setStatus(Tenant $tenant, string $status, ?string $updatedBy = null): TenantSubscription
    {
        $subscription = $this->ensureForTenant($tenant, null, $updatedBy);
        $subscription->status = $status;
        $subscription->updated_by = $updatedBy;

        if ($status === 'active' && (!$subscription->expires_at || $subscription->expires_at->isPast())) {
            $window = SubscriptionLifecycle::startWindow($subscription->plan, now());
            $subscription->started_at = $window['started_at'];
            $subscription->renewal_window_starts_at = $window['renewal_window_starts_at'];
            $subscription->expires_at = $window['expires_at'];
            $subscription->grace_ends_at = $window['grace_ends_at'];
            $subscription->last_renewed_at = $window['started_at'];
        }

        $subscription->save();

        return $subscription->refresh();
    }

    public function currentForTenant(Tenant $tenant, array $pendingModules = []): array
    {
        $subscription = $this->refreshState($this->ensureForTenant($tenant));
        return $this->buildSubscriptionSnapshot($tenant, $subscription, $pendingModules);
    }

    public function currentSnapshotForTenant(Tenant $tenant, array $pendingModules = []): array
    {
        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $subscription) {
            $plan = strtolower((string) ($tenant->plan ?: 'business'));
            $window = SubscriptionLifecycle::startWindow($plan, $tenant->created_at ?? now());
            $summary = SubscriptionLifecycle::summary($window['expires_at'], $window['grace_ends_at']);

            return [
                'id' => null,
                'tenant_id' => $tenant->id,
                'plan' => $plan,
                'status' => $summary['status'],
                'billing_cycle' => 'monthly',
                'renewal_mode' => 'manual',
                'started_at' => optional($window['started_at'])->toIso8601String(),
                'renewal_window_starts_at' => optional($window['renewal_window_starts_at'])->toIso8601String(),
                'expires_at' => optional($window['expires_at'])->toIso8601String(),
                'grace_ends_at' => optional($window['grace_ends_at'])->toIso8601String(),
                'last_renewed_at' => optional($window['started_at'])->toIso8601String(),
                'days_until_expiration' => $summary['days_until_expiration'],
                'is_expiring_soon' => $summary['is_expiring_soon'],
                'needs_renewal' => $summary['needs_renewal'],
                'term_days' => SubscriptionLifecycle::termDays($plan),
                'grace_period_days' => SubscriptionLifecycle::gracePeriodDays($plan),
                'module_subscriptions' => TenantModuleCatalog::resolve(
                    null,
                    $plan,
                    $pendingModules,
                    $tenant->business_type
                ),
            ];
        }

        return $this->buildSubscriptionSnapshot($tenant, $subscription, $pendingModules);
    }

    public function buildModuleAccess(Tenant $tenant): array
    {
        $current = $this->currentForTenant($tenant);
        $subscriptionAllowsAccess = in_array($current['status'], self::ACCESS_STATUSES, true);
        
        $baseAccess = TenantModuleCatalog::buildModuleAccess(
            $current['module_subscriptions'],
            $current['plan']
        );

        return array_merge($baseAccess, [
            'subscription_status' => $current['status'],
            'expires_at' => $current['expires_at'],
            'grace_ends_at' => $current['grace_ends_at'],
            'needs_renewal' => $current['needs_renewal'],
            // Re-apply subscription allows access logic to the active status
            'statuses' => collect($baseAccess['statuses'])
                ->map(fn ($status) => array_merge($status, [
                    'active' => ($baseAccess['bypass_checks'] ?? false) || ($status['active'] && $subscriptionAllowsAccess)
                ]))
                ->all(),
            'active_modules' => (($baseAccess['bypass_checks'] ?? false) || $subscriptionAllowsAccess) ? $baseAccess['active_modules'] : [],
        ]);
    }

    protected function preserveStoredPayload(?array $payload, ?string $plan = null, ?string $updatedBy = null, ?string $businessType = null): array
    {
        $currentVersion = TenantModuleCatalog::VERSION;
        $storedVersion = (int) ($payload['catalog_version'] ?? 0);
        $enabledModules = $payload['enabled_modules'] ?? [];

        $needsRefresh = $storedVersion < $currentVersion;

        if ($needsRefresh) {
            return TenantModuleCatalog::normalizeForStorage(null, $plan, $updatedBy, null);
        }

        $resolved = TenantModuleCatalog::resolve($payload, $plan, [], $businessType);

        return [
            'enabled_modules' => $resolved['enabled_modules'],
            'custom_modules' => $resolved['custom_modules'],
            'catalog_version' => (int) ($payload['catalog_version'] ?? TenantModuleCatalog::VERSION),
            'updated_at' => $payload['updated_at'] ?? null,
            'updated_by' => $payload['updated_by'] ?? $updatedBy,
        ];
    }

    private function buildSubscriptionSnapshot(Tenant $tenant, TenantSubscription $subscription, array $pendingModules = []): array
    {
        $summary = SubscriptionLifecycle::summary($subscription->expires_at, $subscription->grace_ends_at);
        $status = in_array((string) $subscription->status, self::MANUAL_STATUSES, true)
            ? (string) $subscription->status
            : $summary['status'];

        return [
            'id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'plan' => $subscription->plan,
            'status' => $status,
            'billing_cycle' => $subscription->billing_cycle,
            'renewal_mode' => $subscription->renewal_mode,
            'started_at' => optional($subscription->started_at)->toIso8601String(),
            'renewal_window_starts_at' => optional($subscription->renewal_window_starts_at)->toIso8601String(),
            'expires_at' => optional($subscription->expires_at)->toIso8601String(),
            'grace_ends_at' => optional($subscription->grace_ends_at)->toIso8601String(),
            'last_renewed_at' => optional($subscription->last_renewed_at)->toIso8601String(),
            'days_until_expiration' => $summary['days_until_expiration'],
            'is_expiring_soon' => $summary['is_expiring_soon'],
            'needs_renewal' => $summary['needs_renewal'],
            'term_days' => SubscriptionLifecycle::termDays($subscription->plan),
            'grace_period_days' => SubscriptionLifecycle::gracePeriodDays($subscription->plan),
            'module_subscriptions' => TenantModuleCatalog::resolve(
                is_array($subscription->module_subscriptions) ? $subscription->module_subscriptions : null,
                $subscription->plan,
                $pendingModules,
                $tenant->business_type
            ),
        ];
    }
}
