<?php

namespace Tests\Unit;

use App\Models\Central\Customer;
use App\Services\Payment\Gateways\MercadoPagoGateway;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MercadoPagoGatewayTest extends TestCase
{
    protected MercadoPagoGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new MercadoPagoGateway([
            'access_token' => 'test_access_token',
            'public_key' => 'test_public_key',
            'sandbox' => true,
        ]);
    }

    #[Test]
    public function it_returns_correct_identifier(): void
    {
        $this->assertEquals('mercadopago', $this->gateway->getIdentifier());
    }

    #[Test]
    public function it_returns_correct_display_name(): void
    {
        $this->assertEquals('MercadoPago', $this->gateway->getDisplayName());
    }

    #[Test]
    public function it_returns_supported_types(): void
    {
        $types = $this->gateway->getSupportedTypes();

        $this->assertContains('card', $types);
        $this->assertContains('pix', $types);
        $this->assertContains('boleto', $types);
        $this->assertContains('debit', $types);
    }

    #[Test]
    public function it_returns_supported_currencies(): void
    {
        $currencies = $this->gateway->getSupportedCurrencies();

        $this->assertContains('BRL', $currencies);
        $this->assertContains('ARS', $currencies);
        $this->assertContains('MXN', $currencies);
    }

    #[Test]
    public function it_is_available_when_access_token_is_set(): void
    {
        $this->assertTrue($this->gateway->isAvailable());
    }

    #[Test]
    public function it_is_not_available_when_access_token_is_empty(): void
    {
        $gateway = new MercadoPagoGateway([
            'access_token' => '',
            'sandbox' => true,
        ]);

        $this->assertFalse($gateway->isAvailable());
    }

    #[Test]
    public function it_creates_pix_charge_successfully(): void
    {
        Http::fake([
            'api.mercadopago.com/*' => Http::response([
                'id' => 123456789,
                'status' => 'pending',
                'point_of_interaction' => [
                    'transaction_data' => [
                        'qr_code' => 'PIX_CODE_123',
                        'qr_code_base64' => 'BASE64_IMAGE',
                        'ticket_url' => 'https://pix.url',
                    ],
                ],
                'date_of_expiration' => '2025-12-31T23:59:59.000-03:00',
            ], 200),
        ]);

        $customer = $this->createTestCustomer();

        $result = $this->gateway->createPixCharge($customer, 10000, [
            'description' => 'Test PIX',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('123456789', $result->providerPaymentId);
        $this->assertEquals('pending', $result->status);
        $this->assertArrayHasKey('pix', $result->providerData);
    }

    #[Test]
    public function it_creates_boleto_charge_successfully(): void
    {
        Http::fake([
            'api.mercadopago.com/*' => Http::response([
                'id' => 123456789,
                'status' => 'pending',
                'barcode' => [
                    'content' => '12345678901234567890123456789012345678901234',
                ],
                'transaction_details' => [
                    'external_resource_url' => 'https://boleto.url',
                ],
                'date_of_expiration' => '2025-12-31',
            ], 200),
        ]);

        $customer = $this->createTestCustomer();

        $result = $this->gateway->createBoletoCharge($customer, 10000, [
            'description' => 'Test Boleto',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('123456789', $result->providerPaymentId);
        $this->assertArrayHasKey('boleto', $result->providerData);
    }

    #[Test]
    public function it_creates_card_charge_successfully(): void
    {
        Http::fake([
            'api.mercadopago.com/*' => Http::response([
                'id' => 123456789,
                'status' => 'approved',
                'payment_method_id' => 'visa',
                'currency_id' => 'BRL',
                'card' => [
                    'last_four_digits' => '1234',
                    'first_six_digits' => '123456',
                    'expiration_month' => 12,
                    'expiration_year' => 2030,
                ],
            ], 200),
        ]);

        $customer = $this->createTestCustomer();

        $result = $this->gateway->createCardCharge($customer, 10000, [
            'card_token' => 'test_card_token',
            'payment_method_id' => 'visa',
            'description' => 'Test Card',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('123456789', $result->providerPaymentId);
        $this->assertEquals('paid', $result->status);
        $this->assertArrayHasKey('card', $result->providerData);
    }

    #[Test]
    public function it_handles_pix_charge_failure(): void
    {
        Http::fake([
            'api.mercadopago.com/*' => Http::response([
                'message' => 'Invalid request',
                'error' => 'bad_request',
            ], 400),
        ]);

        $customer = $this->createTestCustomer();

        $result = $this->gateway->createPixCharge($customer, 10000, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid request', $result->failureMessage);
    }

    #[Test]
    public function it_processes_refund_successfully(): void
    {
        Http::fake([
            'api.mercadopago.com/*' => Http::response([
                'id' => 987654321,
                'amount' => 100.00,
            ], 200),
        ]);

        $payment = new \App\Models\Central\Payment([
            'provider_payment_id' => '123456789',
            'amount' => 10000,
        ]);

        $result = $this->gateway->refund($payment, 10000);

        $this->assertTrue($result->success);
        $this->assertEquals('987654321', $result->providerRefundId);
    }

    #[Test]
    public function it_creates_setup_intent(): void
    {
        $customer = $this->createTestCustomer();

        $result = $this->gateway->createSetupIntent($customer);

        $this->assertTrue($result->success);
        $this->assertStringStartsWith('mp_setup_', $result->providerIntentId);
        $this->assertArrayHasKey('public_key', $result->providerData);
    }

    #[Test]
    public function it_creates_subscription_successfully(): void
    {
        Http::fake([
            'api.mercadopago.com/*' => Http::response([
                'id' => 'preapproval_123',
                'status' => 'authorized',
                'init_point' => 'https://mp.checkout.url',
                'sandbox_init_point' => 'https://sandbox.mp.checkout.url',
            ], 200),
        ]);

        $customer = $this->createTestCustomer();

        $result = $this->gateway->createSubscription($customer, 'plan_pro', [
            'amount' => 9900,
            'description' => 'Pro Plan',
            'interval' => 'month',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('preapproval_123', $result->providerSubscriptionId);
        $this->assertEquals('active', $result->status);
    }

    #[Test]
    public function it_pauses_subscription_successfully(): void
    {
        Http::fake([
            'api.mercadopago.com/*' => Http::response([
                'id' => 'preapproval_123',
                'status' => 'paused',
            ], 200),
        ]);

        $subscription = new \App\Models\Central\Subscription([
            'provider_subscription_id' => 'preapproval_123',
        ]);

        $result = $this->gateway->pauseSubscription($subscription);

        $this->assertTrue($result->success);
        $this->assertEquals('paused', $result->status);
    }

    #[Test]
    public function it_resumes_subscription_successfully(): void
    {
        Http::fake([
            'api.mercadopago.com/*' => Http::response([
                'id' => 'preapproval_123',
                'status' => 'authorized',
            ], 200),
        ]);

        $subscription = new \App\Models\Central\Subscription([
            'provider_subscription_id' => 'preapproval_123',
        ]);

        $result = $this->gateway->resumeSubscription($subscription);

        $this->assertTrue($result->success);
        $this->assertEquals('active', $result->status);
    }

    #[Test]
    public function it_cancels_subscription_successfully(): void
    {
        Http::fake([
            'api.mercadopago.com/*' => Http::response([
                'id' => 'preapproval_123',
                'status' => 'cancelled',
            ], 200),
        ]);

        $subscription = new \App\Models\Central\Subscription([
            'provider_subscription_id' => 'preapproval_123',
        ]);

        $result = $this->gateway->cancelSubscription($subscription);

        $this->assertTrue($result->success);
        $this->assertEquals('canceled', $result->status);
    }

    #[Test]
    public function it_creates_checkout_session(): void
    {
        Http::fake([
            'api.mercadopago.com/*' => Http::response([
                'id' => 'pref_123',
                'init_point' => 'https://mp.checkout.url',
                'sandbox_init_point' => 'https://sandbox.mp.checkout.url',
            ], 200),
        ]);

        $customer = $this->createTestCustomer();

        $url = $this->gateway->createCheckoutSession($customer, 'price_123', [
            'amount' => 9900,
            'description' => 'Test Product',
        ]);

        // In sandbox mode, should return sandbox_init_point
        $this->assertEquals('https://sandbox.mp.checkout.url', $url);
    }

    #[Test]
    public function it_maps_payment_statuses_correctly(): void
    {
        $reflection = new \ReflectionClass($this->gateway);

        $mapPaymentStatus = $reflection->getMethod('mapPaymentStatus');
        $mapPaymentStatus->setAccessible(true);

        $this->assertEquals('paid', $mapPaymentStatus->invoke($this->gateway, 'approved'));
        $this->assertEquals('pending', $mapPaymentStatus->invoke($this->gateway, 'pending'));
        $this->assertEquals('processing', $mapPaymentStatus->invoke($this->gateway, 'authorized'));
        $this->assertEquals('failed', $mapPaymentStatus->invoke($this->gateway, 'rejected'));
        $this->assertEquals('refunded', $mapPaymentStatus->invoke($this->gateway, 'refunded'));
    }

    #[Test]
    public function it_maps_preapproval_statuses_correctly(): void
    {
        $reflection = new \ReflectionClass($this->gateway);

        $mapStatus = $reflection->getMethod('mapPreapprovalStatus');
        $mapStatus->setAccessible(true);

        $this->assertEquals('active', $mapStatus->invoke($this->gateway, 'authorized'));
        $this->assertEquals('pending', $mapStatus->invoke($this->gateway, 'pending'));
        $this->assertEquals('paused', $mapStatus->invoke($this->gateway, 'paused'));
        $this->assertEquals('canceled', $mapStatus->invoke($this->gateway, 'cancelled'));
    }

    protected function createTestCustomer(): Customer
    {
        return new Customer([
            'id' => 'cust_test_123',
            'email' => 'test@example.com',
            'name' => 'Test Customer',
            'tax_id' => '12345678901',
            'phone' => '11999999999',
        ]);
    }
}
