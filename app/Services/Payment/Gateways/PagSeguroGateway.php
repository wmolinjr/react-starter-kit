<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Payment\PaymentMethodGatewayInterface;
use App\Contracts\Payment\SubscriptionGatewayInterface;
use App\DTOs\Payment\ChargeResult;
use App\DTOs\Payment\PaymentMethodResult;
use App\DTOs\Payment\RefundResult;
use App\DTOs\Payment\SetupIntentResult;
use App\DTOs\Payment\SubscriptionResult;
use App\Models\Central\Customer;
use App\Models\Central\Payment;
use App\Models\Central\PaymentMethod;
use App\Models\Central\Subscription;

/**
 * PagSeguro Gateway Stub
 *
 * Brazilian payment provider supporting:
 * - Credit Card
 * - Boleto
 * - PIX
 *
 * @see https://dev.pagseguro.uol.com.br/
 */
class PagSeguroGateway implements PaymentGatewayInterface, PaymentMethodGatewayInterface, SubscriptionGatewayInterface
{
    protected string $apiKey;

    protected string $baseUrl;

    protected bool $sandbox;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->sandbox = $config['sandbox'] ?? true;
        $this->baseUrl = $this->sandbox
            ? 'https://sandbox.api.pagseguro.com'
            : 'https://api.pagseguro.com';
    }

    // =========================================================================
    // PaymentGatewayInterface Implementation
    // =========================================================================

    public function getIdentifier(): string
    {
        return 'pagseguro';
    }

    public function getDisplayName(): string
    {
        return 'PagSeguro';
    }

    public function getSupportedTypes(): array
    {
        return ['card', 'pix', 'boleto'];
    }

    public function getSupportedCurrencies(): array
    {
        return ['BRL'];
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function ensureCustomer(Customer $customer): string
    {
        return $customer->getProviderId('pagseguro') ?? $customer->id;
    }

    public function charge(Customer $customer, int $amount, array $options = []): ChargeResult
    {
        return ChargeResult::failed('PagSeguro gateway not yet implemented');
    }

    public function refund(Payment $payment, ?int $amount = null): RefundResult
    {
        return RefundResult::failed('PagSeguro gateway not yet implemented');
    }

    public function checkStatus(Payment $payment): string
    {
        return 'failed';
    }

    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        return false;
    }

    public function handleWebhook(array $payload, array $headers = []): void
    {
        // Not yet implemented
    }

    // =========================================================================
    // PaymentMethodGatewayInterface Implementation
    // =========================================================================

    public function createSetupIntent(Customer $customer): SetupIntentResult
    {
        return SetupIntentResult::failed('PagSeguro gateway not yet implemented');
    }

    public function attachPaymentMethod(Customer $customer, string $providerMethodId, array $options = []): PaymentMethodResult
    {
        return PaymentMethodResult::failed('PagSeguro gateway not yet implemented');
    }

    public function detachPaymentMethod(PaymentMethod $paymentMethod): bool
    {
        return false;
    }

    public function listPaymentMethods(Customer $customer): array
    {
        return [];
    }

    public function setDefaultPaymentMethod(Customer $customer, PaymentMethod $paymentMethod): bool
    {
        return false;
    }

    public function syncPaymentMethods(Customer $customer): void
    {
        // Not yet implemented
    }

    // =========================================================================
    // SubscriptionGatewayInterface Implementation
    // =========================================================================

    public function createSubscription(Customer $customer, string $priceId, array $options = []): SubscriptionResult
    {
        return SubscriptionResult::failed('PagSeguro gateway not yet implemented');
    }

    public function updateSubscription(Subscription $subscription, string $newPriceId, array $options = []): SubscriptionResult
    {
        return SubscriptionResult::failed('PagSeguro gateway not yet implemented');
    }

    public function pauseSubscription(Subscription $subscription): SubscriptionResult
    {
        return SubscriptionResult::failed('PagSeguro does not support pausing subscriptions');
    }

    public function syncSubscription(Subscription $subscription): SubscriptionResult
    {
        return SubscriptionResult::failed('PagSeguro gateway not yet implemented');
    }

    public function createCheckoutSession(Customer $customer, string $priceId, array $options = []): string
    {
        return $options['success_url'] ?? '/checkout';
    }

    public function createBillingPortalSession(Customer $customer, string $returnUrl): string
    {
        return $returnUrl;
    }

    public function cancelSubscription(Subscription $subscription, bool $immediately = false): SubscriptionResult
    {
        return SubscriptionResult::failed('PagSeguro gateway not yet implemented');
    }

    public function resumeSubscription(Subscription $subscription): SubscriptionResult
    {
        return SubscriptionResult::failed('PagSeguro does not support resuming subscriptions');
    }

    public function retrieveSubscription(string $subscriptionId): array
    {
        return [];
    }

    public function addSubscriptionItem(Subscription $subscription, string $priceId, int $quantity = 1): array
    {
        throw new \RuntimeException('PagSeguro gateway not yet implemented');
    }

    public function updateSubscriptionItem(Subscription $subscription, string $priceId, int $quantity): void
    {
        throw new \RuntimeException('PagSeguro gateway not yet implemented');
    }

    public function removeSubscriptionItem(Subscription $subscription, string $priceId): void
    {
        throw new \RuntimeException('PagSeguro gateway not yet implemented');
    }
}
