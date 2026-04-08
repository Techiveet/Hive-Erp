<?php

namespace Tests\Unit;

use Modules\Subscription\Models\TenantSubscriptionOrder;
use Modules\Subscription\Payments\ArifPayPaymentProvider;
use Modules\Tenancy\Support\ArifPayGateway;
use PHPUnit\Framework\TestCase;

class ArifPayPaymentProviderTest extends TestCase
{
    public function test_create_checkout_session_formats_phone_and_uses_unique_nonce(): void
    {
        $gateway = $this->createMock(ArifPayGateway::class);
        $provider = new ArifPayPaymentProvider($gateway);
        $order = new TenantSubscriptionOrder([
            'id' => '01HQ7J4Z4Y4X1X5C4VJQ8K3P1N',
            'public_token' => 'public-order-token',
            'billing_phone' => '+251 911 222 333',
            'admin_email' => 'owner@example.com',
            'total_amount_etb' => 499.00,
        ]);

        $gateway->expects($this->once())
            ->method('defaultPaymentMethods')
            ->willReturn(['TELEBIRR_USSD']);

        $gateway->expects($this->once())
            ->method('beneficiariesForAmount')
            ->with(499.0)
            ->willReturn([[
                'accountNumber' => '01320811436100',
                'bank' => 'AWINETAA',
                'amount' => 499.0,
            ]]);

        $gateway->expects($this->once())
            ->method('formatPhoneNumber')
            ->with('+251 911 222 333')
            ->willReturn('251911222333');

        $gateway->expects($this->once())
            ->method('nonceForReference')
            ->with('01HQ7J4Z4Y4X1X5C4VJQ8K3P1N')
            ->willReturn('nonce.example.1712572800000');

        $gateway->expects($this->once())
            ->method('expiryTimestamp')
            ->willReturn('2026-04-08T12:00:00Z');

        $gateway->expects($this->once())
            ->method('createCheckoutSession')
            ->with($this->callback(function (array $payload): bool {
                $this->assertSame('251911222333', $payload['phone']);
                $this->assertSame('nonce.example.1712572800000', $payload['nonce']);
                $this->assertSame('2026-04-08T12:00:00Z', $payload['expireDate']);
                $this->assertSame(['TELEBIRR_USSD'], $payload['paymentMethods']);
                $this->assertSame(
                    'https://backend.test/api/v1/public/subscriptions/orders/public-order-token/notify',
                    $payload['notifyUrl']
                );

                return true;
            }))
            ->willReturn([
                'sessionId' => 'session_123',
                'paymentUrl' => 'https://checkout.arifpay.test/session_123',
            ]);

        $checkout = $provider->createCheckoutSession(
            $order,
            'https://backend.test',
            'https://frontend.test/auth/signup?checkout=public-order-token',
            'https://frontend.test/auth/signup?checkout=public-order-token&cancelled=1',
            [],
            [[
                'name' => 'Business Plan',
                'quantity' => 1,
                'price' => 499.0,
                'description' => 'Workspace subscription',
            ]]
        );

        $this->assertSame('session_123', $checkout['session_id']);
        $this->assertSame('https://checkout.arifpay.test/session_123', $checkout['checkout_url']);
    }

    public function test_ingest_notification_uses_nested_transaction_payload(): void
    {
        $provider = new ArifPayPaymentProvider($this->createMock(ArifPayGateway::class));
        $order = new TenantSubscriptionOrder();

        $result = $provider->ingestNotification($order, [
            'transaction' => [
                'transactionStatus' => 'SUCCESS',
                'transactionId' => 'txn_123',
            ],
        ]);

        $this->assertSame('paid', $result['status']);
        $this->assertSame('txn_123', $result['transaction_id']);
        $this->assertNotNull($result['paid_at']);
    }
}
