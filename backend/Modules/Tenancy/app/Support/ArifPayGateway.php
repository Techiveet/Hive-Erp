<?php

namespace Modules\Tenancy\Support;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ArifPayGateway
{
    public function isConfigured(): bool
    {
        return filled($this->apiKey())
            && filled($this->beneficiaryAccount())
            && filled($this->beneficiaryBank());
    }

    public function checkoutSessionUrl(): string
    {
        return $this->baseUrl() . '/checkout/session';
    }

    public function createCheckoutSession(array $payload): array
    {
        $response = Http::acceptJson()
            ->contentType('application/json')
            ->withHeaders([
                'x-arifpay-key' => $this->apiKey(),
            ])
            ->timeout(30)
            ->post($this->checkoutSessionUrl(), $payload)
            ->throw();

        return $this->unwrap($response->json());
    }

    public function fetchCheckoutSession(string $sessionId): array
    {
        $response = Http::acceptJson()
            ->contentType('application/json')
            ->withHeaders([
                'x-arifpay-key' => $this->apiKey(),
            ])
            ->timeout(30)
            ->get($this->checkoutSessionUrl() . '/' . urlencode($sessionId))
            ->throw();

        return $this->unwrap($response->json());
    }

    public function defaultPaymentMethods(): array
    {
        $raw = env('ARIFPAY_PAYMENT_METHODS', 'TELEBIRR_USSD,CBE,CARD');

        return collect(explode(',', (string) $raw))
            ->map(fn (string $value) => strtoupper(trim($value)))
            ->filter()
            ->values()
            ->all();
    }

    public function expiryTimestamp(): string
    {
        return Carbon::now()->addMinutes((int) env('ARIFPAY_CHECKOUT_EXPIRE_MINUTES', 30))->toIso8601String();
    }

    public function beneficiaryAccount(): string
    {
        return (string) env('ARIFPAY_BENEFICIARY_ACCOUNT', '');
    }

    public function beneficiaryBank(): string
    {
        return strtoupper((string) env('ARIFPAY_BENEFICIARY_BANK', 'AWINETAA'));
    }

    protected function apiKey(): string
    {
        return (string) env('ARIFPAY_API_KEY', '');
    }

    protected function baseUrl(): string
    {
        $root = rtrim((string) env('ARIFPAY_BASE_URL', 'https://gateway.arifpay.net'), '/');
        $version = '/v0';
        $sandboxPrefix = filter_var(env('ARIFPAY_SANDBOX', false), FILTER_VALIDATE_BOOLEAN) ? '/sandbox' : '';

        return $root . $version . $sandboxPrefix;
    }

    protected function unwrap(array $json): array
    {
        if (($json['error'] ?? null) === true) {
            throw new RuntimeException((string) ($json['msg'] ?? 'ArifPay rejected the request.'));
        }

        $data = $json['data'] ?? null;

        if (!is_array($data)) {
            throw new RuntimeException('ArifPay returned an unexpected payload.');
        }

        return $data;
    }
}
