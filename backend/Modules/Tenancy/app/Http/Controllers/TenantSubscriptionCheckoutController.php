<?php

namespace Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Models\TenantSubscriptionOrder;
use Modules\Tenancy\Support\TenantModuleCatalog;
use Modules\Tenancy\Support\TenantSubscriptionOrderService;

class TenantSubscriptionCheckoutController extends Controller
{
    public function __construct(
        protected TenantSubscriptionOrderService $orders,
    ) {
    }

    public function publicCatalog()
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

    public function publicCheckout(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'alpha_dash', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', 'string', Rule::in(array_keys(TenantModuleCatalog::planPricing()))],
            'domain' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8'],
            'billing_phone' => ['required', 'string', 'max:20'],
            'selected_modules' => ['nullable', 'array'],
            'selected_modules.*' => ['string', Rule::in(TenantModuleCatalog::slugs())],
            'payment_method' => ['nullable', 'string'],
            'success_url_base' => ['required', 'url'],
            'cancel_url_base' => ['required', 'url'],
        ]);

        $order = $this->orders->createPublicSignupOrder(
            $validated,
            $request->getSchemeAndHttpHost(),
            $validated['success_url_base'],
            $validated['cancel_url_base'],
        );

        return response()->json([
            'message' => $order->provider_checkout_url
                ? 'Checkout session created successfully.'
                : 'Tenant registration completed successfully.',
            'data' => $this->publicOrderPayload($order),
        ], 201);
    }

    public function publicOrderStatus(string $token)
    {
        $order = TenantSubscriptionOrder::query()->where('public_token', $token)->firstOrFail();
        $order = $this->orders->syncOrderStatus($order);

        return response()->json([
            'data' => $this->publicOrderPayload($order),
        ], 200);
    }

    public function publicNotify(Request $request, string $token)
    {
        $order = TenantSubscriptionOrder::query()->where('public_token', $token)->firstOrFail();
        $this->orders->ingestNotifyPayload($order, $request->all());

        return response()->json(['message' => 'Notification received.'], 200);
    }

    public function startCurrentCheckout(Request $request)
    {
        /** @var Tenant|null $tenant */
        $tenant = function_exists('tenant') ? tenant() : null;

        if (!$tenant) {
            return response()->json(['message' => 'Tenant context was not initialized for this request.'], 404);
        }

        $validated = $request->validate([
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', Rule::in(TenantModuleCatalog::slugs())],
            'billing_phone' => ['required', 'string', 'max:20'],
            'payment_method' => ['nullable', 'string'],
            'success_url_base' => ['required', 'url'],
            'cancel_url_base' => ['required', 'url'],
        ]);

        $order = $this->orders->createTenantUpgradeOrder(
            $tenant,
            $validated['modules'],
            $validated['billing_phone'],
            $validated['payment_method'] ?? null,
            auth()->user()?->email,
            $request->getSchemeAndHttpHost(),
            $validated['success_url_base'],
            $validated['cancel_url_base'],
        );

        return response()->json([
            'message' => $order->provider_checkout_url
                ? 'Module checkout session created successfully.'
                : 'Requested modules were activated successfully.',
            'data' => [
                'order' => $this->orders->toApiPayload($order),
            ],
        ], 201);
    }

    public function syncCurrentCheckout(string $token)
    {
        /** @var Tenant|null $tenant */
        $tenant = function_exists('tenant') ? tenant() : null;

        if (!$tenant) {
            return response()->json(['message' => 'Tenant context was not initialized for this request.'], 404);
        }

        $order = TenantSubscriptionOrder::query()
            ->where('public_token', $token)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $order = $this->orders->syncOrderStatus($order);

        return response()->json([
            'data' => [
                'order' => $this->orders->toApiPayload($order),
            ],
        ], 200);
    }

    protected function publicOrderPayload(TenantSubscriptionOrder $order): array
    {
        return [
            'order' => $this->orders->toApiPayload($order),
            'tenant' => $order->tenant_id ? [
                'id' => $order->tenant_id,
                'name' => $order->tenant_name,
                'domain' => $order->tenant_domain,
            ] : null,
        ];
    }
}
