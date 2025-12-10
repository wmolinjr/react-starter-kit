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
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asaas Gateway
 *
 * Brazilian payment provider supporting:
 * - PIX (instant payments)
 * - Boleto (bank slip)
 * - Credit Card
 * - Recurring subscriptions
 *
 * @see https://docs.asaas.com/
 */
class AsaasGateway implements PaymentGatewayInterface, PaymentMethodGatewayInterface, SubscriptionGatewayInterface
{
    protected string $apiKey;

    protected string $baseUrl;

    protected bool $sandbox;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->sandbox = $config['sandbox'] ?? true;
        $this->baseUrl = $this->sandbox
            ? 'https://sandbox.asaas.com/api/v3'
            : 'https://api.asaas.com/api/v3';
    }

    /**
     * Get HTTP client with authentication.
     */
    protected function http(): PendingRequest
    {
        return Http::withHeaders([
            'access_token' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->baseUrl($this->baseUrl);
    }

    // =========================================================================
    // PaymentGatewayInterface Implementation
    // =========================================================================

    /**
     * Get the unique identifier for this gateway.
     */
    public function getIdentifier(): string
    {
        return 'asaas';
    }

    /**
     * Get the display name for this gateway.
     */
    public function getDisplayName(): string
    {
        return 'Asaas';
    }

    /**
     * Get supported payment types.
     */
    public function getSupportedTypes(): array
    {
        return ['card', 'pix', 'boleto'];
    }

    /**
     * Get supported currencies.
     */
    public function getSupportedCurrencies(): array
    {
        return ['BRL'];
    }

    /**
     * Check if the gateway is available and configured.
     */
    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Ensure customer exists in this provider.
     */
    public function ensureCustomer(Customer $customer): string
    {
        $asaasId = $customer->getProviderId('asaas');

        if ($asaasId) {
            return $asaasId;
        }

        return $this->createAsaasCustomer($customer);
    }

    /**
     * Create a charge for a customer.
     */
    public function charge(Customer $customer, int $amount, array $options = []): ChargeResult
    {
        $asaasId = $this->ensureCustomer($customer);

        $billingType = $options['billing_type'] ?? $options['payment_type'] ?? 'UNDEFINED';
        $dueDate = $options['due_date'] ?? now()->addDays(3)->format('Y-m-d');

        try {
            $response = $this->http()->post('/payments', [
                'customer' => $asaasId,
                'billingType' => strtoupper($billingType),
                'value' => $amount / 100,
                'dueDate' => $dueDate,
                'description' => $options['description'] ?? 'Payment',
                'externalReference' => $options['reference'] ?? null,
            ]);

            if ($response->failed()) {
                Log::error('Asaas charge failed', [
                    'customer_id' => $customer->id,
                    'response' => $response->json(),
                ]);

                return ChargeResult::failed(
                    $response->json('errors.0.description', 'Charge failed')
                );
            }

            $data = $response->json();

            return ChargeResult::success(
                providerPaymentId: $data['id'],
                status: $this->mapPaymentStatus($data['status']),
                providerData: [
                    'amount' => (int) ($data['value'] * 100),
                    'currency' => 'BRL',
                    'billing_type' => $data['billingType'],
                    'due_date' => $data['dueDate'],
                    'pix' => $this->extractPixData($data),
                    'boleto' => $this->extractBoletoData($data),
                    'invoice_url' => $data['invoiceUrl'] ?? null,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Asaas charge exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return ChargeResult::failed($e->getMessage());
        }
    }

    /**
     * Process a refund.
     */
    public function refund(Payment $payment, ?int $amount = null): RefundResult
    {
        $paymentId = $payment->provider_payment_id;

        if (! $paymentId) {
            return RefundResult::failed('No provider payment ID found');
        }

        try {
            $response = $this->http()->post("/payments/{$paymentId}/refund", [
                'value' => $amount ? $amount / 100 : null,
                'description' => 'Refund',
            ]);

            if ($response->failed()) {
                return RefundResult::failed(
                    $response->json('errors.0.description', 'Refund failed')
                );
            }

            $data = $response->json();

            return RefundResult::success(
                providerRefundId: $data['id'] ?? $paymentId,
                amountRefunded: $amount ?? 0
            );
        } catch (\Exception $e) {
            return RefundResult::failed($e->getMessage());
        }
    }

    /**
     * Check payment status from provider.
     */
    public function checkStatus(Payment $payment): string
    {
        $paymentId = $payment->provider_payment_id;

        if (! $paymentId) {
            return 'failed';
        }

        try {
            $response = $this->http()->get("/payments/{$paymentId}");

            if ($response->failed()) {
                return 'failed';
            }

            $data = $response->json();

            return $this->mapPaymentStatus($data['status'] ?? 'FAILED');
        } catch (\Exception $e) {
            Log::error('Asaas checkStatus failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }

    /**
     * Validate webhook signature.
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $webhookToken = config('payment.drivers.asaas.webhook_token', '');

        return hash_equals($webhookToken, $signature);
    }

    /**
     * Handle incoming webhook.
     */
    public function handleWebhook(array $payload, array $headers = []): void
    {
        $event = $payload['event'] ?? '';

        Log::info('Asaas webhook received', [
            'event' => $event,
            'payment_id' => $payload['payment']['id'] ?? null,
        ]);
    }

    // =========================================================================
    // PaymentMethodGatewayInterface Implementation
    // =========================================================================

    /**
     * Create a setup intent for saving payment methods.
     */
    public function createSetupIntent(Customer $customer): SetupIntentResult
    {
        $intentId = 'asaas_setup_'.uniqid();

        return SetupIntentResult::success(
            clientSecret: $intentId,
            providerIntentId: $intentId,
            providerData: [
                'message' => 'Asaas saves payment methods during first charge',
            ]
        );
    }

    /**
     * Attach a payment method to a customer.
     */
    public function attachPaymentMethod(
        Customer $customer,
        string $providerMethodId,
        array $options = []
    ): PaymentMethodResult {
        return new PaymentMethodResult(
            success: true,
            providerMethodId: $providerMethodId,
            type: 'card',
            isDefault: $options['set_as_default'] ?? true
        );
    }

    /**
     * Detach a payment method from a customer.
     */
    public function detachPaymentMethod(PaymentMethod $paymentMethod): bool
    {
        return true;
    }

    /**
     * List customer's payment methods.
     */
    public function listPaymentMethods(Customer $customer): array
    {
        $asaasId = $customer->getProviderId('asaas');

        if (! $asaasId) {
            return [];
        }

        try {
            $response = $this->http()->get("/customers/{$asaasId}/creditCardInfo");

            if ($response->failed()) {
                return [];
            }

            $data = $response->json();

            if (empty($data)) {
                return [];
            }

            return [
                new PaymentMethodResult(
                    success: true,
                    providerMethodId: 'card_'.$asaasId,
                    type: 'card',
                    brand: $data['creditCardBrand'] ?? null,
                    last4: $data['creditCardNumber'] ?? null,
                    isDefault: true
                ),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Set default payment method.
     */
    public function setDefaultPaymentMethod(Customer $customer, PaymentMethod $paymentMethod): bool
    {
        return true;
    }

    /**
     * Sync payment methods from provider to local database.
     */
    public function syncPaymentMethods(Customer $customer): void
    {
        // Asaas doesn't store multiple payment methods, so nothing to sync
    }

    // =========================================================================
    // SubscriptionGatewayInterface Implementation
    // =========================================================================

    /**
     * Create a subscription.
     */
    public function createSubscription(Customer $customer, string $priceId, array $options = []): SubscriptionResult
    {
        $asaasId = $this->ensureCustomer($customer);

        $billingType = $options['billing_type'] ?? 'CREDIT_CARD';
        $cycle = $options['cycle'] ?? 'MONTHLY';

        try {
            $response = $this->http()->post('/subscriptions', [
                'customer' => $asaasId,
                'billingType' => $billingType,
                'value' => ($options['amount'] ?? 0) / 100,
                'nextDueDate' => $options['start_date'] ?? now()->addDays(1)->format('Y-m-d'),
                'cycle' => $cycle,
                'description' => $options['description'] ?? 'Subscription',
                'externalReference' => $priceId,
            ]);

            if ($response->failed()) {
                return new SubscriptionResult(
                    success: false,
                    providerSubscriptionId: null,
                    status: 'failed',
                    failureMessage: $response->json('errors.0.description', 'Subscription creation failed')
                );
            }

            $data = $response->json();

            return new SubscriptionResult(
                success: true,
                providerSubscriptionId: $data['id'],
                status: $this->mapSubscriptionStatus($data['status']),
                currentPeriodStart: now(),
                currentPeriodEnd: isset($data['nextDueDate']) ? \Carbon\Carbon::parse($data['nextDueDate']) : null,
                providerData: [
                    'cycle' => $data['cycle'],
                    'billing_type' => $data['billingType'],
                    'value' => $data['value'],
                ]
            );
        } catch (\Exception $e) {
            return new SubscriptionResult(
                success: false,
                providerSubscriptionId: null,
                status: 'failed',
                failureMessage: $e->getMessage()
            );
        }
    }

    /**
     * Update a subscription.
     */
    public function updateSubscription(
        Subscription $subscription,
        string $newPriceId,
        array $options = []
    ): SubscriptionResult {
        $subscriptionId = $subscription->provider_subscription_id;

        try {
            $updateData = [];

            if (isset($options['amount'])) {
                $updateData['value'] = $options['amount'] / 100;
            }

            if (isset($options['billing_type'])) {
                $updateData['billingType'] = $options['billing_type'];
            }

            if (isset($options['next_due_date'])) {
                $updateData['nextDueDate'] = $options['next_due_date'];
            }

            $response = $this->http()->put("/subscriptions/{$subscriptionId}", $updateData);

            if ($response->failed()) {
                return new SubscriptionResult(
                    success: false,
                    providerSubscriptionId: $subscriptionId,
                    status: 'failed',
                    failureMessage: $response->json('errors.0.description', 'Update failed')
                );
            }

            $data = $response->json();

            return new SubscriptionResult(
                success: true,
                providerSubscriptionId: $data['id'],
                status: $this->mapSubscriptionStatus($data['status'])
            );
        } catch (\Exception $e) {
            return new SubscriptionResult(
                success: false,
                providerSubscriptionId: $subscriptionId,
                status: 'failed',
                failureMessage: $e->getMessage()
            );
        }
    }

    /**
     * Pause a subscription.
     */
    public function pauseSubscription(Subscription $subscription): SubscriptionResult
    {
        return new SubscriptionResult(
            success: false,
            providerSubscriptionId: $subscription->provider_subscription_id,
            status: 'failed',
            failureMessage: 'Asaas does not support pausing subscriptions.'
        );
    }

    /**
     * Sync subscription status from provider.
     */
    public function syncSubscription(Subscription $subscription): SubscriptionResult
    {
        $subscriptionId = $subscription->provider_subscription_id;

        try {
            $data = $this->retrieveSubscription($subscriptionId);

            return new SubscriptionResult(
                success: true,
                providerSubscriptionId: $data['id'],
                status: $this->mapSubscriptionStatus($data['status'] ?? 'INACTIVE'),
                currentPeriodEnd: isset($data['nextDueDate'])
                    ? \Carbon\Carbon::parse($data['nextDueDate'])
                    : null
            );
        } catch (\Exception $e) {
            return new SubscriptionResult(
                success: false,
                providerSubscriptionId: $subscriptionId,
                status: 'failed',
                failureMessage: $e->getMessage()
            );
        }
    }

    /**
     * Create a checkout session for subscription.
     */
    public function createCheckoutSession(
        Customer $customer,
        string $priceId,
        array $options = []
    ): string {
        // Asaas doesn't have hosted checkout like Stripe
        // Return a URL to your own checkout page
        return $options['success_url'] ?? '/checkout';
    }

    /**
     * Create a billing portal session.
     */
    public function createBillingPortalSession(Customer $customer, string $returnUrl): string
    {
        // Asaas doesn't have a customer billing portal like Stripe
        // Return a URL to your own billing management page
        return $returnUrl;
    }

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(Subscription $subscription, bool $immediately = false): SubscriptionResult
    {
        $subscriptionId = $subscription->provider_subscription_id;

        try {
            $response = $this->http()->delete("/subscriptions/{$subscriptionId}");

            if ($response->failed()) {
                return new SubscriptionResult(
                    success: false,
                    providerSubscriptionId: $subscriptionId,
                    status: 'failed',
                    failureMessage: $response->json('errors.0.description', 'Cancellation failed')
                );
            }

            return new SubscriptionResult(
                success: true,
                providerSubscriptionId: $subscriptionId,
                status: 'canceled'
            );
        } catch (\Exception $e) {
            return new SubscriptionResult(
                success: false,
                providerSubscriptionId: $subscriptionId,
                status: 'failed',
                failureMessage: $e->getMessage()
            );
        }
    }

    /**
     * Resume a canceled subscription.
     */
    public function resumeSubscription(Subscription $subscription): SubscriptionResult
    {
        return new SubscriptionResult(
            success: false,
            providerSubscriptionId: $subscription->provider_subscription_id,
            status: 'failed',
            failureMessage: 'Asaas does not support resuming subscriptions. Create a new subscription instead.'
        );
    }

    /**
     * Retrieve subscription details.
     */
    public function retrieveSubscription(string $subscriptionId): array
    {
        $response = $this->http()->get("/subscriptions/{$subscriptionId}");

        if ($response->failed()) {
            throw new \RuntimeException('Failed to retrieve subscription');
        }

        return $response->json();
    }

    // =========================================================================
    // Subscription Item Methods
    // =========================================================================

    /**
     * Add item to subscription.
     *
     * Note: Asaas doesn't support multi-item subscriptions like Stripe.
     * For addons, create separate subscriptions or adjust the main subscription value.
     */
    public function addSubscriptionItem(
        Subscription $subscription,
        string $priceId,
        int $quantity = 1
    ): array {
        // Asaas doesn't support multi-item subscriptions
        // Option 1: Create a separate subscription for the addon
        // Option 2: Update the main subscription value
        throw new \RuntimeException(
            'Asaas does not support multi-item subscriptions. '.
            'Create a separate subscription for addons or update the main subscription value.'
        );
    }

    /**
     * Update subscription item quantity.
     */
    public function updateSubscriptionItem(
        Subscription $subscription,
        string $priceId,
        int $quantity
    ): void {
        throw new \RuntimeException(
            'Asaas does not support multi-item subscriptions. '.
            'Update the subscription value directly instead.'
        );
    }

    /**
     * Remove item from subscription.
     */
    public function removeSubscriptionItem(
        Subscription $subscription,
        string $priceId
    ): void {
        throw new \RuntimeException(
            'Asaas does not support multi-item subscriptions. '.
            'Cancel the separate addon subscription instead.'
        );
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Create a customer in Asaas.
     */
    protected function createAsaasCustomer(Customer $customer): string
    {
        $response = $this->http()->post('/customers', [
            'name' => $customer->name ?? $customer->email,
            'email' => $customer->email,
            'cpfCnpj' => $customer->tax_id ?? null,
            'phone' => $customer->phone ?? null,
            'externalReference' => $customer->id,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to create Asaas customer: '.$response->json('errors.0.description', 'Unknown error'));
        }

        $asaasId = $response->json('id');
        $customer->setProviderId('asaas', $asaasId);

        return $asaasId;
    }

    /**
     * Map Asaas payment status to internal status.
     */
    protected function mapPaymentStatus(string $status): string
    {
        return match ($status) {
            'CONFIRMED', 'RECEIVED' => 'paid',
            'PENDING', 'AWAITING_RISK_ANALYSIS' => 'pending',
            'OVERDUE' => 'past_due',
            'REFUNDED', 'REFUND_REQUESTED', 'REFUND_IN_PROGRESS' => 'refunded',
            'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE', 'AWAITING_CHARGEBACK_REVERSAL' => 'disputed',
            default => 'failed',
        };
    }

    /**
     * Map Asaas subscription status to internal status.
     */
    protected function mapSubscriptionStatus(string $status): string
    {
        return match ($status) {
            'ACTIVE' => 'active',
            'INACTIVE', 'EXPIRED' => 'canceled',
            default => 'incomplete',
        };
    }

    /**
     * Extract PIX data from payment response.
     */
    protected function extractPixData(array $data): ?array
    {
        if (($data['billingType'] ?? '') !== 'PIX') {
            return null;
        }

        if (! isset($data['id'])) {
            return null;
        }

        try {
            $pixResponse = $this->http()->get("/payments/{$data['id']}/pixQrCode");

            if ($pixResponse->successful()) {
                $pixData = $pixResponse->json();

                return [
                    'qr_code' => $pixData['encodedImage'] ?? null,
                    'copy_paste' => $pixData['payload'] ?? null,
                    'expiration' => $pixData['expirationDate'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get PIX QR code', ['payment_id' => $data['id']]);
        }

        return null;
    }

    /**
     * Extract Boleto data from payment response.
     */
    protected function extractBoletoData(array $data): ?array
    {
        if (($data['billingType'] ?? '') !== 'BOLETO') {
            return null;
        }

        return [
            'barcode' => $data['bankSlipUrl'] ?? null,
            'url' => $data['bankSlipUrl'] ?? null,
            'due_date' => $data['dueDate'] ?? null,
        ];
    }
}
