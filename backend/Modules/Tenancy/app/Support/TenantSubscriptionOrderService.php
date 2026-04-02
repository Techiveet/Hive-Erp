<?php

namespace Modules\Tenancy\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Models\TenantSubscriptionOrder;
use RuntimeException;
use Throwable;

class TenantSubscriptionOrderService
{
    public function __construct(
        protected ArifPayGateway $gateway,
        protected TenantProvisioningService $provisioningService,
    ) {
    }

    public function createPublicSignupOrder(array $payload, string $backendBaseUrl, string $successBaseUrl, string $cancelBaseUrl): TenantSubscriptionOrder
    {
        $tenantId = strtolower((string) $payload['id']);
        $tenantDomain = strtolower((string) $payload['domain']);

        if (Tenant::query()->where('id', $tenantId)->exists()) {
            throw ValidationException::withMessages([
                'id' => ['That workspace slug is already registered.'],
            ]);
        }

        if (DB::table('domains')->where('domain', $tenantDomain)->exists()) {
            throw ValidationException::withMessages([
                'domain' => ['That tenant domain is already registered.'],
            ]);
        }

        $selectedModules = TenantModuleCatalog::normalizeRequestedModules($payload['selected_modules'] ?? []);
        $quote = TenantModuleCatalog::quoteForPlan($payload['plan'], $selectedModules);
        $order = new TenantSubscriptionOrder([
            'id' => (string) Str::ulid(),
            'public_token' => Str::random(40),
            'scope' => 'public_signup',
            'status' => $quote['total_etb'] > 0 ? 'pending_payment' : 'paid',
            'provider' => 'arifpay',
            'currency' => 'ETB',
            'tenant_id' => $tenantId,
            'tenant_name' => $payload['name'],
            'tenant_domain' => $tenantDomain,
            'plan' => strtolower((string) $payload['plan']),
            'admin_name' => $payload['admin_name'],
            'admin_email' => strtolower((string) $payload['admin_email']),
            'admin_password_encrypted' => Crypt::encryptString((string) $payload['admin_password']),
            'billing_phone' => $payload['billing_phone'],
            'module_request' => [
                'enabled_modules' => $selectedModules,
                'custom_modules' => [],
            ],
            'custom_modules' => [],
            'line_items' => $quote['line_items'],
            'plan_amount_etb' => $quote['plan_amount_etb'],
            'addon_amount_etb' => $quote['addon_amount_etb'],
            'total_amount_etb' => $quote['total_etb'],
        ]);
        $order->save();

        if ((float) $order->total_amount_etb <= 0) {
            $order->paid_at = now();
            $order->save();

            return $this->finalizePaidOrder($order);
        }

        return $this->attachCheckoutSession(
            $order,
            $backendBaseUrl,
            rtrim($successBaseUrl, '/') . '/auth/signup/success?order=' . $order->public_token,
            rtrim($cancelBaseUrl, '/') . '/auth/signup/cancel?order=' . $order->public_token,
            Arr::wrap($payload['payment_method'] ?? null)
        );
    }

    public function createTenantUpgradeOrder(Tenant $tenant, array $requestedModules, ?string $billingPhone, ?string $paymentMethod, ?string $requestedByEmail, string $backendBaseUrl, string $successBaseUrl, string $cancelBaseUrl): TenantSubscriptionOrder
    {
        $currentSubscriptions = TenantModuleCatalog::resolve(
            is_array($tenant->module_subscriptions) ? $tenant->module_subscriptions : null,
            $tenant->plan
        );

        $normalizedRequested = TenantModuleCatalog::normalizeRequestedModules($requestedModules);
        $newModules = array_values(array_diff($normalizedRequested, $currentSubscriptions['enabled_modules']));

        if ($newModules === []) {
            throw ValidationException::withMessages([
                'modules' => ['Every selected module is already active for this tenant.'],
            ]);
        }

        $quote = TenantModuleCatalog::quoteForUpgrade($tenant->plan, $newModules);
        $order = new TenantSubscriptionOrder([
            'id' => (string) Str::ulid(),
            'public_token' => Str::random(40),
            'scope' => 'tenant_upgrade',
            'status' => $quote['total_etb'] > 0 ? 'pending_payment' : 'paid',
            'provider' => 'arifpay',
            'currency' => 'ETB',
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name ?? ucfirst($tenant->id),
            'tenant_domain' => $tenant->domains()->value('domain'),
            'plan' => strtolower((string) $tenant->plan),
            'admin_email' => $requestedByEmail,
            'billing_phone' => $billingPhone,
            'module_request' => [
                'enabled_modules' => $newModules,
                'custom_modules' => [],
            ],
            'custom_modules' => [],
            'line_items' => $quote['line_items'],
            'plan_amount_etb' => 0,
            'addon_amount_etb' => $quote['total_etb'],
            'total_amount_etb' => $quote['total_etb'],
        ]);
        $order->save();

        if ((float) $order->total_amount_etb <= 0) {
            $order->paid_at = now();
            $order->save();

            return $this->finalizePaidOrder($order);
        }

        return $this->attachCheckoutSession(
            $order,
            $backendBaseUrl,
            rtrim($successBaseUrl, '/') . '/dashboard/subscriptions?checkout=' . $order->public_token,
            rtrim($cancelBaseUrl, '/') . '/dashboard/subscriptions?checkout=' . $order->public_token . '&cancelled=1',
            Arr::wrap($paymentMethod)
        );
    }

