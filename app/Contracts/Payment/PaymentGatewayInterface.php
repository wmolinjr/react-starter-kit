<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\DTOs\Payment\ChargeResult;
use App\DTOs\Payment\RefundResult;
use App\Models\Central\Customer;
use App\Models\Central\Payment;

/**
 * Payment Gateway Interface
 *
 * Provider-agnostic contract for payment processing.
 * Implementations: StripeGateway, AsaasGateway, etc.
 */
interface PaymentGatewayInterface
{
    /**
     * Get the unique identifier for this gateway.
     *
     * @example 'stripe', 'asaas', 'pagseguro'
     */
    public function getIdentifier(): string;

    /**
     * Get the display name for this gateway.
     *
     * @example 'Stripe', 'Asaas', 'PagSeguro'
     */
    public function getDisplayName(): string;

    /**
     * Get supported payment types.
     *
     * @return array<string> ['card', 'pix', 'boleto', 'bank_transfer']
     */
    public function getSupportedTypes(): array;

    /**
     * Get supported currencies.
     *
     * @return array<string> ['BRL', 'USD', 'EUR']
     */
    public function getSupportedCurrencies(): array;

    /**
     * Check if the gateway is available and configured.
     */
    public function isAvailable(): bool;

    /**
     * Ensure customer exists in this provider.
     *
     * Creates the customer in the provider if not exists,
     * stores provider customer ID in Customer.provider_ids.
     *
     * @return string The provider customer ID
     */
    public function ensureCustomer(Customer $customer): string;

    /**
     * Create a charge/payment.
     *
     * @param  Customer  $customer  The customer to charge
     * @param  int  $amount  Amount in cents
     * @param array{
     *   currency?: string,
     *   payment_method_id?: string,
     *   payment_type?: string,
     *   description?: string,
     *   metadata?: array,
     *   return_url?: string,
     * } $options
     */
    public function charge(Customer $customer, int $amount, array $options = []): ChargeResult;

    /**
     * Process a refund.
     *
     * @param  Payment  $payment  The payment to refund
     * @param  int|null  $amount  Amount to refund in cents (null = full refund)
     */
    public function refund(Payment $payment, ?int $amount = null): RefundResult;

    /**
     * Check payment status from provider.
     *
     * @return string Status: 'pending', 'paid', 'failed', 'refunded', 'expired'
     */
    public function checkStatus(Payment $payment): string;

    /**
     * Process incoming webhook from provider.
     *
     * @param  array<string, mixed>  $payload  Webhook payload
     * @param  array<string, string>  $headers  Request headers
     */
    public function handleWebhook(array $payload, array $headers): void;

    /**
     * Validate webhook signature.
     *
     * @param  string  $payload  Raw request body
     * @param  string  $signature  Signature from header
     */
    public function validateWebhookSignature(string $payload, string $signature): bool;
}
