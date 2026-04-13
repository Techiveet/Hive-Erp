<?php

namespace Tests\Unit;

use Modules\Subscription\Support\PaymentGatewaySettings;
use Modules\Tenancy\Support\ArifPayGateway;
use PHPUnit\Framework\TestCase;

class ArifPayGatewayTest extends TestCase
{
    public function test_is_configured_only_requires_api_key(): void
    {
        $settings = $this->createMock(PaymentGatewaySettings::class);
        $settings->expects($this->once())
            ->method('providerConfig')
            ->with('arifpay')
            ->willReturn([
                'api_key' => 'arifpay_live_key',
                'base_url' => 'https://gateway.arifpay.net',
            ]);

        $gateway = new ArifPayGateway($settings);

        $this->assertTrue($gateway->isConfigured());
    }

    public function test_checkout_session_url_defaults_to_documented_api_path(): void
    {
        $settings = $this->createMock(PaymentGatewaySettings::class);
        $settings->method('providerConfig')
            ->with('arifpay')
            ->willReturn([
                'api_key' => 'arifpay_live_key',
                'base_url' => 'https://gateway.arifpay.net',
                'sandbox' => false,
            ]);

        $gateway = new ArifPayGateway($settings);

        $this->assertSame(
            'https://gateway.arifpay.net/api/checkout/session',
            $gateway->checkoutSessionUrl()
        );
    }

    public function test_it_normalizes_supported_ethiopian_mobile_formats(): void
    {
        $settings = $this->createMock(PaymentGatewaySettings::class);
        $settings->method('providerConfig')
            ->with('arifpay')
            ->willReturn([
                'api_key' => 'arifpay_live_key',
                'base_url' => 'https://gateway.arifpay.net',
            ]);

        $gateway = new ArifPayGateway($settings);

        $this->assertSame('251953912525', $gateway->formatPhoneNumber('953912525'));
        $this->assertSame('251953912525', $gateway->formatPhoneNumber('0953912525'));
        $this->assertSame('251953912525', $gateway->formatPhoneNumber('+251953912525'));
    }

    public function test_it_uses_default_beneficiary_details_when_settings_are_blank(): void
    {
        $settings = $this->createMock(PaymentGatewaySettings::class);
        $settings->method('providerConfig')
            ->with('arifpay')
            ->willReturn([
                'api_key' => 'arifpay_live_key',
                'base_url' => 'https://gateway.arifpay.net',
            ]);

        $gateway = new ArifPayGateway($settings);

        $this->assertSame([
            [
                'accountNumber' => '01320811436100',
                'bank' => 'AWINETAA',
                'amount' => 499.0,
            ],
        ], $gateway->beneficiariesForAmount(499.0));
    }

    public function test_it_ignores_stored_settlement_fields_for_arifpay_beneficiaries(): void
    {
        $settings = $this->createMock(PaymentGatewaySettings::class);
        $settings->method('providerConfig')
            ->with('arifpay')
            ->willReturn([
                'api_key' => 'arifpay_live_key',
                'base_url' => 'https://gateway.arifpay.net',
                'settlement_account_number' => '1000530784243',
                'settlement_bank_code' => 'AWASH',
                'beneficiary_account' => '99999999999999',
                'beneficiary_bank' => 'ZZZZZZAA',
            ]);

        $gateway = new ArifPayGateway($settings);

        $this->assertSame([
            [
                'accountNumber' => '01320811436100',
                'bank' => 'AWINETAA',
                'amount' => 1.0,
            ],
        ], $gateway->beneficiariesForAmount(1.0));
    }
}
