<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Events\Payment\PaymentConfirmed;
use App\Events\Payment\PaymentFailed;
use App\Models\Central\AddonPurchase;
use App\Models\Central\Customer;
use App\Services\Payment\Gateways\AsaasGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Asaas Gateway Test Suite
 *
 * Tests the Asaas payment gateway implementation for:
 * - PIX payments (QR code generation, retrieval)
 * - Boleto payments (barcode, identification field)
 * - Webhook handling for async payments
 */
class AsaasGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected AsaasGateway $gateway;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new AsaasGateway([
            'api_key' => 'test_api_key',
            'sandbox' => true,
        ]);

        // Create a customer with Asaas provider ID
        $this->customer = Customer::factory()->create([
            'provider_ids' => ['asaas' => 'cus_test_123'],
        ]);
    }

    // =========================================================================
    // Gateway Configuration Tests
    // =========================================================================

    #[Test]
    public function gateway_returns_correct_identifier(): void
    {
        $this->assertEquals('asaas', $this->gateway->getIdentifier());
    }

    #[Test]
    public function gateway_returns_correct_display_name(): void
    {
        $this->assertEquals('Asaas', $this->gateway->getDisplayName());
    }

    #[Test]
    public function gateway_supports_pix_boleto_and_card(): void
    {
        $types = $this->gateway->getSupportedTypes();

        $this->assertContains('card', $types);
        $this->assertContains('pix', $types);
        $this->assertContains('boleto', $types);
    }

    #[Test]
    public function gateway_supports_brl_currency(): void
    {
        $currencies = $this->gateway->getSupportedCurrencies();

        $this->assertContains('BRL', $currencies);
    }

    #[Test]
    public function gateway_is_available_when_api_key_is_set(): void
    {
        $this->assertTrue($this->gateway->isAvailable());
    }

    #[Test]
    public function gateway_is_not_available_when_api_key_is_empty(): void
    {
        $gateway = new AsaasGateway(['api_key' => '']);

        $this->assertFalse($gateway->isAvailable());
    }

    // =========================================================================
    // PIX Charge Tests
    // =========================================================================

    #[Test]
    public function create_pix_charge_returns_success_with_qr_code(): void
    {
        Http::fake([
            // Payment creation
            'api-sandbox.asaas.com/v3/payments' => Http::response([
                'id' => 'pay_test_pix_123',
                'status' => 'PENDING',
                'billingType' => 'PIX',
                'value' => 99.90,
                'dueDate' => '2025-12-15',
                'invoiceUrl' => 'https://asaas.com/invoice/123',
            ], 200),
            // QR code retrieval
            'api-sandbox.asaas.com/v3/payments/pay_test_pix_123/pixQrCode' => Http::response([
                'encodedImage' => 'base64_qr_code_image_data',
                'payload' => '00020126580014br.gov.bcb.pix0136example-pix-key',
                'expirationDate' => '2025-12-15T23:59:59Z',
            ], 200),
        ]);

        $result = $this->gateway->createPixCharge($this->customer, 9990, [
            'description' => 'Test PIX Payment',
            'reference' => 'addon_purchase_123',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('pay_test_pix_123', $result->providerPaymentId);
        $this->assertEquals('pending', $result->status);
        $this->assertEquals('PIX', $result->providerData['billing_type']);

        // Check PIX data
        $this->assertNotNull($result->providerData['pix']);
        $this->assertEquals('base64_qr_code_image_data', $result->providerData['pix']['qr_code']);
        $this->assertStringContains('pix', $result->providerData['pix']['copy_paste']);
        $this->assertNotNull($result->providerData['pix']['expiration']);
    }

    #[Test]
    public function create_pix_charge_handles_api_failure(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments' => Http::response([
                'errors' => [
                    ['description' => 'Invalid customer'],
                ],
            ], 400),
        ]);

        $result = $this->gateway->createPixCharge($this->customer, 9990);

        $this->assertFalse($result->success);
        $this->assertEquals('Invalid customer', $result->failureMessage);
    }

    #[Test]
    public function get_pix_qr_code_retrieves_existing_payment_qr(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments/pay_existing_123/pixQrCode' => Http::response([
                'encodedImage' => 'refreshed_qr_code_data',
                'payload' => '00020126580014br.gov.bcb.pix',
                'expirationDate' => '2025-12-20T23:59:59Z',
            ], 200),
        ]);

        $result = $this->gateway->getPixQrCode('pay_existing_123');

        $this->assertNotNull($result);
        $this->assertEquals('refreshed_qr_code_data', $result['qr_code']);
        $this->assertEquals('refreshed_qr_code_data', $result['qr_code_base64']);
        $this->assertNotNull($result['copy_paste']);
        $this->assertNotNull($result['expiration_date']);
    }

    #[Test]
    public function get_pix_qr_code_returns_null_on_failure(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments/invalid_123/pixQrCode' => Http::response([
                'errors' => [['description' => 'Payment not found']],
            ], 404),
        ]);

        $result = $this->gateway->getPixQrCode('invalid_123');

        $this->assertNull($result);
    }

    // =========================================================================
    // Boleto Charge Tests
    // =========================================================================

    #[Test]
    public function create_boleto_charge_returns_success_with_barcode(): void
    {
        Http::fake([
            // Payment creation
            'api-sandbox.asaas.com/v3/payments' => Http::response([
                'id' => 'pay_test_boleto_123',
                'status' => 'PENDING',
                'billingType' => 'BOLETO',
                'value' => 149.90,
                'dueDate' => '2025-12-18',
                'bankSlipUrl' => 'https://asaas.com/boleto/123',
                'nossoNumero' => '1234567890',
                'invoiceUrl' => 'https://asaas.com/invoice/123',
            ], 200),
            // Identification field retrieval
            'api-sandbox.asaas.com/v3/payments/pay_test_boleto_123/identificationField' => Http::response([
                'identificationField' => '23793.38128 60000.000003 00000.000406 1 84660000014990',
                'barCode' => '23791846600000149909381286000000000000000040',
            ], 200),
        ]);

        $result = $this->gateway->createBoletoCharge($this->customer, 14990, [
            'description' => 'Test Boleto Payment',
            'reference' => 'addon_purchase_456',
            'due_date' => '2025-12-18',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('pay_test_boleto_123', $result->providerPaymentId);
        $this->assertEquals('pending', $result->status);
        $this->assertEquals('BOLETO', $result->providerData['billing_type']);

        // Check Boleto data
        $this->assertNotNull($result->providerData['boleto']);
        $this->assertEquals('https://asaas.com/boleto/123', $result->providerData['boleto']['url']);
        $this->assertEquals('1234567890', $result->providerData['boleto']['nosso_numero']);
        $this->assertNotNull($result->providerData['boleto']['digitable_line']);
        $this->assertNotNull($result->providerData['boleto']['barcode']);
    }

    #[Test]
    public function create_boleto_charge_includes_fine_and_interest(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments' => Http::response([
                'id' => 'pay_test_boleto_fine',
                'status' => 'PENDING',
                'billingType' => 'BOLETO',
                'value' => 100.00,
                'dueDate' => '2025-12-20',
            ], 200),
            'api-sandbox.asaas.com/v3/payments/*/identificationField' => Http::response([
                'identificationField' => '12345',
                'barCode' => '12345',
            ], 200),
        ]);

        $result = $this->gateway->createBoletoCharge($this->customer, 10000, [
            'fine_value' => 2,
            'interest_value' => 1,
        ]);

        $this->assertTrue($result->success);

        // Verify the request included fine and interest
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->url() === 'https://api-sandbox.asaas.com/v3/payments'
                && isset($body['fine'])
                && $body['fine']['value'] === 2
                && $body['fine']['type'] === 'PERCENTAGE'
                && isset($body['interest'])
                && $body['interest']['value'] === 1;
        });
    }

    #[Test]
    public function get_boleto_identification_field_retrieves_existing(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments/pay_boleto_456/identificationField' => Http::response([
                'identificationField' => '23793.38128 60000.000003 00000.000406 1 84660000014990',
                'barCode' => '23791846600000149909381286000000000000000040',
            ], 200),
        ]);

        $result = $this->gateway->getBoletoIdentificationField('pay_boleto_456');

        $this->assertNotNull($result);
        $this->assertNotNull($result['identification_field']);
        $this->assertNotNull($result['digitable_line']);
        $this->assertNotNull($result['barcode']);
        $this->assertNotNull($result['bar_code']);
    }

    // =========================================================================
    // Webhook Handling Tests
    // =========================================================================

    #[Test]
    public function webhook_handler_dispatches_payment_confirmed_event(): void
    {
        Event::fake([PaymentConfirmed::class]);

        // Create a pending purchase (let factory generate UUID)
        $purchase = AddonPurchase::factory()->pending()->create();

        $payload = [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay_asaas_123',
                'externalReference' => $purchase->id,
                'billingType' => 'PIX',
                'value' => 99.90,
                'netValue' => 97.90,
                'paymentDate' => '2025-12-10',
                'confirmedDate' => '2025-12-10',
            ],
        ];

        $this->gateway->handleWebhook($payload);

        Event::assertDispatched(PaymentConfirmed::class, function ($event) use ($purchase) {
            return $event->purchase->id === $purchase->id
                && $event->provider === 'asaas'
                && $event->paymentIntentId === 'pay_asaas_123';
        });
    }

    #[Test]
    public function webhook_handler_dispatches_payment_received_event(): void
    {
        Event::fake([PaymentConfirmed::class]);

        $purchase = AddonPurchase::factory()->pending()->create();

        $payload = [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_asaas_456',
                'externalReference' => $purchase->id,
                'billingType' => 'BOLETO',
                'value' => 149.90,
            ],
        ];

        $this->gateway->handleWebhook($payload);

        Event::assertDispatched(PaymentConfirmed::class);
    }

    #[Test]
    public function webhook_handler_dispatches_payment_failed_on_overdue(): void
    {
        Event::fake([PaymentFailed::class]);

        $purchase = AddonPurchase::factory()->pending()->create();

        $payload = [
            'event' => 'PAYMENT_OVERDUE',
            'payment' => [
                'id' => 'pay_asaas_789',
                'externalReference' => $purchase->id,
                'billingType' => 'PIX',
                'dueDate' => '2025-12-05',
            ],
        ];

        $this->gateway->handleWebhook($payload);

        Event::assertDispatched(PaymentFailed::class, function ($event) {
            return str_contains($event->reason, 'vencido');
        });
    }

    #[Test]
    public function webhook_handler_ignores_unknown_external_reference(): void
    {
        Event::fake([PaymentConfirmed::class, PaymentFailed::class]);

        // Use a valid UUID format but nonexistent
        $payload = [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay_unknown',
                'externalReference' => '019b09ec-0000-0000-0000-000000000000',
                'billingType' => 'PIX',
            ],
        ];

        $this->gateway->handleWebhook($payload);

        Event::assertNotDispatched(PaymentConfirmed::class);
        Event::assertNotDispatched(PaymentFailed::class);
    }

    #[Test]
    public function webhook_handler_ignores_missing_external_reference(): void
    {
        Event::fake([PaymentConfirmed::class]);

        $payload = [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay_no_ref',
                'billingType' => 'PIX',
            ],
        ];

        $this->gateway->handleWebhook($payload);

        Event::assertNotDispatched(PaymentConfirmed::class);
    }

    // =========================================================================
    // Webhook Parsing Tests
    // =========================================================================

    #[Test]
    public function parse_webhook_payload_normalizes_data(): void
    {
        $payload = [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay_123',
                'externalReference' => 'ref_456',
                'status' => 'CONFIRMED',
                'billingType' => 'PIX',
                'value' => 99.90,
                'netValue' => 97.90,
                'dueDate' => '2025-12-15',
                'paymentDate' => '2025-12-10',
                'confirmedDate' => '2025-12-10',
            ],
        ];

        $result = $this->gateway->parseWebhookPayload($payload);

        $this->assertEquals('PAYMENT_CONFIRMED', $result['event']);
        $this->assertEquals('payment.confirmed', $result['event_type']);
        $this->assertEquals('pay_123', $result['payment_id']);
        $this->assertEquals('ref_456', $result['external_reference']);
        $this->assertEquals('CONFIRMED', $result['status']);
        $this->assertEquals('paid', $result['normalized_status']);
        $this->assertEquals('PIX', $result['billing_type']);
        $this->assertEquals(9990, $result['value']);
        $this->assertEquals(9790, $result['net_value']);
    }

    #[Test]
    public function is_payment_confirmed_event_returns_true_for_confirmed(): void
    {
        $this->assertTrue($this->gateway->isPaymentConfirmedEvent('PAYMENT_CONFIRMED'));
        $this->assertTrue($this->gateway->isPaymentConfirmedEvent('PAYMENT_RECEIVED'));
        $this->assertFalse($this->gateway->isPaymentConfirmedEvent('PAYMENT_OVERDUE'));
    }

    #[Test]
    public function is_payment_failed_event_returns_true_for_failure_events(): void
    {
        $this->assertTrue($this->gateway->isPaymentFailedEvent('PAYMENT_OVERDUE'));
        $this->assertTrue($this->gateway->isPaymentFailedEvent('PAYMENT_DELETED'));
        $this->assertTrue($this->gateway->isPaymentFailedEvent('PAYMENT_REFUNDED'));
        $this->assertTrue($this->gateway->isPaymentFailedEvent('PAYMENT_CHARGEBACK_REQUESTED'));
        $this->assertFalse($this->gateway->isPaymentFailedEvent('PAYMENT_CONFIRMED'));
    }

    // =========================================================================
    // Payment Retrieval Tests
    // =========================================================================

    #[Test]
    public function retrieve_payment_returns_payment_data(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments/pay_123' => Http::response([
                'id' => 'pay_123',
                'status' => 'CONFIRMED',
                'billingType' => 'PIX',
                'value' => 99.90,
            ], 200),
        ]);

        $result = $this->gateway->retrievePayment('pay_123');

        $this->assertEquals('pay_123', $result['id']);
        $this->assertEquals('CONFIRMED', $result['status']);
    }

    #[Test]
    public function retrieve_payment_throws_on_failure(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments/invalid' => Http::response([
                'errors' => [['description' => 'Payment not found']],
            ], 404),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to retrieve payment');

        $this->gateway->retrievePayment('invalid');
    }

    #[Test]
    public function list_payments_returns_customer_payments(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments*' => Http::response([
                'data' => [
                    ['id' => 'pay_1', 'status' => 'CONFIRMED'],
                    ['id' => 'pay_2', 'status' => 'PENDING'],
                ],
                'totalCount' => 2,
            ], 200),
        ]);

        $result = $this->gateway->listPayments($this->customer, [
            'billing_type' => 'PIX',
            'limit' => 10,
        ]);

        $this->assertCount(2, $result['data']);
        $this->assertEquals(2, $result['totalCount']);
    }

    #[Test]
    public function list_payments_returns_empty_for_customer_without_asaas_id(): void
    {
        $customerWithoutAsaas = Customer::factory()->create([
            'provider_ids' => [],
        ]);

        $result = $this->gateway->listPayments($customerWithoutAsaas);

        $this->assertEmpty($result['data']);
        $this->assertEquals(0, $result['totalCount']);
    }

    // =========================================================================
    // Webhook Signature Validation Tests
    // =========================================================================

    #[Test]
    public function validate_webhook_signature_with_valid_token(): void
    {
        config(['payment.drivers.asaas.webhook_token' => 'test_webhook_token']);

        $gateway = new AsaasGateway([
            'api_key' => 'test_key',
            'sandbox' => true,
        ]);

        $isValid = $gateway->validateWebhookSignature('payload', 'test_webhook_token');

        $this->assertTrue($isValid);
    }

    #[Test]
    public function validate_webhook_signature_with_invalid_token(): void
    {
        config(['payment.drivers.asaas.webhook_token' => 'test_webhook_token']);

        $gateway = new AsaasGateway([
            'api_key' => 'test_key',
            'sandbox' => true,
        ]);

        $isValid = $gateway->validateWebhookSignature('payload', 'wrong_token');

        $this->assertFalse($isValid);
    }

    // =========================================================================
    // Credit Card Charge Tests
    // =========================================================================

    #[Test]
    public function create_card_charge_with_token_returns_success(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments' => Http::response([
                'id' => 'pay_card_123',
                'status' => 'CONFIRMED',
                'billingType' => 'CREDIT_CARD',
                'value' => 199.90,
                'invoiceUrl' => 'https://asaas.com/invoice/123',
                'creditCard' => [
                    'creditCardNumber' => '4242',
                    'creditCardBrand' => 'VISA',
                ],
            ], 200),
        ]);

        $result = $this->gateway->createCardCharge(
            $this->customer,
            19990,
            'token_xyz_123',
            [
                'description' => 'Test Card Payment',
                'reference' => 'order_123',
            ]
        );

        $this->assertTrue($result->success);
        $this->assertEquals('pay_card_123', $result->providerPaymentId);
        $this->assertEquals('paid', $result->status);
        $this->assertEquals('CREDIT_CARD', $result->providerData['billing_type']);
        $this->assertEquals('4242', $result->providerData['card']['last_four']);
        $this->assertEquals('VISA', $result->providerData['card']['brand']);
    }

    #[Test]
    public function create_card_charge_with_installments(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments' => Http::response([
                'id' => 'pay_installment_123',
                'status' => 'CONFIRMED',
                'billingType' => 'CREDIT_CARD',
                'value' => 600.00,
                'creditCard' => [
                    'creditCardNumber' => '1234',
                    'creditCardBrand' => 'MASTERCARD',
                ],
            ], 200),
        ]);

        $result = $this->gateway->createCardCharge(
            $this->customer,
            60000, // R$600.00
            'token_xyz',
            ['installments' => 6]
        );

        $this->assertTrue($result->success);
        $this->assertEquals('pay_installment_123', $result->providerPaymentId);
        $this->assertEquals(6, $result->providerData['installments']);
        $this->assertEquals(60000, $result->providerData['amount']);
        $this->assertEquals('1234', $result->providerData['card']['last_four']);
        $this->assertEquals('MASTERCARD', $result->providerData['card']['brand']);
    }

    #[Test]
    public function create_card_charge_handles_api_failure(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments' => Http::response([
                'errors' => [
                    ['description' => 'Card declined'],
                ],
            ], 400),
        ]);

        $result = $this->gateway->createCardCharge(
            $this->customer,
            10000,
            'invalid_token'
        );

        $this->assertFalse($result->success);
        $this->assertEquals('Card declined', $result->failureMessage);
    }

    #[Test]
    public function create_card_charge_with_data_returns_success_and_token(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments' => Http::response([
                'id' => 'pay_direct_123',
                'status' => 'CONFIRMED',
                'billingType' => 'CREDIT_CARD',
                'value' => 99.90,
                'creditCard' => [
                    'creditCardNumber' => '4242',
                    'creditCardBrand' => 'VISA',
                ],
                'creditCardToken' => 'new_token_abc',
            ], 200),
        ]);

        $result = $this->gateway->createCardChargeWithData(
            $this->customer,
            9990,
            [
                'holder_name' => 'John Doe',
                'number' => '4242424242424242',
                'exp_month' => '12',
                'exp_year' => '2030',
                'cvv' => '123',
            ],
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'cpf_cnpj' => '123.456.789-00',
                'postal_code' => '01310-100',
                'address_number' => '100',
            ]
        );

        $this->assertTrue($result->success);
        $this->assertEquals('pay_direct_123', $result->providerPaymentId);
        $this->assertEquals('new_token_abc', $result->providerData['card']['token']);
    }

    // =========================================================================
    // Card Tokenization Tests
    // =========================================================================

    #[Test]
    public function tokenize_card_returns_token(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/creditCard/tokenize' => Http::response([
                'creditCardNumber' => '4242',
                'creditCardBrand' => 'VISA',
                'creditCardToken' => 'token_generated_123',
            ], 200),
        ]);

        $result = $this->gateway->tokenizeCard(
            $this->customer,
            [
                'holder_name' => 'Jane Doe',
                'number' => '4242424242424242',
                'exp_month' => '06',
                'exp_year' => '2028',
                'cvv' => '456',
            ],
            [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'cpf_cnpj' => '98765432100',
                'postal_code' => '04567890',
                'address_number' => '200',
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('token_generated_123', $result['token']);
        $this->assertEquals('4242', $result['last_four']);
        $this->assertEquals('VISA', $result['brand']);
    }

    #[Test]
    public function tokenize_card_handles_failure(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/creditCard/tokenize' => Http::response([
                'errors' => [
                    ['description' => 'Invalid card data'],
                ],
            ], 400),
        ]);

        $result = $this->gateway->tokenizeCard(
            $this->customer,
            [
                'holder_name' => 'Test',
                'number' => '1234',
                'exp_month' => '01',
                'exp_year' => '2020',
                'cvv' => '123',
            ],
            [
                'name' => 'Test',
                'email' => 'test@test.com',
                'cpf_cnpj' => '12345678900',
                'postal_code' => '12345678',
                'address_number' => '1',
            ]
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid card data', $result['error']);
    }

    #[Test]
    public function tokenize_card_removes_formatting_from_cpf_and_postal_code(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/creditCard/tokenize' => Http::response([
                'creditCardNumber' => '4242',
                'creditCardBrand' => 'VISA',
                'creditCardToken' => 'token_123',
            ], 200),
        ]);

        $this->gateway->tokenizeCard(
            $this->customer,
            [
                'holder_name' => 'Test User',
                'number' => '4242424242424242',
                'exp_month' => '12',
                'exp_year' => '2030',
                'cvv' => '123',
            ],
            [
                'name' => 'Test User',
                'email' => 'test@test.com',
                'cpf_cnpj' => '123.456.789-00',
                'postal_code' => '01310-100',
                'address_number' => '100',
            ]
        );

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['creditCardHolderInfo']['cpfCnpj'] === '12345678900'
                && $body['creditCardHolderInfo']['postalCode'] === '01310100';
        });
    }

    // =========================================================================
    // Card Validation Tests
    // =========================================================================

    #[Test]
    public function validate_card_data_returns_valid_for_correct_data(): void
    {
        $result = $this->gateway->validateCardData([
            'holder_name' => 'John Doe',
            'number' => '4242424242424242',
            'exp_month' => '12',
            'exp_year' => '2030',
            'cvv' => '123',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function validate_card_data_detects_missing_fields(): void
    {
        $result = $this->gateway->validateCardData([
            'holder_name' => 'John Doe',
            // missing other fields
        ]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    #[Test]
    public function validate_card_data_detects_invalid_card_number(): void
    {
        $result = $this->gateway->validateCardData([
            'holder_name' => 'John Doe',
            'number' => '1234567890123456', // Invalid Luhn
            'exp_month' => '12',
            'exp_year' => '2030',
            'cvv' => '123',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('Invalid card number', $result['errors']);
    }

    #[Test]
    public function validate_card_data_detects_expired_card(): void
    {
        $result = $this->gateway->validateCardData([
            'holder_name' => 'John Doe',
            'number' => '4242424242424242',
            'exp_month' => '01',
            'exp_year' => '2020', // Expired
            'cvv' => '123',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('Card has expired', $result['errors']);
    }

    #[Test]
    public function validate_card_data_detects_invalid_cvv(): void
    {
        $result = $this->gateway->validateCardData([
            'holder_name' => 'John Doe',
            'number' => '4242424242424242',
            'exp_month' => '12',
            'exp_year' => '2030',
            'cvv' => '12', // Too short
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('Invalid CVV length', $result['errors']);
    }

    // =========================================================================
    // Card Brand Detection Tests
    // =========================================================================

    #[Test]
    public function detect_card_brand_identifies_visa(): void
    {
        $this->assertEquals('visa', $this->gateway->detectCardBrand('4242424242424242'));
        $this->assertEquals('visa', $this->gateway->detectCardBrand('4111111111111111'));
    }

    #[Test]
    public function detect_card_brand_identifies_mastercard(): void
    {
        $this->assertEquals('mastercard', $this->gateway->detectCardBrand('5555555555554444'));
        $this->assertEquals('mastercard', $this->gateway->detectCardBrand('5105105105105100'));
        $this->assertEquals('mastercard', $this->gateway->detectCardBrand('2223000048400011'));
    }

    #[Test]
    public function detect_card_brand_identifies_amex(): void
    {
        $this->assertEquals('amex', $this->gateway->detectCardBrand('378282246310005'));
        $this->assertEquals('amex', $this->gateway->detectCardBrand('371449635398431'));
    }

    #[Test]
    public function detect_card_brand_identifies_elo(): void
    {
        $this->assertEquals('elo', $this->gateway->detectCardBrand('6362970000457013'));
    }

    #[Test]
    public function detect_card_brand_returns_null_for_unknown(): void
    {
        $this->assertNull($this->gateway->detectCardBrand('9999999999999999'));
    }

    // =========================================================================
    // Helper Assertion
    // =========================================================================

    protected function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertStringContainsString($needle, $haystack);
    }
}
