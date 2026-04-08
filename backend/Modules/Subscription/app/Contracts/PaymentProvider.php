<?php

namespace Modules\Subscription\Contracts;

use Modules\Subscription\Models\TenantSubscriptionOrder;

interface PaymentProvider
{
    public function key(): string;

    public function label(): string;

    public function description(): string;

    public function implemented(): bool;

    public function isConfigured(): bool;

    public function supportsPaymentMethods(): bool;

    public function requiresBillingPhone(): bool;

    /**
     * @return array<int, array{code:string,label:string}>
     */
    public function paymentMethods(): array;

    /**
     * @param array<int, string> $requestedPaymentMethods
     * @param array<int, array{name:string,quantity:int,price:float,description:string}> $checkoutItems
     * @return array{session_id:?string,checkout_url:?string,payload:array}
     */
    public function createCheckoutSession(
        TenantSubscriptionOrder $order,
        string $backendBaseUrl,
        string $successUrl,
        string $cancelUrl,
        array $requestedPaymentMethods = [],
        array $checkoutItems = [],
    ): array;

    /**
     * @return array{status:string,transaction_id:?string,paid_at:mixed,payload:array}
     */
    public function syncOrder(TenantSubscriptionOrder $order): array;

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     * @return array{status:string,transaction_id:?string,paid_at:mixed,payload:array}
     */
    public function ingestNotification(TenantSubscriptionOrder $order, array $payload, array $headers = []): array;
}
