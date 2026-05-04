<?php

namespace Modules\Subscription\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Subscription\Support\PaymentProviderManager;
use Modules\Subscription\Support\TenantModuleCatalog;
use Modules\Subscription\Support\TenantSubscriptionOrderService;
use Modules\Subscription\Support\TenantSubscriptionService;
use Modules\Subscription\Support\SubscriptionFeatureMap;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantLandingTemplateCatalog;

class TenantSubscriptionController extends Controller
{
    public function __construct(
        protected TenantSubscriptionOrderService $orders,
        protected TenantSubscriptionService $subscriptions,
        protected PaymentProviderManager $payments,
        protected TenantLandingTemplateCatalog $landingTemplates,
        protected SubscriptionFeatureMap $featureMap,
    ) {
    }

    public function catalog()
    {
        return response()->json([
            'data' => [
                'catalog' => TenantModuleCatalog::catalogWithPricing(),
                'feature_map' => [
                    'modules' => $this->featureMap->modules(),
                    'submodules' => $this->featureMap->submodules(),
                    'features' => $this->featureMap->features(),
                ],
                'plan_defaults' => TenantModuleCatalog::planDefaults(),
                'plan_pricing' => TenantModuleCatalog::planPricing(),
                'business_types' => $this->landingTemplates->businessTypesPayload(),
                'payment_provider' => $this->payments->activeProviderPayload(),
                'payment_providers' => $this->payments->publicProvidersPayload(),
                'payment_methods' => $this->payments->paymentMethods(),
                'direct_transfer' => $this->payments->directTransferPayload(),
                'subscription_policy' => [
                    'term_days' => \Modules\Subscription\Support\SubscriptionLifecycle::termDays(),
                    'grace_period_days' => \Modules\Subscription\Support\SubscriptionLifecycle::gracePeriodDays(),
                    'renewal_notice_days' => \Modules\Subscription\Support\SubscriptionLifecycle::renewalNoticeDays(),
                ],
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
        $currentSubscription = $this->subscriptions->currentForTenant($tenant, $pendingModules);

        return response()->json([
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name ?? ucfirst($tenant->id),
                    'plan' => $tenant->plan ?? 'business',
                ],
                'subscription' => collect($currentSubscription)->except('module_subscriptions')->all(),
                'module_subscriptions' => $currentSubscription['module_subscriptions'],
                'feature_matrix' => $this->featureMap->matrixForCatalogModules(
                    $currentSubscription['module_subscriptions']['catalog_modules'] ?? []
                ),
                'catalog' => TenantModuleCatalog::catalogWithPricing(),
                'feature_map' => [
                    'modules' => $this->featureMap->modules(),
                    'submodules' => $this->featureMap->submodules(),
                    'features' => $this->featureMap->features(),
                ],
                'plan_defaults' => TenantModuleCatalog::planDefaults(),
                'plan_pricing' => TenantModuleCatalog::planPricing(),
                'business_types' => $this->landingTemplates->businessTypesPayload(),
                'payment_provider' => $this->payments->activeProviderPayload(),
                'payment_providers' => $this->payments->publicProvidersPayload(),
                'payment_methods' => $this->payments->paymentMethods(),
                'direct_transfer' => $this->payments->directTransferPayload(),
                'subscription_policy' => [
                    'term_days' => \Modules\Subscription\Support\SubscriptionLifecycle::termDays(),
                    'grace_period_days' => \Modules\Subscription\Support\SubscriptionLifecycle::gracePeriodDays(),
                    'renewal_notice_days' => \Modules\Subscription\Support\SubscriptionLifecycle::renewalNoticeDays(),
                ],
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
        $currentResolved = $this->subscriptions->currentForTenant($tenant)['module_subscriptions'];
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

        $subscription = $this->subscriptions->updateModules(
            $tenant,
            $validated['module_subscriptions'] ?? null,
            auth()->user()?->email
        );

        activity('Module Subscriptions')
            ->causedBy(auth()->user())
            ->event('updated')
            ->withProperties([
                'tenant_id' => $tenant->id,
                'enabled_modules' => $subscription->module_subscriptions['enabled_modules'] ?? [],
                'custom_modules' => $subscription->module_subscriptions['custom_modules'] ?? [],
            ])
            ->log("Updated tenant module subscriptions for [{$tenant->id}].");

        $currentSubscription = $this->subscriptions->currentForTenant(
            $tenant->refresh(),
            $this->orders->pendingModulesForTenant($tenant)
        );

        return response()->json([
            'message' => 'Module subscriptions updated successfully.',
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name ?? ucfirst($tenant->id),
                    'plan' => $tenant->plan ?? 'business',
                ],
                'subscription' => collect($currentSubscription)->except('module_subscriptions')->all(),
                'module_subscriptions' => $currentSubscription['module_subscriptions'],
                'feature_matrix' => $this->featureMap->matrixForCatalogModules(
                    $currentSubscription['module_subscriptions']['catalog_modules'] ?? []
                ),
                'catalog' => TenantModuleCatalog::catalogWithPricing(),
                'feature_map' => [
                    'modules' => $this->featureMap->modules(),
                    'submodules' => $this->featureMap->submodules(),
                    'features' => $this->featureMap->features(),
                ],
                'plan_defaults' => TenantModuleCatalog::planDefaults(),
                'plan_pricing' => TenantModuleCatalog::planPricing(),
                'business_types' => $this->landingTemplates->businessTypesPayload(),
                'payment_provider' => $this->payments->activeProviderPayload(),
                'payment_providers' => $this->payments->publicProvidersPayload(),
                'payment_methods' => $this->payments->paymentMethods(),
                'direct_transfer' => $this->payments->directTransferPayload(),
                'subscription_policy' => [
                    'term_days' => \Modules\Subscription\Support\SubscriptionLifecycle::termDays(),
                    'grace_period_days' => \Modules\Subscription\Support\SubscriptionLifecycle::gracePeriodDays(),
                    'renewal_notice_days' => \Modules\Subscription\Support\SubscriptionLifecycle::renewalNoticeDays(),
                ],
                'pending_orders' => $this->orders->pendingOrdersForTenant($tenant),
            ],
        ], 200);
    }
}