    public function syncOrderStatus(TenantSubscriptionOrder $order): TenantSubscriptionOrder
    {
        if (!$order->provider_session_id || $order->status === 'provisioned') {
            return $order;
        }

        $session = $this->gateway->fetchCheckoutSession($order->provider_session_id);
        $transaction = $session['transaction'] ?? [];
        $status = strtoupper((string) ($transaction['transactionStatus'] ?? 'PENDING'));

        $order->provider_status_payload = $session;
        $order->provider_transaction_id = $transaction['transactionId'] ?? $order->provider_transaction_id;

        if (in_array($status, ['SUCCESS', 'COMPLETED', 'PAID'], true)) {
            $order->status = 'paid';
            $order->paid_at = $order->paid_at ?? now();
            $order->save();

            return $this->finalizePaidOrder($order);
        }

        if (in_array($status, ['FAILED', 'DECLINED'], true)) {
            $order->status = 'failed';
        } elseif (in_array($status, ['CANCELLED', 'CANCELED'], true)) {
            $order->status = 'cancelled';
        } else {
            $order->status = 'payment_processing';
        }

        $order->save();

        return $order;
    }

    public function ingestNotifyPayload(TenantSubscriptionOrder $order, array $payload): TenantSubscriptionOrder
    {
        $order->notify_payload = $payload;

        $status = strtoupper((string) ($payload['transactionStatus'] ?? $payload['status'] ?? ''));
        $transactionId = $payload['transactionId'] ?? $payload['transaction_id'] ?? null;

        if ($transactionId) {
            $order->provider_transaction_id = (string) $transactionId;
        }

        if (in_array($status, ['SUCCESS', 'COMPLETED', 'PAID'], true)) {
            $order->status = 'paid';
            $order->paid_at = $order->paid_at ?? now();
            $order->save();

            return $this->finalizePaidOrder($order);
        }

        if (in_array($status, ['FAILED', 'DECLINED'], true)) {
            $order->status = 'failed';
        } elseif (in_array($status, ['CANCELLED', 'CANCELED'], true)) {
            $order->status = 'cancelled';
        }

        $order->save();

        return $order;
    }

    public function pendingModulesForTenant(Tenant $tenant): array
    {
        return TenantSubscriptionOrder::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['pending_payment', 'payment_processing'])
            ->get()
            ->flatMap(fn (TenantSubscriptionOrder $order) => $order->module_request['enabled_modules'] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    public function pendingOrdersForTenant(Tenant $tenant): array
    {
        return TenantSubscriptionOrder::query()
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (TenantSubscriptionOrder $order) => $this->toApiPayload($order))
            ->all();
    }

    public function toApiPayload(TenantSubscriptionOrder $order): array
    {
        return [
            'id' => $order->id,
            'public_token' => $order->public_token,
            'scope' => $order->scope,
            'status' => $order->status,
            'tenant_id' => $order->tenant_id,
            'tenant_name' => $order->tenant_name,
            'tenant_domain' => $order->tenant_domain,
            'plan' => $order->plan,
            'billing_phone' => $order->billing_phone,
            'line_items' => $order->line_items ?? [],
            'total_amount_etb' => (float) $order->total_amount_etb,
            'provider_session_id' => $order->provider_session_id,
            'provider_transaction_id' => $order->provider_transaction_id,
            'provider_checkout_url' => $order->provider_checkout_url,
            'paid_at' => optional($order->paid_at)->toIso8601String(),
            'provisioned_at' => optional($order->provisioned_at)->toIso8601String(),
            'created_at' => optional($order->created_at)->toIso8601String(),
            'module_request' => $order->module_request ?? [],
        ];
    }

