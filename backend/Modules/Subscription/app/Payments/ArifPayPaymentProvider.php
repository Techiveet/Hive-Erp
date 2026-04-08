<?php

namespace Modules\Subscription\Payments;

use Modules\Subscription\Contracts\PaymentProvider;
use Modules\Subscription\Models\TenantSubscriptionOrder;
use Modules\Tenancy\Support\ArifPayGateway;
use RuntimeException;

class ArifPayPaymentProvider implements PaymentProvider
{
    public function __construct(
        protected ArifPayGateway $gateway,
    ) {
    }

    public function key(): string
    {
        return 'arifpay';
    }

    public function label(): string
    {
        return 'ArifPay';
    }

    public function description(): string
    {
        return 'Hosted checkout through ArifPay with Ethiopian bank and wallet payment methods.';
    }

    public function implemented(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return $this->gateway->isConfigured();
    }

    public function supportsPaymentMethods(): bool
    {
        return true;
    }

    public function requiresBillingPhone(): bool
    {
        return true;
    }

    public function paymentMethods(): array
    {
        return collect($this->gateway->defaultPaymentMethods())
            ->map(fn (string $code) => [
                'code' => $code,
                'label' => match ($code) {
                'TELEBIRR_USSD' => 'Telebirr',
                'CBE' => 'Commercial Bank of Ethiopia',
                'AWASH_BIRR' => 'Awash Birr',
                'AMOLE' => 'Amole',
                'ZAMZAM' => 'ZamZam Bank',
                'CARD' => 'Card',
                default => ucwords(str_replace('_', ' ', strtolower($code))),
            },
            ])
            ->values()
            ->all();
    }

    public function createCheckoutSession(
        TenantSubscriptionOrder $order,
        string $backendBaseUrl,
        string $successUrl,
        string $cancelUrl,
        array $requestedPaymentMethods = [],
        array $checkoutItems = [],
    ): array {
        $paymentMethods = collect($requestedPaymentMethods)
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->values()
            ->all();

        if ($paymentMethods === []) {
            $paymentMethods = $this->gateway->defaultPaymentMethods();
        }

        $beneficiaries = $this->gateway->beneficiariesForAmount((float) $order->total_amount_etb);

        $payload = [
            'cancelUrl' => $cancelUrl,
            'phone' => $this->gateway->formatPhoneNumber((string) $order->billing_phone),
            'email' => $order->admin_email,
            'nonce' => $this->gateway->nonceForReference($order->id),
            'errorUrl' => $cancelUrl,
            'notifyUrl' => rtrim($backendBaseUrl, '/') . '/api/v1/public/subscriptions/orders/' . $order->public_token . '/notify',
            'successUrl' => $successUrl,
            'paymentMethods' => $paymentMethods,
            'expireDate' => $this->gateway->expiryTimestamp(),
            'items' => $checkoutItems,
            'lang' => 'EN',
        ];

        if ($beneficiaries !== []) {
            $payload['beneficiaries'] = $beneficiaries;
        }

        $checkout = $this->gateway->createCheckoutSession($payload);
        $sessionId = (string) ($checkout['sessionId'] ?? $checkout['session_id'] ?? '');
        $checkoutUrl = urldecode((string) ($checkout['paymentUrl'] ?? $checkout['payment_url'] ?? ''));

        if ($checkoutUrl === '') {
            throw new RuntimeException('ArifPay did not return a hosted checkout URL.');
        }

        return [
            'session_id' => $sessionId,
            'checkout_url' => $checkoutUrl,
            'payload' => $checkout,
        ];
    }

    public function syncOrder(TenantSubscriptionOrder $order): array
    {
        $session = $this->gateway->fetchCheckoutSession((string) $order->provider_session_id);
        $transaction = $session['transaction'] ?? [];
        $status = $this->mapStatus((string) ($transaction['transactionStatus'] ?? 'PENDING'));

        return [
            'status' => $status,
            'transaction_id' => $transaction['transactionId'] ?? $session['transactionId'] ?? null,
            'paid_at' => $status === 'paid' ? now() : null,
            'payload' => $session,
        ];
    }

    public function ingestNotification(TenantSubscriptionOrder $order, array $payload, array $headers = []): array
    {
        $root = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $transaction = is_array($root['transaction'] ?? null) ? $root['transaction'] : [];
        $status = $this->mapStatus((string) ($transaction['transactionStatus'] ?? $root['transactionStatus'] ?? $root['status'] ?? ''));

        return [
            'status' => $status,
            'transaction_id' => $transaction['transactionId'] ?? $root['transactionId'] ?? $root['transaction_id'] ?? null,
            'paid_at' => $status === 'paid' ? now() : null,
            'payload' => $payload,
        ];
    }

    protected function mapStatus(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'SUCCESS', 'COMPLETED', 'PAID' => 'paid',
            'FAILED', 'DECLINED' => 'failed',
            'CANCELLED', 'CANCELED' => 'cancelled',
            default => 'payment_processing',
        };
    }
}
