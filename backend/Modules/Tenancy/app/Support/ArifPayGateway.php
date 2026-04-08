<?php

namespace Modules\Tenancy\Support;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\Subscription\Support\PaymentGatewaySettings;
use RuntimeException;

class ArifPayGateway
{
    protected const DEFAULT_BENEFICIARY_ACCOUNT = '01320811436100';

    protected const DEFAULT_BENEFICIARY_BANK = 'AWINETAA';

    public function __construct(
        protected PaymentGatewaySettings $settings,
    ) {
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey());
    }

    public function checkoutSessionUrl(): string
    {
        return $this->apiBaseUrl() . '/checkout/session';
    }

    public function createCheckoutSession(array $payload): array
    {
        try {
            $response = Http::acceptJson()
                ->contentType('application/json')
                ->withHeaders([
                    'x-arifpay-key' => $this->apiKey(),
                ])
                ->timeout(30)
                ->post($this->checkoutSessionUrl(), $payload)
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException($this->messageFromFailedRequest($exception), 0, $exception);
        }

        return $this->unwrap($response->json());
    }

    public function fetchCheckoutSession(string $sessionId): array
    {
        try {
            $response = Http::acceptJson()
                ->contentType('application/json')
                ->withHeaders([
                    'x-arifpay-key' => $this->apiKey(),
                ])
                ->timeout(30)
                ->get($this->checkoutSessionUrl() . '/' . urlencode($sessionId))
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException($this->messageFromFailedRequest($exception), 0, $exception);
        }

        return $this->unwrap($response->json());
    }

    public function defaultPaymentMethods(): array
    {
        return collect($this->config()['payment_methods'] ?? [])
            ->map(fn (string $value) => strtoupper(trim($value)))
            ->filter()
            ->values()
            ->all();
    }

    public function expiryTimestamp(): string
    {
        return Carbon::now('UTC')
            ->addMinutes((int) ($this->config()['checkout_expire_minutes'] ?? 30))
            ->format('Y-m-d\TH:i:s\Z');
    }

    public function nonceForReference(?string $reference = null): string
    {
        $random = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
        $timestamp = (string) (int) floor(microtime(true) * 1000);
        $prefix = trim((string) $reference);

        if ($prefix === '') {
            return $random . '.' . $timestamp;
        }

        return $prefix . '.' . $random . '.' . $timestamp;
    }

    public function formatPhoneNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return trim($phone);
        }

        if (str_starts_with($digits, '251')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '251' . substr($digits, 1);
        }

        if (preg_match('/^9\d{8}$/', $digits) === 1) {
            return '251' . $digits;
        }

        return $digits;
    }

    public function beneficiaryAccount(): string
    {
        return $this->firstFilledString([
            env('ARIFPAY_SETTLEMENT_ACCOUNT_NUMBER'),
            env('ARIFPAY_BENEFICIARY_ACCOUNT'),
            self::DEFAULT_BENEFICIARY_ACCOUNT,
        ]);
    }

    public function beneficiaryBank(): string
    {
        return strtoupper($this->firstFilledString([
            env('ARIFPAY_SETTLEMENT_BANK_CODE'),
            env('ARIFPAY_BENEFICIARY_BANK'),
            self::DEFAULT_BENEFICIARY_BANK,
        ]));
    }

    public function beneficiariesForAmount(float $amount): array
    {
        if (! filled($this->beneficiaryAccount()) || ! filled($this->beneficiaryBank())) {
            return [];
        }

        return [[
            'accountNumber' => $this->beneficiaryAccount(),
            'bank' => $this->beneficiaryBank(),
            'amount' => $amount,
        ]];
    }

    protected function apiKey(): string
    {
        return (string) ($this->config()['api_key'] ?? '');
    }

    protected function apiBaseUrl(): string
    {
        $root = rtrim((string) ($this->config()['base_url'] ?? 'https://gateway.arifpay.net'), '/');
        $hasApiPath = (bool) preg_match('#/(?:v\d+|api)(?:/sandbox)?$#', $root);

        if (!$hasApiPath) {
            $root .= '/v0';
        }

        if (
            filter_var($this->config()['sandbox'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && !str_ends_with($root, '/sandbox')
        ) {
            $root .= '/sandbox';
        }

        return $root;
    }

    protected function config(): array
    {
        return $this->settings->providerConfig('arifpay');
    }

    protected function unwrap(array $json): array
    {
        if (($json['error'] ?? null) === true) {
            throw new RuntimeException($this->messageFromPayload($json));
        }

        $data = $json['data'] ?? null;

        if (!is_array($data)) {
            throw new RuntimeException('ArifPay returned an unexpected payload.');
        }

        return $data;
    }

    protected function messageFromFailedRequest(RequestException $exception): string
    {
        $payload = $exception->response?->json();

        if (is_array($payload)) {
            return $this->messageFromPayload($payload);
        }

        return 'ArifPay rejected the checkout request.';
    }

    protected function messageFromPayload(array $payload): string
    {
        $message = $payload['msg'] ?? $payload['message'] ?? $payload['error'] ?? null;

        if (is_array($message)) {
            $message = collect($message)->flatten()->filter()->implode(' ');
        }

        $detailMessage = null;
        $data = $payload['data'] ?? null;

        if (is_array($data)) {
            $detailMessage = collect($data)
                ->map(function ($value, $key) {
                    if (is_array($value)) {
                        $value = collect($value)->flatten()->filter()->implode(' ');
                    }

                    $value = trim((string) $value);

                    if ($value === '') {
                        return null;
                    }

                    return is_string($key) && $key !== ''
                        ? "{$key}: {$value}"
                        : $value;
                })
                ->filter()
                ->implode(' ');
        }

        if (
            filled($detailMessage)
            && (!filled($message) || strcasecmp((string) $message, 'Validation Error') === 0)
        ) {
            return $detailMessage;
        }

        if (filled($message) && !is_bool($message)) {
            return (string) $message;
        }

        return 'ArifPay rejected the checkout request.';
    }

    protected function firstFilledString(array $values): string
    {
        foreach ($values as $value) {
            $stringValue = trim((string) $value);

            if ($stringValue !== '') {
                return $stringValue;
            }
        }

        return '';
    }
}
