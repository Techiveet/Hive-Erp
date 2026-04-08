<?php

namespace Modules\Subscription\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Subscription\Models\TenantSubscriptionOrder;
use Modules\Subscription\Support\PaymentProviderManager;
use Modules\Subscription\Support\TenantModuleCatalog;
use Modules\Subscription\Support\TenantSubscriptionOrderService;
use Modules\Tenancy\Models\Tenant;

class TenantSubscriptionCheckoutController extends Controller
{
    public function __construct(
        protected TenantSubscriptionOrderService $orders,
        protected PaymentProviderManager $payments,
    ) {
    }

    public function publicCatalog()
    {
        $activePlanPricing = TenantModuleCatalog::activePlanPricing();

        return response()->json([
            'data' => [
                'catalog' => TenantModuleCatalog::catalog(),
                'plan_defaults' => array_intersect_key(TenantModuleCatalog::planDefaults(), $activePlanPricing),
                'plan_pricing' => $activePlanPricing,
                'payment_provider' => $this->payments->activeProviderPayload(),
                'payment_providers' => $this->payments->publicProvidersPayload(),
                'payment_methods' => $this->payments->paymentMethods(),
                'direct_transfer' => $this->payments->directTransferPayload(),
            ],
        ], 200);
    }

    public function publicCheckout(Request $request)
    {
        $prepared = $this->preparePublicCheckoutInput($request);
        $selectedModules = TenantModuleCatalog::normalizeRequestedModules($prepared['selected_modules'] ?? []);
        $quote = TenantModuleCatalog::quoteForPlan((string) ($prepared['plan'] ?? ''), $selectedModules);
        $provider = $this->payments->activeProvider();
        $isDirectTransfer = strtolower((string) ($prepared['checkout_channel'] ?? 'gateway')) === 'direct_transfer';
        $paymentMethodRules = ['nullable', 'string'];
        $checkoutChannelRules = ['nullable', 'string', Rule::in(['gateway', 'direct_transfer'])];

        if ((float) $quote['total_etb'] > 0 && $provider->supportsPaymentMethods() && $this->payments->paymentMethods() !== []) {
            $paymentMethodRules[] = Rule::in(collect($this->payments->paymentMethods())->pluck('code')->all());
        }

        $validated = validator($prepared, [
            'id' => ['required', 'string', 'alpha_dash', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', 'string', Rule::in(TenantModuleCatalog::activePlanKeys())],
            'domain' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8'],
            'billing_phone' => (float) $quote['total_etb'] > 0 && !$isDirectTransfer && $provider->requiresBillingPhone()
                ? ['required', 'string', 'max:20']
                : ['nullable', 'string', 'max:20'],
            'selected_modules' => ['nullable', 'array'],
            'selected_modules.*' => ['string', Rule::in(TenantModuleCatalog::slugs())],
            'checkout_channel' => $checkoutChannelRules,
            'payment_method' => $paymentMethodRules,
            'manual_bank_account_id' => ['nullable', 'string', 'max:64'],
            'manual_transaction_reference' => ['nullable', 'string', 'max:255'],
            'success_url_base' => ['required', 'url'],
            'cancel_url_base' => ['required', 'url'],
        ])->validate();

        $order = $this->orders->createPublicSignupOrder(
            $validated,
            $this->backendCheckoutBaseUrl($request),
            $validated['success_url_base'],
            $validated['cancel_url_base'],
        );

        return response()->json([
            'message' => $order->status === 'pending_manual_review'
                ? 'Direct transfer submitted. An admin will verify the reference and activate the workspace.'
                : ($order->provider_checkout_url
                    ? 'Checkout session created successfully.'
                    : 'Tenant registration completed successfully.'),
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
        $this->orders->ingestNotifyPayload($order, $request->all(), $request->headers->all());

        return response()->json(['message' => 'Notification received.'], 200);
    }

    public function startCurrentCheckout(Request $request)
    {
        /** @var Tenant|null $tenant */
        $tenant = function_exists('tenant') ? tenant() : null;

        if (!$tenant) {
            return response()->json(['message' => 'Tenant context was not initialized for this request.'], 404);
        }

        $provider = $this->payments->activeProvider();
        $isDirectTransfer = strtolower((string) ($request->input('checkout_channel', 'gateway'))) === 'direct_transfer';
        $paymentMethodRules = ['nullable', 'string'];
        $checkoutChannelRules = ['nullable', 'string', Rule::in(['gateway', 'direct_transfer'])];

        if ($provider->supportsPaymentMethods() && $this->payments->paymentMethods() !== []) {
            $paymentMethodRules[] = Rule::in(collect($this->payments->paymentMethods())->pluck('code')->all());
        }

        $validated = $request->validate([
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', Rule::in(TenantModuleCatalog::slugs())],
            'billing_phone' => !$isDirectTransfer && $provider->requiresBillingPhone()
                ? ['required', 'string', 'max:20']
                : ['nullable', 'string', 'max:20'],
            'checkout_channel' => $checkoutChannelRules,
            'payment_method' => $paymentMethodRules,
            'manual_bank_account_id' => ['nullable', 'string', 'max:64'],
            'manual_transaction_reference' => ['nullable', 'string', 'max:255'],
            'success_url_base' => ['required', 'url'],
            'cancel_url_base' => ['required', 'url'],
        ]);

        $order = $this->orders->createTenantUpgradeOrder(
            $tenant,
            $validated['modules'],
            $validated['billing_phone'],
            $validated['payment_method'] ?? null,
            auth()->user()?->email,
            $this->backendCheckoutBaseUrl($request),
            $validated['success_url_base'],
            $validated['cancel_url_base'],
            [
                'checkout_channel' => $validated['checkout_channel'] ?? 'gateway',
                'manual_bank_account_id' => $validated['manual_bank_account_id'] ?? null,
                'manual_transaction_reference' => $validated['manual_transaction_reference'] ?? null,
            ],
        );

        return response()->json([
            'message' => $order->status === 'pending_manual_review'
                ? 'Direct transfer submitted. The selected modules will activate after manual verification.'
                : ($order->provider_checkout_url
                    ? 'Module checkout session created successfully.'
                    : 'Requested modules were activated successfully.'),
            'data' => [
                'order' => $this->orders->toApiPayload($order),
            ],
        ], 201);
    }

    public function startCurrentRenewalCheckout(Request $request)
    {
        /** @var Tenant|null $tenant */
        $tenant = function_exists('tenant') ? tenant() : null;

        if (!$tenant) {
            return response()->json(['message' => 'Tenant context was not initialized for this request.'], 404);
        }

        $provider = $this->payments->activeProvider();
        $isDirectTransfer = strtolower((string) ($request->input('checkout_channel', 'gateway'))) === 'direct_transfer';
        $paymentMethodRules = ['nullable', 'string'];
        $checkoutChannelRules = ['nullable', 'string', Rule::in(['gateway', 'direct_transfer'])];

        if ($provider->supportsPaymentMethods() && $this->payments->paymentMethods() !== []) {
            $paymentMethodRules[] = Rule::in(collect($this->payments->paymentMethods())->pluck('code')->all());
        }

        $validated = $request->validate([
            'billing_phone' => !$isDirectTransfer && $provider->requiresBillingPhone()
                ? ['required', 'string', 'max:20']
                : ['nullable', 'string', 'max:20'],
            'checkout_channel' => $checkoutChannelRules,
            'payment_method' => $paymentMethodRules,
            'manual_bank_account_id' => ['nullable', 'string', 'max:64'],
            'manual_transaction_reference' => ['nullable', 'string', 'max:255'],
            'success_url_base' => ['required', 'url'],
            'cancel_url_base' => ['required', 'url'],
        ]);

        $order = $this->orders->createTenantRenewalOrder(
            $tenant,
            $validated['billing_phone'],
            $validated['payment_method'] ?? null,
            auth()->user()?->email,
            $this->backendCheckoutBaseUrl($request),
            $validated['success_url_base'],
            $validated['cancel_url_base'],
            [
                'checkout_channel' => $validated['checkout_channel'] ?? 'gateway',
                'manual_bank_account_id' => $validated['manual_bank_account_id'] ?? null,
                'manual_transaction_reference' => $validated['manual_transaction_reference'] ?? null,
            ],
        );

        return response()->json([
            'message' => $order->status === 'pending_manual_review'
                ? 'Direct transfer submitted. The renewal will activate after manual verification.'
                : ($order->provider_checkout_url
                    ? 'Renewal checkout session created successfully.'
                    : 'Tenant subscription renewed successfully.'),
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

    protected function preparePublicCheckoutInput(Request $request): array
    {
        $input = $request->all();
        $frontendBaseUrl = rtrim((string) ($input['success_url_base']
            ?? $request->headers->get('origin')
            ?? config('app.frontend_url', 'http://localhost:3000')), '/');

        $input['id'] = $input['id'] ?? $input['tenant_id'] ?? null;
        $input['name'] = $input['name'] ?? $input['tenant_name'] ?? null;
        $input['domain'] = $input['domain'] ?? $this->defaultTenantDomain($input['id'] ?? null);
        $input['success_url_base'] = $input['success_url_base'] ?? $frontendBaseUrl;
        $input['cancel_url_base'] = $input['cancel_url_base'] ?? $frontendBaseUrl;

        return $input;
    }

    protected function defaultTenantDomain(?string $tenantId): ?string
    {
        $tenantId = trim((string) $tenantId);

        if ($tenantId === '') {
            return null;
        }

        $frontendUrl = (string) config('app.frontend_url', 'http://localhost:3000');
        $host = parse_url($frontendUrl, PHP_URL_HOST) ?: 'localhost';

        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            return "{$tenantId}.localhost";
        }

        return "{$tenantId}.{$host}";
    }

    protected function backendCheckoutBaseUrl(Request $request): string
    {
        $configured = rtrim((string) config('app.url', ''), '/');
        $requestHost = rtrim($request->getSchemeAndHttpHost(), '/');

        if ($this->isLocalUrl($configured) && !$this->isLocalUrl($requestHost)) {
            return $requestHost;
        }

        if ($configured !== '') {
            return $configured;
        }

        return $requestHost;
    }

    protected function isLocalUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST) ?: '';

        return in_array($host, ['localhost', '127.0.0.1'], true);
    }
}

