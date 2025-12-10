<?php

namespace Tests\Unit;

use App\Models\Central\Customer;
use App\Services\Payment\Gateways\PagSeguroGateway;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PagSeguroGatewayTest extends TestCase
{
    protected PagSeguroGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new PagSeguroGateway([
            'api_key' => 'test_api_key',
            'public_key' => 'test_public_key',
            'sandbox' => true,
        ]);
    }

    #[Test]
    public function it_returns_correct_identifier(): void
    {
        $this->assertEquals('pagseguro', $this->gateway->getIdentifier());
    }

    #[Test]
    public function it_returns_correct_display_name(): void
    {
        $this->assertEquals('PagSeguro', $this->gateway->getDisplayName());
    }

    #[Test]
    public function it_returns_supported_types(): void
    {
        $types = $this->gateway->getSupportedTypes();

        $this->assertContains('card', $types);
        $this->assertContains('pix', $types);
        $this->assertContains('boleto', $types);
    }

    #[Test]
    public function it_returns_supported_currencies(): void
    {
        $currencies = $this->gateway->getSupportedCurrencies();

        $this->assertContains('BRL', $currencies);
    }

    #[Test]
    public function it_is_available_when_api_key_is_set(): void
    {
        $this->assertTrue($this->gateway->isAvailable());
    }

    #[Test]
    public function it_is_not_available_when_api_key_is_empty(): void
    {
        $gateway = new PagSeguroGateway([
            'api_key' => '',
            'sandbox' => true,
        ]);

        $this->assertFalse($gateway->isAvailable());
    }

    #[Test]
    public function it_creates_pix_charge_successfully(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/*' => Http::response([
                'id' => 'ORDER_123',
                'status' => 'PENDING',
                'qr_codes' => [
                    [
                        'text' => 'PIX_CODE_123',
                        'links' => [['href' => 'https://pix.url']],
                        'expiration_date' => '2025-12-31T23:59:59',
                    ],
                ],
            ], 200),
        ]);

        $customer = $this->createTestCustomer();

        $result = $this->gateway->createPixCharge($customer, 10000, [
            'description' => 'Test PIX',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('ORDER_123', $result->providerPaymentId);
        $this->assertEquals('pending', $result->status);
        $this->assertArrayHasKey('pix', $result->providerData);
    }

    #[Test]
    public function it_creates_boleto_charge_successfully(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/*' => Http::response([
                'id' => 'ORDER_123',
                'charges' => [
                    [
                        'id' => 'CHARGE_123',
                        'status' => 'WAITING',
                        'payment_method' => [
                            'boleto' => [
                                'barcode' => '12345678901234567890123456789012345678901234',
                                'formatted_barcode' => '12345.67890 12345.678901 23456.789012 3 45678901234567',
                            ],
                        ],
                        'links' => [
                            ['href' => 'https://boleto.pdf', 'media' => 'application/pdf'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $customer = $this->createTestCustomer();

        $result = $this->gateway->createBoletoCharge($customer, 10000, [
            'description' => 'Test Boleto',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('ORDER_123', $result->providerPaymentId);
        $this->assertArrayHasKey('boleto', $result->providerData);
    }

    #[Test]
    public function it_creates_card_charge_successfully(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/*' => Http::response([
                'id' => 'ORDER_123',
                'charges' => [
                    [
                        'id' => 'CHARGE_123',
                        'status' => 'PAID',
                        'payment_method' => [
                            'card' => ['brand' => 'VISA'],
                        ],
                        'payment_response' => [
                            'raw_data' => ['card_last_digits' => '1234'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $customer = $this->createTestCustomer();

        $result = $this->gateway->createCardCharge($customer, 10000, [
            'card_token' => 'test_card_token',
            'description' => 'Test Card',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('ORDER_123', $result->providerPaymentId);
        $this->assertEquals('paid', $result->status);
    }

    #[Test]
    public function it_handles_pix_charge_failure(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/*' => Http::response([
                'error_messages' => [
                    ['description' => 'Invalid request'],
                ],
            ], 400),
        ]);

        $customer = $this->createTestCustomer();

        $result = $this->gateway->createPixCharge($customer, 10000, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid request', $result->failureMessage);
    }

    #[Test]
    public function it_creates_setup_intent(): void
    {
        $customer = $this->createTestCustomer();

        $result = $this->gateway->createSetupIntent($customer);

        $this->assertTrue($result->success);
        $this->assertStringStartsWith('pagseguro_setup_', $result->providerIntentId);
        $this->assertArrayHasKey('public_key', $result->providerData);
    }

    #[Test]
    public function it_handles_webhook_validation(): void
    {
        // PagSeguro validates webhooks by notification_code presence
        $this->assertTrue($this->gateway->validateWebhookSignature('{}', 'notification_code_123'));
        $this->assertFalse($this->gateway->validateWebhookSignature('{}', ''));
    }

    #[Test]
    public function it_maps_statuses_correctly(): void
    {
        $reflection = new \ReflectionClass($this->gateway);

        $mapOrderStatus = $reflection->getMethod('mapOrderStatus');
        $mapOrderStatus->setAccessible(true);

        $this->assertEquals('paid', $mapOrderStatus->invoke($this->gateway, 'PAID'));
        $this->assertEquals('processing', $mapOrderStatus->invoke($this->gateway, 'AUTHORIZED'));
        $this->assertEquals('pending', $mapOrderStatus->invoke($this->gateway, 'PENDING'));
        $this->assertEquals('failed', $mapOrderStatus->invoke($this->gateway, 'DECLINED'));
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
