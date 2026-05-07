<?php

namespace Modules\Subscription\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\Subscription\Mail\TenantSubscriptionManualTransferUpdate;
use Modules\Subscription\Models\TenantSubscription;
use Modules\Subscription\Models\TenantSubscriptionOrder;
use Modules\Subscription\Notifications\DirectTransferReviewSubmitted;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantLandingTemplateCatalog;
use Modules\Tenancy\Support\TenantProvisioningService;

class TenantSubscriptionOrderService
{
    public function __construct(
        protected PaymentProviderManager $payments,
        protected TenantProvisioningService $provisioningService,
        protected TenantSubscriptionService $subscriptions,
        protected TenantLandingTemplateCatalog $landingTemplates,
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
        $usesManualTransfer = $this->usesManualTransfer($payload);
        $order = new TenantSubscriptionOrder([
            'id' => (string) Str::ulid(),
            'public_token' => Str::random(40),
            'scope' => 'public_signup',
            'status' => $quote['total_etb'] > 0
                ? ($usesManualTransfer ? 'pending_manual_review' : 'pending_payment')
                : 'paid',
            'provider' => $usesManualTransfer ? 'direct_transfer' : $this->payments->activeProvider()->key(),
            'payment_channel' => $usesManualTransfer ? 'direct_transfer' : 'gateway',
            'currency' => 'ETB',
            'tenant_id' => $tenantId,
            'tenant_name' => $payload['name'],
            'tenant_domain' => $tenantDomain,
            'plan' => strtolower((string) $payload['plan']),
            'business_type' => $this->landingTemplates->normalizeBusinessType($payload['business_type'] ?? null),
            'admin_name' => $payload['admin_name'],
            'admin_email' => strtolower((string) $payload['admin_email']),
            'admin_password_encrypted' => Crypt::encryptString((string) $payload['admin_password']),
            'billing_phone' => $payload['billing_phone'] ?? null,
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

        if ($usesManualTransfer) {
            return $this->attachManualTransferSubmission($order, $payload);
        }

        return $this->attachCheckoutSession(
            $order,
            $backendBaseUrl,
            rtrim($successBaseUrl, '/') . '/auth/signup?checkout=' . $order->public_token,
            rtrim($cancelBaseUrl, '/') . '/auth/signup?checkout=' . $order->public_token . '&cancelled=1',
            Arr::wrap($payload['payment_method'] ?? null)
        );
    }

    public function createTenantUpgradeOrder(Tenant $tenant, array $requestedModules, ?string $billingPhone, ?string $paymentMethod, ?string $requestedByEmail, string $backendBaseUrl, string $successBaseUrl, string $cancelBaseUrl, array $options = []): TenantSubscriptionOrder
    {
        $currentSubscriptions = $this->subscriptions->currentForTenant($tenant)['module_subscriptions'];

        $normalizedRequested = TenantModuleCatalog::normalizeRequestedModules($requestedModules);
        $newModules = array_values(array_diff($normalizedRequested, $currentSubscriptions['enabled_modules']));

        if ($newModules === []) {
            throw ValidationException::withMessages([
                'modules' => ['Every selected module is already active for this tenant.'],
            ]);
        }

        $quote = TenantModuleCatalog::quoteForUpgrade($tenant->plan, $newModules);
        $usesManualTransfer = $this->usesManualTransfer($options);
        $order = new TenantSubscriptionOrder([
            'id' => (string) Str::ulid(),
            'public_token' => Str::random(40),
            'scope' => 'tenant_upgrade',
            'status' => $quote['total_etb'] > 0
                ? ($usesManualTransfer ? 'pending_manual_review' : 'pending_payment')
                : 'paid',
            'provider' => $usesManualTransfer ? 'direct_transfer' : $this->payments->activeProvider()->key(),
            'payment_channel' => $usesManualTransfer ? 'direct_transfer' : 'gateway',
            'currency' => 'ETB',
            'tenant_id' => $tenant->id,
            'subscription_id' => TenantSubscription::query()->where('tenant_id', $tenant->id)->value('id'),
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

        if ($usesManualTransfer) {
            return $this->attachManualTransferSubmission($order, $options);
        }

        return $this->attachCheckoutSession(
            $order,
            $backendBaseUrl,
            rtrim($successBaseUrl, '/') . '/dashboard/subscriptions?checkout=' . $order->public_token,
            rtrim($cancelBaseUrl, '/') . '/dashboard/subscriptions?checkout=' . $order->public_token . '&cancelled=1',
            Arr::wrap($paymentMethod)
        );
    }

    public function createTenantRenewalOrder(Tenant $tenant, ?string $billingPhone, ?string $paymentMethod, ?string $requestedByEmail, string $backendBaseUrl, string $successBaseUrl, string $cancelBaseUrl, array $options = []): TenantSubscriptionOrder
    {
        $existingPendingRenewal = TenantSubscriptionOrder::query()
            ->where('tenant_id', $tenant->id)
            ->where('scope', 'tenant_renewal')
            ->whereIn('status', ['pending_payment', 'payment_processing', 'pending_manual_review', 'paid'])
            ->latest()
            ->first();

        if ($existingPendingRenewal) {
            return $existingPendingRenewal;
        }

        $current = $this->subscriptions->currentForTenant($tenant);
        $moduleSubscriptions = $current['module_subscriptions'];
        $quote = TenantModuleCatalog::quoteForPlan($tenant->plan, $moduleSubscriptions['enabled_modules'] ?? []);
        $subscriptionId = TenantSubscription::query()->where('tenant_id', $tenant->id)->value('id');

        $usesManualTransfer = $this->usesManualTransfer($options);
        $order = new TenantSubscriptionOrder([
            'id' => (string) Str::ulid(),
            'public_token' => Str::random(40),
            'scope' => 'tenant_renewal',
            'status' => $quote['total_etb'] > 0
                ? ($usesManualTransfer ? 'pending_manual_review' : 'pending_payment')
                : 'paid',
            'provider' => $usesManualTransfer ? 'direct_transfer' : $this->payments->activeProvider()->key(),
            'payment_channel' => $usesManualTransfer ? 'direct_transfer' : 'gateway',
            'currency' => 'ETB',
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscriptionId,
            'tenant_name' => $tenant->name ?? ucfirst($tenant->id),
            'tenant_domain' => $tenant->domains()->value('domain'),
            'plan' => strtolower((string) $tenant->plan),
            'admin_email' => $requestedByEmail,
            'billing_phone' => $billingPhone,
            'module_request' => [
                'enabled_modules' => $moduleSubscriptions['enabled_modules'] ?? [],
                'custom_modules' => $moduleSubscriptions['custom_modules'] ?? [],
            ],
            'custom_modules' => $moduleSubscriptions['custom_modules'] ?? [],
            'line_items' => $quote['line_items'],
            'plan_amount_etb' => $quote['plan_amount_etb'],
            'addon_amount_etb' => $quote['addon_amount_etb'],
            'total_amount_etb' => $quote['total_etb'],
            'renewal_term_days' => SubscriptionLifecycle::termDays($tenant->plan),
        ]);
        $order->save();

        if ((float) $order->total_amount_etb <= 0) {
            $order->paid_at = now();
            $order->save();

            return $this->finalizePaidOrder($order);
        }

        if ($usesManualTransfer) {
            return $this->attachManualTransferSubmission($order, $options);
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
        if ($order->status === 'provisioned' || $order->payment_channel === 'direct_transfer') {
            return $order;
        }

        if (!$order->provider_session_id && $order->status !== 'paid') {
            return $order;
        }

        $provider = $this->payments->provider($order->provider);
        $result = $provider->syncOrder($order);

        return $this->applyProviderStatus($order, $result);
    }

    public function ingestNotifyPayload(TenantSubscriptionOrder $order, array $payload, array $headers = []): TenantSubscriptionOrder
    {
        $order->notify_payload = $payload;
        $order->save();

        $provider = $this->payments->provider($order->provider);
        $result = $provider->ingestNotification($order, $payload, $headers);

        return $this->applyProviderStatus($order, $result, false);
    }

    public function pendingModulesForTenant(Tenant $tenant): array
    {
        return TenantSubscriptionOrder::query()
            ->where('tenant_id', $tenant->id)
            ->where('scope', 'tenant_upgrade')
            ->whereIn('status', ['pending_payment', 'payment_processing', 'pending_manual_review'])
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

    public function manualReviewQueue(): array
    {
        return TenantSubscriptionOrder::query()
            ->where('payment_channel', 'direct_transfer')
            ->where('status', 'pending_manual_review')
            ->latest('manual_payment_submitted_at')
            ->limit(50)
            ->get()
            ->map(fn (TenantSubscriptionOrder $order) => $this->toApiPayload($order))
            ->all();
    }

    public function manualReviewLedger(int $limit = 200): array
    {
        return TenantSubscriptionOrder::query()
            ->where('payment_channel', 'direct_transfer')
            ->latest('manual_payment_submitted_at')
            ->limit($limit)
            ->get()
            ->map(fn (TenantSubscriptionOrder $order) => $this->toApiPayload($order))
            ->all();
    }

    public function manualReviewCounts(): array
    {
        $base = TenantSubscriptionOrder::query()->where('payment_channel', 'direct_transfer');

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('manual_review_status', 'pending')->count(),
            'approved' => (clone $base)->where('manual_review_status', 'approved')->count(),
            'rejected' => (clone $base)->where('manual_review_status', 'rejected')->count(),
        ];
    }

    public function approveManualPayment(TenantSubscriptionOrder $order, ?string $reviewedBy = null, ?string $notes = null): TenantSubscriptionOrder
    {
        $this->assertManualTransferReviewable($order);

        $order->manual_review_status = 'approved';
        $order->manual_review_notes = $notes;
        $order->manual_reviewed_by = $reviewedBy;
        $order->manual_reviewed_at = now();
        $order->status = 'paid';
        $order->paid_at = now();
        $order->save();

        $order = $this->finalizePaidOrder($order);

        if ($order->scope !== 'public_signup') {
            $this->sendManualTransferOutcomeMail($order, 'approved');
        }

        return $order;
    }

    public function rejectManualPayment(TenantSubscriptionOrder $order, ?string $reviewedBy = null, ?string $notes = null): TenantSubscriptionOrder
    {
        $this->assertManualTransferReviewable($order);

        $order->manual_review_status = 'rejected';
        $order->manual_review_notes = $notes ?: 'The submitted transaction reference did not match the transfer received in the selected bank account.';
        $order->manual_reviewed_by = $reviewedBy;
        $order->manual_reviewed_at = now();
        $order->status = 'manual_payment_rejected';
        $order->save();

        $this->sendManualTransferOutcomeMail($order, 'rejected');

        return $order;
    }

    public function toApiPayload(TenantSubscriptionOrder $order): array
    {
        return [
            'id' => $order->id,
            'public_token' => $order->public_token,
            'scope' => $order->scope,
            'status' => $order->status,
            'provider' => $order->provider,
            'payment_channel' => $order->payment_channel,
            'tenant_id' => $order->tenant_id,
            'subscription_id' => $order->subscription_id,
            'tenant_name' => $order->tenant_name,
            'tenant_domain' => $order->tenant_domain,
            'admin_name' => $order->admin_name,
            'admin_email' => $order->admin_email,
            'plan' => $order->plan,
            'business_type' => $order->business_type,
            'billing_phone' => $order->billing_phone,
            'line_items' => $order->line_items ?? [],
            'total_amount_etb' => (float) $order->total_amount_etb,
            'provider_session_id' => $order->provider_session_id,
            'provider_transaction_id' => $order->provider_transaction_id,
            'provider_checkout_url' => $order->provider_checkout_url,
            'manual_payment_bank_account_id' => $order->manual_payment_bank_account_id,
            'manual_payment_bank_account_snapshot' => $order->manual_payment_bank_account_snapshot,
            'manual_payment_reference' => $order->manual_payment_reference,
            'manual_payment_submitted_at' => optional($order->manual_payment_submitted_at)->toIso8601String(),
            'manual_review_status' => $order->manual_review_status,
            'manual_review_notes' => $order->manual_review_notes,
            'manual_reviewed_by' => $order->manual_reviewed_by,
            'manual_reviewed_at' => optional($order->manual_reviewed_at)->toIso8601String(),
            'renewal_term_days' => $order->renewal_term_days,
            'paid_at' => optional($order->paid_at)->toIso8601String(),
            'provisioned_at' => optional($order->provisioned_at)->toIso8601String(),
            'created_at' => optional($order->created_at)->toIso8601String(),
            'module_request' => $order->module_request ?? [],
        ];
    }

    protected function attachCheckoutSession(TenantSubscriptionOrder $order, string $backendBaseUrl, string $successUrl, string $cancelUrl, array $requestedPaymentMethods = []): TenantSubscriptionOrder
    {
        $provider = $this->payments->provider($order->provider ?: $this->payments->activeProvider()->key());

        if (!$provider->implemented()) {
            throw ValidationException::withMessages([
                'payment' => ["{$provider->label()} checkout is not implemented yet."],
            ]);
        }

        if (!$provider->isConfigured()) {
            throw ValidationException::withMessages([
                'payment' => ["{$provider->label()} is not configured yet. Add its required payment settings before checkout."],
            ]);
        }

        if ($provider->requiresBillingPhone() && blank($order->billing_phone)) {
            throw ValidationException::withMessages([
                'billing_phone' => ["{$provider->label()} requires a billing phone number before checkout."],
            ]);
        }

        $checkout = $provider->createCheckoutSession(
            $order,
            $backendBaseUrl,
            $successUrl,
            $cancelUrl,
            $requestedPaymentMethods,
            $this->checkoutItems($order),
        );

        $order->provider = $provider->key();
        $order->payment_channel = 'gateway';
        $order->provider_session_id = $checkout['session_id'] ?? $order->provider_session_id;
        $order->provider_checkout_url = $checkout['checkout_url'] ?? $order->provider_checkout_url;
        $order->provider_payload = $checkout['payload'] ?? [];
        $order->save();

        return $order;
    }

    protected function attachManualTransferSubmission(TenantSubscriptionOrder $order, array $payload): TenantSubscriptionOrder
    {
        if (!$this->payments->directTransferEnabled()) {
            throw ValidationException::withMessages([
                'payment' => ['Direct bank transfer is not configured yet. Add at least one active bank account in Payment Provider Settings.'],
            ]);
        }

        $bankAccountId = trim((string) ($payload['manual_bank_account_id'] ?? ''));
        $reference = trim((string) ($payload['manual_transaction_reference'] ?? ''));

        if ($bankAccountId === '') {
            throw ValidationException::withMessages([
                'manual_bank_account_id' => ['Choose the bank account you transferred the payment to.'],
            ]);
        }

        if ($reference === '') {
            throw ValidationException::withMessages([
                'manual_transaction_reference' => ['Enter the transaction reference from your bank transfer receipt.'],
            ]);
        }

        $account = $this->payments->directTransferAccount($bankAccountId);

        if (!$account || !(bool) ($account['is_active'] ?? false)) {
            throw ValidationException::withMessages([
                'manual_bank_account_id' => ['That bank account is no longer active. Pick another one and submit the transfer again.'],
            ]);
        }

        $order->provider = 'direct_transfer';
        $order->payment_channel = 'direct_transfer';
        $order->status = 'pending_manual_review';
        $order->manual_payment_bank_account_id = (string) $account['id'];
        $order->manual_payment_bank_account_snapshot = $account;
        $order->manual_payment_reference = $reference;
        $order->manual_payment_submitted_at = now();
        $order->manual_review_status = 'pending';
        $order->manual_review_notes = null;
        $order->manual_reviewed_by = null;
        $order->manual_reviewed_at = null;
        $order->provider_checkout_url = null;
        $order->provider_session_id = null;
        $order->provider_transaction_id = null;
        $order->save();

        $this->notifyManualTransferReviewers($order);

        return $order;
    }

    protected function applyProviderStatus(TenantSubscriptionOrder $order, array $result, bool $storeProviderPayload = true): TenantSubscriptionOrder
    {
        if ($storeProviderPayload) {
            $order->provider_status_payload = $result['payload'] ?? $order->provider_status_payload;
        }

        if (!empty($result['transaction_id'])) {
            $order->provider_transaction_id = (string) $result['transaction_id'];
        }

        $status = (string) ($result['status'] ?? 'payment_processing');

        if ($status === 'paid') {
            $order->status = 'paid';
            $order->paid_at = $order->paid_at ?? ($result['paid_at'] ?? now());
            $order->save();

            return $this->finalizePaidOrder($order);
        }

        if (in_array($status, ['failed', 'cancelled', 'payment_processing'], true)) {
            $order->status = $status;
        }

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
                'business_type' => $order->business_type,
                'domain' => $order->tenant_domain,
                'admin_name' => $order->admin_name,
                'admin_email' => $order->admin_email,
                'admin_password' => Crypt::decryptString((string) $order->admin_password_encrypted),
                'module_subscriptions' => $order->module_request ?? [],
            ], $order->admin_email);

            $order->tenant_id = $tenant->id;
            $order->tenant_domain = $tenant->primaryDomain()?->domain ?? $order->tenant_domain;
            $order->subscription_id = TenantSubscription::query()->where('tenant_id', $tenant->id)->value('id');

            // Seed demo data for trial plans
            if (strtolower((string) $order->plan) === 'larva') {
                $this->seedDemoDataForTenant($tenant);
            }
        } elseif ($order->scope === 'tenant_upgrade') {
            $tenant = Tenant::query()->with('domains')->findOrFail($order->tenant_id);
            $requestedModules = TenantModuleCatalog::normalizeRequestedModules(
                $order->module_request['enabled_modules'] ?? []
            );
            $subscription = $this->subscriptions->appendModules($tenant, $requestedModules, $order->admin_email);
            $order->subscription_id = $subscription->id;
        } else {
            $tenant = Tenant::query()->findOrFail($order->tenant_id);
            $subscription = $this->subscriptions->renew($tenant, $order->paid_at ?? now(), $order->admin_email);
            $order->subscription_id = $subscription->id;
        }

        $order->status = 'provisioned';
        $order->provisioned_at = now();
        $order->save();

        return $order->refresh();
    }

    protected function usesManualTransfer(array $payload): bool
    {
        return strtolower((string) ($payload['checkout_channel'] ?? 'gateway')) === 'direct_transfer';
    }

    protected function assertManualTransferReviewable(TenantSubscriptionOrder $order): void
    {
        if ($order->payment_channel !== 'direct_transfer' || $order->status !== 'pending_manual_review') {
            throw ValidationException::withMessages([
                'order' => ['Only pending direct-transfer orders can be reviewed from this screen.'],
            ]);
        }
    }

    protected function sendManualTransferOutcomeMail(TenantSubscriptionOrder $order, string $outcome): void
    {
        if (blank($order->admin_email)) {
            return;
        }

        try {
            Mail::to($order->admin_email)->send(new TenantSubscriptionManualTransferUpdate($order, $outcome));
        } catch (\Throwable $exception) {
            Log::warning('Manual transfer outcome email failed: ' . $exception->getMessage(), [
                'order_id' => $order->id,
                'outcome' => $outcome,
            ]);
        }
    }

    protected function notifyManualTransferReviewers(TenantSubscriptionOrder $order): void
    {
        try {
            $reviewers = User::query()
                ->where('is_active', true)
                ->get()
                ->filter(fn (User $user) => $user->hasAnyPermission([
                    'manage_tenants',
                    'manage_payment_settings',
                    'manage_general_settings',
                ]))
                ->values();

            foreach ($reviewers as $reviewer) {
                $reviewer->notify(new DirectTransferReviewSubmitted($order));
            }
        } catch (\Throwable $exception) {
            Log::warning('Direct transfer reviewer notification failed: ' . $exception->getMessage(), [
                'order_id' => $order->id,
            ]);
        }
    }

    protected function seedDemoDataForTenant(Tenant $tenant): void
    {
        try {
            // Switch to tenant context
            $tenant->makeCurrent();

            // Run the demo data seeder
            $seeder = new \Modules\Subscription\Database\Seeders\DemoDataSeeder();
            $seeder->setCommand($this->command);
            $seeder->run();

            // Restore central context
            \Modules\Tenancy\Tenancy::centralize();

            $this->command?->info("Demo data seeded for trial tenant: {$tenant->id}");
        } catch (\Throwable $exception) {
            Log::warning('Demo data seeding failed: ' . $exception->getMessage(), [
                'tenant_id' => $tenant->id,
                'exception' => $exception,
            ]);
        }
    }
}

