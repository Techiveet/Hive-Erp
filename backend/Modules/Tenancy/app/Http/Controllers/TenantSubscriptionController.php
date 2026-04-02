<?php

namespace Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantModuleCatalog;
use Modules\Tenancy\Support\TenantSubscriptionOrderService;

class TenantSubscriptionController extends Controller
{
    public function __construct(
        protected TenantSubscriptionOrderService $orders,
    ) {
    }

    public function catalog()
    {
        return response()->json([
            'data' => [
                'catalog' => TenantModuleCatalog::catalog(),
                'plan_defaults' => TenantModuleCatalog::planDefaults(),
                'plan_pricing' => TenantModuleCatalog::planPricing(),
                'payment_methods' => TenantModuleCatalog::paymentMethods(),
            ],
        ], 200);
    }

    public function current()
    {
        /** @var Tenant|null $tenant */
        $tenant = function_exists('tenant') ? tenant() : null;

        if (!$tenant) {
            return response()->json(['message' => 'Tenant context was not initialized for this request.'], 404);
        }

        $pendingModules = $this->orders->pendingModulesForTenant($tenant);
        $resolvedSubscriptions = TenantModuleCatalog::resolve(
            is_array($tenant->module_subscriptions) ? $tenant->module_subscriptions : null,
            $tenant->plan,
            $pendingModules
        );

        return response()->json([
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name ?? ucfirst($tenant->id),
                    'plan' => $tenant->plan ?? 'business',
                ],
                'module_subscriptions' => $resolvedSubscriptions,
                'catalog' => TenantModuleCatalog::catalog(),
                'plan_defaults' => TenantModuleCatalog::planDefaults(),
                'plan_pricing' => TenantModuleCatalog::planPricing(),
                'payment_methods' => TenantModuleCatalog::paymentMethods(),
                'pending_orders' => $this->orders->pendingOrdersForTenant($tenant),
            ],
        ], 200);
    }

    public function updateCurrent(Request $request)
    {
        /** @var Tenant|null $tenant */
        $tenant = function_exists('tenant') ? tenant() : null;

        if (!$tenant) {
            return response()->json(['message' => 'Tenant context was not initialized for this request.'], 404);
        }

        $validated = $request->validate(TenantModuleCatalog::validationRules());
        $currentResolved = TenantModuleCatalog::resolve(
            is_array($tenant->module_subscriptions) ? $tenant->module_subscriptions : null,
            $tenant->plan
        );
        $requestedResolved = TenantModuleCatalog::resolve(
            $validated['module_subscriptions'] ?? null,
            $tenant->plan
        );

        $newModules = array_values(array_diff(
            $requestedResolved['enabled_modules'],
            $currentResolved['enabled_modules']
        ));
        $paidAddons = array_values(array_filter(
            $newModules,
            fn (string $slug) => TenantModuleCatalog::modulePriceForPlan($slug, $tenant->plan) > 0
        ));

        if ($paidAddons !== []) {
            return response()->json([
                'message' => 'One or more selected modules require checkout before they can be activated.',
                'code' => 'SUBSCRIPTION_CHECKOUT_REQUIRED',
                'modules' => $paidAddons,
            ], 422);
        }

        $tenant->module_subscriptions = TenantModuleCatalog::normalizeForStorage(
            $validated['module_subscriptions'] ?? null,
            $tenant->plan,
            auth()->user()?->email
        );
        $tenant->save();

        activity('Module Subscriptions')
            ->causedBy(auth()->user())
            ->event('updated')
            ->withProperties([
                'tenant_id' => $tenant->id,
                'enabled_modules' => $tenant->module_subscriptions['enabled_modules'] ?? [],
                'custom_modules' => $tenant->module_subscriptions['custom_modules'] ?? [],
            ])
            ->log("Updated tenant module subscriptions for [{$tenant->id}].");

        return response()->json([
            'message' => 'Module subscriptions updated successfully.',
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name ?? ucfirst($tenant->id),
                    'plan' => $tenant->plan ?? 'business',
                ],
                'module_subscriptions' => TenantModuleCatalog::resolve(
                    is_array($tenant->module_subscriptions) ? $tenant->module_subscriptions : null,
                    $tenant->plan,
                    $this->orders->pendingModulesForTenant($tenant)
                ),
                'catalog' => TenantModuleCatalog::catalog(),
                'plan_defaults' => TenantModuleCatalog::planDefaults(),
                'plan_pricing' => TenantModuleCatalog::planPricing(),
                'payment_methods' => TenantModuleCatalog::paymentMethods(),
                'pending_orders' => $this->orders->pendingOrdersForTenant($tenant),
            ],
        ], 200);
    }
}
