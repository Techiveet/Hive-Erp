<?php

namespace Modules\Subscription\Support;

use Modules\Subscription\Contracts\PaymentProvider;
use Modules\Subscription\Payments\ArifPayPaymentProvider;
use Modules\Subscription\Payments\ChapaPaymentProvider;
use Modules\Subscription\Payments\TelebirrPaymentProvider;

class PaymentProviderManager
{
    public function __construct(
        protected PaymentGatewaySettings $settings,
        protected ChapaPaymentProvider $chapa,
        protected ArifPayPaymentProvider $arifpay,
        protected TelebirrPaymentProvider $telebirr,
    ) {
    }

    public function activeProvider(): PaymentProvider
    {
        return $this->provider($this->settings->activeProviderKey());
    }

    public function provider(?string $key = null): PaymentProvider
    {
        return match (strtolower((string) ($key ?? ''))) {
            'arifpay' => $this->arifpay,
            'telebirr' => $this->telebirr,
            default => $this->chapa,
        };
    }

    public function activeProviderPayload(): array
    {
        return $this->providerPayload($this->settings->activeProviderKey());
    }

    public function paymentMethods(?string $providerKey = null): array
    {
        return $this->provider($providerKey ?? $this->settings->activeProviderKey())->paymentMethods();
    }

    public function providerPayload(string $providerKey): array
    {
        $provider = $this->provider($providerKey);
        $config = $this->settings->providerConfig($providerKey);

        return [
            'code' => $provider->key(),
            'label' => $provider->label(),
            'description' => $provider->description(),
            'enabled' => (bool) ($config['enabled'] ?? false),
            'configured' => $provider->isConfigured(),
            'implemented' => $provider->implemented(),
            'supports_payment_methods' => $provider->supportsPaymentMethods(),
            'requires_billing_phone' => $provider->requiresBillingPhone(),
            'payment_methods' => $provider->paymentMethods(),
        ];
    }

    public function publicProvidersPayload(): array
    {
        return collect(array_keys(PaymentGatewaySettings::definitions()))
            ->map(fn (string $key) => $this->providerPayload($key))
            ->values()
            ->all();
    }

    public function directTransferPayload(): array
    {
        return $this->settings->directTransferPublicPayload();
    }

    public function directTransferEnabled(): bool
    {
        return $this->settings->directTransferConfigured();
    }

    public function directTransferAccount(string $id): ?array
    {
        return $this->settings->directTransferAccount($id);
    }
}