    protected function attachCheckoutSession(TenantSubscriptionOrder $order, string $backendBaseUrl, string $successUrl, string $cancelUrl, array $requestedPaymentMethods = []): TenantSubscriptionOrder
    {
        if (!$this->gateway->isConfigured()) {
            throw new RuntimeException('ArifPay is not configured yet. Set the API key and beneficiary details first.');
        }

        $paymentMethods = collect($requestedPaymentMethods)
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->values()
            ->all();

        if ($paymentMethods === []) {
            $paymentMethods = $this->gateway->defaultPaymentMethods();
        }

        $checkout = $this->gateway->createCheckoutSession([
            'cancelUrl' => $cancelUrl,
            'phone' => $order->billing_phone,
            'email' => $order->admin_email,
            'nonce' => $order->id,
            'errorUrl' => $cancelUrl,
            'notifyUrl' => rtrim($backendBaseUrl, '/') . '/api/v1/public/subscriptions/orders/' . $order->public_token . '/notify',
            'successUrl' => $successUrl,
            'paymentMethods' => $paymentMethods,
            'expireDate' => $this->gateway->expiryTimestamp(),
            'items' => $this->checkoutItems($order),
            'beneficiaries' => [[
                'accountNumber' => $this->gateway->beneficiaryAccount(),
                'bank' => $this->gateway->beneficiaryBank(),
                'amount' => (float) $order->total_amount_etb,
            ]],
            'lang' => 'EN',
        ]);

        $order->provider_session_id = (string) ($checkout['sessionId'] ?? '');
        $order->provider_checkout_url = urldecode((string) ($checkout['paymentUrl'] ?? ''));
        $order->provider_payload = $checkout;
        $order->save();

        return $order;
    }

    protected function checkoutItems(TenantSubscriptionOrder $order): array
    {
        return collect($order->line_items ?? [])
            ->map(function (array $item) {
                return [
                    'name' => $item['name'],
                    'quantity' => 1,
                    'price' => (float) ($item['amount_etb'] ?? 0),
                    'description' => $item['description'] ?? $item['name'],
                ];
            })
            ->values()
            ->all();
    }

    protected function finalizePaidOrder(TenantSubscriptionOrder $order): TenantSubscriptionOrder
    {
        if ($order->provisioned_at) {
            return $order;
        }

        if ($order->scope === 'public_signup') {
            $tenant = $this->provisioningService->provision([
                'id' => $order->tenant_id,
                'name' => $order->tenant_name,
                'plan' => $order->plan,
                'domain' => $order->tenant_domain,
                'admin_name' => $order->admin_name,
                'admin_email' => $order->admin_email,
                'admin_password' => Crypt::decryptString((string) $order->admin_password_encrypted),
                'module_subscriptions' => $order->module_request ?? [],
            ], $order->admin_email);

            $order->tenant_id = $tenant->id;
            $order->tenant_domain = $tenant->domains->first()->domain ?? $order->tenant_domain;
        } else {
            $tenant = Tenant::query()->with('domains')->findOrFail($order->tenant_id);
            $resolved = TenantModuleCatalog::resolve(
                is_array($tenant->module_subscriptions) ? $tenant->module_subscriptions : null,
                $tenant->plan
            );
            $requestedModules = TenantModuleCatalog::normalizeRequestedModules(
                $order->module_request['enabled_modules'] ?? []
            );
            $nextEnabledModules = collect($resolved['enabled_modules'])
                ->concat($requestedModules)
                ->unique()
                ->values()
                ->all();

            $tenant->module_subscriptions = TenantModuleCatalog::normalizeForStorage([
                'enabled_modules' => $nextEnabledModules,
                'custom_modules' => $resolved['custom_modules'],
            ], $tenant->plan, $order->admin_email);
            $tenant->save();
        }

        $order->status = 'provisioned';
        $order->provisioned_at = now();
        $order->save();

        return $order->refresh();
    }
}
