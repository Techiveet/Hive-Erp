<?php

namespace Modules\Subscription\Payments;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Subscription\Contracts\PaymentProvider;
use Modules\Subscription\Models\TenantSubscriptionOrder;
use Modules\Subscription\Support\PaymentGatewaySettings;
use RuntimeException;

class ChapaPaymentProvider implements PaymentProvider
{
    public function __construct(
        protected PaymentGatewaySettings $settings,
    ) {
    }

    public function key(): string
    {
        return 'chapa';
    }

    public function label(): string
    {
        return 'Chapa';
    }

    public function description(): string
    {
        return 'Hosted checkout through Chapa with callback and transaction verification.';
    }

    public function implemented(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return filled($this->config()['secret_key'] ?? null);
    }

    public function supportsPaymentMethods(): bool
    {
        return false;
    }

    public function requiresBillingPhone(): bool
    {
        return false;
    }

    public function paymentMethods(): array
    {
        return [];
    }

    public function createCheckoutSession(
        TenantSubscriptionOrder $order,
        string $backendBaseUrl,
        string $successUrl,
        string $cancelUrl,
        array $requestedPaymentMethods = [],
        array $checkoutItems = [],
    ): array {
        $adminName = trim((string) ($order->admin_name ?: $order->tenant_name ?: 'Hive Operator'));
        [$firstName, $lastName] = $this->splitName($adminName);

        $payload = [
            'amount' => number_format((float) $order->total_amount_etb, 2, '.', ''),
            'currency' => $order->currency ?: 'ETB',
            'email' => (string) $order->admin_email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'tx_ref' => $this->referenceFor($order),
            'callback_url' => rtrim($backendBaseUrl, '/') . '/api/v1/public/subscriptions/orders/' . $order->public_token . '/notify',
            'return_url' => $successUrl,
            'customization' => [
                'title' => 'HIVE.OS Subscription Checkout',
                'description' => $this->checkoutDescription($order),
            ],
            'meta' => [
                'payment_reason' => $this->checkoutDescription($order),
                'hide_receipt' => false,
                'disable_phone_edit' => false,
                'custom_receipt_enabled' => false,
                'invoices' => collect($checkoutItems)
                    ->map(fn (array $item) => [
                        'key' => $item['name'],
                        'value' => $item['description'],
                    ])
                    ->values()
                    ->all(),
            ],
        ];

        if (filled($order->billing_phone)) {
            $payload['phone_number'] = $this->normalizePhone((string) $order->billing_phone);
        }

        $logoUrl = trim((string) ($this->config()['logo_url'] ?? ''));
        if ($logoUrl !== '') {
            $payload['customization']['logo'] = $logoUrl;
        }

        $response = Http::acceptJson()
            ->contentType('application/json')
            ->withToken($this->secretKey())
            ->timeout(30)
            ->post($this->baseUrl() . '/v1/transaction/initialize', $payload)
            ->throw()
            ->json();

        if (($response['status'] ?? null) !== 'success' || !is_array($response['data'] ?? null)) {
            throw new RuntimeException((string) ($response['message'] ?? 'Chapa rejected the checkout request.'));
        }

        return [
            'session_id' => $this->referenceFor($order),
            'checkout_url' => (string) ($response['data']['checkout_url'] ?? ''),
            'payload' => $response,
        ];
    }

    public function syncOrder(TenantSubscriptionOrder $order): array
    {
        $reference = $this->referenceFor($order);
        $response = $this->verifyReference($reference);
        $transaction = is_array($response['data'] ?? null) ? $response['data'] : [];
        $status = $this->mapStatus((string) ($transaction['status'] ?? $response['status'] ?? 'pending'));

        return [
            'status' => $status,
            'transaction_id' => $transaction['reference'] ?? $transaction['ref_id'] ?? $transaction['id'] ?? null,
            'paid_at' => $status === 'paid'
                ? $this->timestampFrom($transaction['created_at'] ?? null)
                : null,
            'payload' => $response,
        ];
    }

    public function ingestNotification(TenantSubscriptionOrder $order, array $payload, array $headers = []): array
    {
        $this->verifyWebhookSignatureIfPresent($payload, $headers);

        $reference = trim((string) ($payload['trx_ref'] ?? $payload['tx_ref'] ?? $order->provider_session_id ?? $order->id));

        if ($reference !== '') {
            return $this->syncOrder($order->forceFill([
                'provider_session_id' => $reference,
            ]));
        }

        $status = $this->mapStatus((string) ($payload['status'] ?? 'pending'));

        return [
            'status' => $status,
            'transaction_id' => $payload['ref_id'] ?? $payload['reference'] ?? null,
            'paid_at' => $status === 'paid' ? now() : null,
            'payload' => $payload,
        ];
    }

    protected function verifyReference(string $reference): array
    {
        return Http::acceptJson()
            ->withToken($this->secretKey())
            ->timeout(30)
            ->get($this->baseUrl() . '/v1/transaction/verify/' . urlencode($reference))
            ->throw()
            ->json();
    }

    protected function verifyWebhookSignatureIfPresent(array $payload, array $headers = []): void
    {
        $secret = trim((string) ($this->config()['webhook_secret'] ?? ''));

        if ($secret === '') {
            return;
        }

        $received = $this->extractSignature($headers);
        if ($received === null) {
            return;
        }

        $expected = hash_hmac('sha256', json_encode($payload), $secret);

        if (!hash_equals($expected, $received)) {
            throw new RuntimeException('Chapa webhook signature validation failed.');
        }
    }

    protected function extractSignature(array $headers): ?string
    {
        foreach (['x-chapa-signature', 'chapa-signature'] as $candidate) {
            $value = $headers[$candidate] ?? $headers[Str::title($candidate)] ?? null;

            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            if (filled($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    protected function referenceFor(TenantSubscriptionOrder $order): string
    {
        return (string) ($order->provider_session_id ?: $order->id);
    }

    protected function checkoutDescription(TenantSubscriptionOrder $order): string
    {
        return match ($order->scope) {
            'tenant_renewal' => "Renew subscription for {$order->tenant_name}",
            'tenant_upgrade' => "Upgrade modules for {$order->tenant_name}",
            default => "Provision {$order->tenant_name} on the {$order->plan} plan",
        };
    }

    protected function splitName(string $value): array
    {
        $parts = collect(preg_split('/\s+/', trim($value)) ?: [])
            ->filter()
            ->values();

        $first = $parts->first() ?: 'Hive';
        $last = $parts->skip(1)->implode(' ');

        return [$first, $last !== '' ? $last : 'Operator'];
    }

    protected function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (Str::startsWith($digits, '251') && strlen($digits) >= 12) {
            return '0' . substr($digits, 3);
        }

        return $digits;
    }

    protected function mapStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'success', 'completed', 'paid' => 'paid',
            'failed', 'error' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            default => 'payment_processing',
        };
    }

    protected function timestampFrom(mixed $value): mixed
    {
        if (!filled($value)) {
            return now();
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return now();
        }
    }

    protected function baseUrl(): string
    {
        return rtrim((string) ($this->config()['base_url'] ?? 'https://api.chapa.co'), '/');
    }

    protected function secretKey(): string
    {
        return trim((string) ($this->config()['secret_key'] ?? ''));
    }

    protected function config(): array
    {
        return $this->settings->providerConfig($this->key());
    }
}
