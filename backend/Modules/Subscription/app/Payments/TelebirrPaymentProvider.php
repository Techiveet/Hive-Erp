<?php

namespace Modules\Subscription\Payments;

use Modules\Subscription\Contracts\PaymentProvider;
use Modules\Subscription\Models\TenantSubscriptionOrder;
use Modules\Subscription\Support\PaymentGatewaySettings;
use RuntimeException;

class TelebirrPaymentProvider implements PaymentProvider
{
    public function __construct(
        protected PaymentGatewaySettings $settings,
    ) {
    }

    public function key(): string
    {
        return 'telebirr';
    }

    public function label(): string
    {
        return 'Telebirr';
    }

    public function description(): string
    {
        return 'Telebirr direct API scaffold. Credentials and settings are stored, but the checkout adapter still needs the exact API contract.';
    }

    public function implemented(): bool
    {
        return false;
    }

    public function isConfigured(): bool
    {
        $config = $this->config();

        return filled($config['merchant_app_id'] ?? null)
            && filled($config['fabric_app_id'] ?? null)
            && filled($config['short_code'] ?? null)
            && filled($config['app_secret'] ?? null)
            && filled($config['private_key'] ?? null);
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
        throw new RuntimeException('Telebirr is configured in settings, but its checkout adapter has not been implemented yet.');
    }

    public function syncOrder(TenantSubscriptionOrder $order): array
    {
        throw new RuntimeException('Telebirr order verification is not implemented yet.');
    }

    public function ingestNotification(TenantSubscriptionOrder $order, array $payload, array $headers = []): array
    {
        throw new RuntimeException('Telebirr webhook handling is not implemented yet.');
    }

    protected function config(): array
    {
        return $this->settings->providerConfig($this->key());
    }
}
