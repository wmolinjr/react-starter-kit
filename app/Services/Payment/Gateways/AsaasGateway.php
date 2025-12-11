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
     *
     * Processes Asaas webhook events for PIX/Boleto async payments.
     * Dispatches PaymentConfirmed or PaymentFailed events as appropriate.
     */
    public function handleWebhook(array $payload, array $headers = []): void
    {
        $event = $payload['event'] ?? '';
        $payment = $payload['payment'] ?? [];

        Log::info('Asaas webhook received', [
            'event' => $event,
            'payment_id' => $payment['id'] ?? null,
            'external_reference' => $payment['externalReference'] ?? null,
            'billing_type' => $payment['billingType'] ?? null,
        ]);

        // Process async payment events (PIX, Boleto)
        if ($this->isPaymentConfirmedEvent($event)) {
            $this->handlePaymentConfirmedWebhook($payment);
        } elseif ($this->isPaymentFailedEvent($event)) {
            $this->handlePaymentFailedWebhook($payment, $event);
        }
    }

    /**
     * Handle payment confirmed webhook.
     */
    protected function handlePaymentConfirmedWebhook(array $payment): void
    {
        $externalReference = $payment['externalReference'] ?? null;

        if (! $externalReference) {
            Log::warning('Asaas payment confirmed without external reference', [
                'payment_id' => $payment['id'] ?? null,
            ]);

            return;
        }

        // Try to find AddonPurchase by external reference
        $purchase = $this->findPurchaseByReference($externalReference);

        if (! $purchase) {
            Log::info('Asaas payment confirmed but no matching purchase found', [
                'payment_id' => $payment['id'] ?? null,
                'external_reference' => $externalReference,
            ]);

            return;
        }

        // Dispatch PaymentConfirmed event
        \App\Events\Payment\PaymentConfirmed::dispatch(
            $purchase,
            'asaas',
            $payment['id'] ?? '',
            [
                'billing_type' => $payment['billingType'] ?? null,
                'value' => $payment['value'] ?? null,
                'net_value' => $payment['netValue'] ?? null,
                'payment_date' => $payment['paymentDate'] ?? null,
                'confirmed_date' => $payment['confirmedDate'] ?? null,
            ]
        );

        Log::info('PaymentConfirmed event dispatched for Asaas payment', [
            'purchase_id' => $purchase->id,
            'payment_id' => $payment['id'] ?? null,
        ]);
    }

    /**
     * Handle payment failed webhook.
     */
    protected function handlePaymentFailedWebhook(array $payment, string $event): void
    {
        $externalReference = $payment['externalReference'] ?? null;

        if (! $externalReference) {
            return;
        }

        $purchase = $this->findPurchaseByReference($externalReference);

        if (! $purchase) {
            return;
        }

        $failureReason = $this->getFailureReasonForEvent($event, $payment);

        // Dispatch PaymentFailed event
        \App\Events\Payment\PaymentFailed::dispatch(
            $purchase,
            'asaas',
            $failureReason,
            [
                'event' => $event,
                'billing_type' => $payment['billingType'] ?? null,
                'due_date' => $payment['dueDate'] ?? null,
            ]
        );

        Log::info('PaymentFailed event dispatched for Asaas payment', [
            'purchase_id' => $purchase->id,
            'payment_id' => $payment['id'] ?? null,
            'reason' => $failureReason,
        ]);
    }

    /**
     * Find AddonPurchase by external reference.
     */
    protected function findPurchaseByReference(string $reference): ?\App\Models\Central\AddonPurchase
    {
        // External reference format: "addon_purchase_{id}" or just the purchase ID
        $purchaseId = str_starts_with($reference, 'addon_purchase_')
            ? str_replace('addon_purchase_', '', $reference)
            : $reference;

        return \App\Models\Central\AddonPurchase::find($purchaseId);
    }

    /**
     * Get failure reason based on event type.
     */
    protected function getFailureReasonForEvent(string $event, array $payment): string
    {
        return match ($event) {
            'PAYMENT_OVERDUE' => 'Pagamento vencido (PIX expirado ou Boleto vencido)',
            'PAYMENT_DELETED' => 'Pagamento cancelado',
            'PAYMENT_REFUNDED' => 'Pagamento estornado',
            'PAYMENT_CHARGEBACK_REQUESTED' => 'Chargeback solicitado',
            default => 'Falha no pagamento',
        };
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
     *
     * Supports credit card subscriptions when credit_card and credit_card_holder_info
     * are provided in options.
     *
     * @param  array  $options  Additional options:
     *                          - billing_type: CREDIT_CARD, BOLETO, PIX
     *                          - amount: Amount in cents
     *                          - cycle: WEEKLY, BIWEEKLY, MONTHLY, QUARTERLY, SEMIANNUALLY, YEARLY
     *                          - description: Subscription description
     *                          - start_date: First charge date (Y-m-d)
     *                          - credit_card: Card data for CREDIT_CARD billing
     *                          - credit_card_holder_info: Holder info for CREDIT_CARD billing
     */
    public function createSubscription(Customer $customer, string $priceId, array $options = []): SubscriptionResult
    {
        $asaasId = $this->ensureCustomer($customer);

        $billingType = $options['billing_type'] ?? 'CREDIT_CARD';
        $cycle = $options['cycle'] ?? 'MONTHLY';

        $subscriptionData = [
            'customer' => $asaasId,
            'billingType' => $billingType,
            'value' => ($options['amount'] ?? 0) / 100,
            'nextDueDate' => $options['start_date'] ?? now()->addDays(1)->format('Y-m-d'),
            'cycle' => $cycle,
            'description' => $options['description'] ?? 'Subscription',
            'externalReference' => $priceId,
        ];

        // Add credit card data for CREDIT_CARD billing type
        if ($billingType === 'CREDIT_CARD' && isset($options['credit_card'])) {
            $cardData = $options['credit_card'];
            $subscriptionData['creditCard'] = [
                'holderName' => $cardData['holder_name'],
                'number' => $cardData['number'],
                'expiryMonth' => $cardData['exp_month'],
                'expiryYear' => $cardData['exp_year'],
                'ccv' => $cardData['cvv'],
            ];

            if (isset($options['credit_card_holder_info'])) {
                $holderInfo = $options['credit_card_holder_info'];
                $subscriptionData['creditCardHolderInfo'] = [
                    'name' => $holderInfo['name'],
                    'email' => $holderInfo['email'],
                    'cpfCnpj' => preg_replace('/\D/', '', $holderInfo['cpf_cnpj']),
                    'postalCode' => preg_replace('/\D/', '', $holderInfo['postal_code']),
                    'addressNumber' => $holderInfo['address_number'],
                    'addressComplement' => $holderInfo['address_complement'] ?? null,
                    'phone' => $holderInfo['phone'] ?? null,
                    'mobilePhone' => $holderInfo['mobile_phone'] ?? null,
                ];
            }

            // Add remote IP for fraud prevention
            $subscriptionData['remoteIp'] = $options['remote_ip'] ?? request()->ip();
        }

        try {
            $response = $this->http()->post('/subscriptions', $subscriptionData);

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
    // Credit Card Payment Methods
    // =========================================================================

    /**
     * Create a credit card charge using a tokenized card.
     *
     * @param  Customer  $customer  The customer to charge
     * @param  int  $amount  Amount in cents (BRL)
     * @param  string  $cardToken  The tokenized card ID from Asaas
     * @param  array  $options  Additional options:
     *                          - description: Payment description
     *                          - reference: External reference ID
     *                          - remote_ip: Client IP for fraud prevention
     *                          - installments: Number of installments (1-12)
     * @return ChargeResult Contains payment status and card details
     */
    public function createCardCharge(
        Customer $customer,
        int $amount,
        string $cardToken,
        array $options = []
    ): ChargeResult {
        $asaasId = $this->ensureCustomer($customer);

        $paymentData = [
            'customer' => $asaasId,
            'billingType' => 'CREDIT_CARD',
            'value' => $amount / 100,
            'dueDate' => now()->format('Y-m-d'),
            'description' => $options['description'] ?? 'Card Payment',
            'externalReference' => $options['reference'] ?? null,
            'creditCardToken' => $cardToken,
            'remoteIp' => $options['remote_ip'] ?? request()->ip(),
        ];

        // Add installments if more than 1
        $installments = $options['installments'] ?? 1;
        if ($installments > 1) {
            $paymentData['installmentCount'] = min($installments, 12);
            $paymentData['installmentValue'] = ($amount / 100) / $installments;
        }

        try {
            $response = $this->http()->post('/payments', $paymentData);

            if ($response->failed()) {
                Log::error('Asaas card charge failed', [
                    'customer_id' => $customer->id,
                    'response' => $response->json(),
                ]);

                return ChargeResult::failed(
                    $response->json('errors.0.description', 'Card charge failed')
                );
            }

            $data = $response->json();

            return ChargeResult::success(
                providerPaymentId: $data['id'],
                status: $this->mapPaymentStatus($data['status']),
                providerData: [
                    'amount' => (int) ($data['value'] * 100),
                    'currency' => 'BRL',
                    'billing_type' => 'CREDIT_CARD',
                    'invoice_url' => $data['invoiceUrl'] ?? null,
                    'card' => [
                        'last_four' => $data['creditCard']['creditCardNumber'] ?? null,
                        'brand' => $data['creditCard']['creditCardBrand'] ?? null,
                    ],
                    'installments' => $installments,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Asaas card charge exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return ChargeResult::failed($e->getMessage());
        }
    }

    /**
     * Create a credit card charge with raw card data.
     *
     * Note: This method handles sensitive card data. Ensure PCI compliance.
     * Consider using tokenizeCard() first for recurring payments.
     *
     * @param  Customer  $customer  The customer to charge
     * @param  int  $amount  Amount in cents (BRL)
     * @param  array  $cardData  Card details:
     *                           - holder_name: Name on card
     *                           - number: Card number (16 digits)
     *                           - exp_month: Expiration month (MM)
     *                           - exp_year: Expiration year (YYYY)
     *                           - cvv: Security code
     * @param  array  $holderInfo  Cardholder details (required by Asaas):
     *                             - name: Full name
     *                             - email: Email address
     *                             - cpf_cnpj: CPF or CNPJ (no formatting)
     *                             - postal_code: CEP
     *                             - address_number: Street number
     *                             - phone: (optional)
     * @param  array  $options  Additional options:
     *                          - description: Payment description
     *                          - reference: External reference ID
     *                          - remote_ip: Client IP
     *                          - installments: Number of installments
     * @return ChargeResult Contains payment status, card token for future use
     */
    public function createCardChargeWithData(
        Customer $customer,
        int $amount,
        array $cardData,
        array $holderInfo,
        array $options = []
    ): ChargeResult {
        $asaasId = $this->ensureCustomer($customer);

        $paymentData = [
            'customer' => $asaasId,
            'billingType' => 'CREDIT_CARD',
            'value' => $amount / 100,
            'dueDate' => now()->format('Y-m-d'),
            'description' => $options['description'] ?? 'Card Payment',
            'externalReference' => $options['reference'] ?? null,
            'remoteIp' => $options['remote_ip'] ?? request()->ip(),
            'creditCard' => [
                'holderName' => $cardData['holder_name'],
                'number' => $cardData['number'],
                'expiryMonth' => $cardData['exp_month'],
                'expiryYear' => $cardData['exp_year'],
                'ccv' => $cardData['cvv'],
            ],
            'creditCardHolderInfo' => [
                'name' => $holderInfo['name'],
                'email' => $holderInfo['email'],
                'cpfCnpj' => preg_replace('/\D/', '', $holderInfo['cpf_cnpj']),
                'postalCode' => preg_replace('/\D/', '', $holderInfo['postal_code']),
                'addressNumber' => $holderInfo['address_number'],
                'addressComplement' => $holderInfo['address_complement'] ?? null,
                'phone' => $holderInfo['phone'] ?? null,
                'mobilePhone' => $holderInfo['mobile_phone'] ?? null,
            ],
        ];

        // Add installments if more than 1
        $installments = $options['installments'] ?? 1;
        if ($installments > 1) {
            $paymentData['installmentCount'] = min($installments, 12);
            $paymentData['installmentValue'] = ($amount / 100) / $installments;
        }

        try {
            $response = $this->http()->post('/payments', $paymentData);

            if ($response->failed()) {
                Log::error('Asaas card charge with data failed', [
                    'customer_id' => $customer->id,
                    'response' => $response->json(),
                ]);

                return ChargeResult::failed(
                    $response->json('errors.0.description', 'Card charge failed')
                );
            }

            $data = $response->json();

            return ChargeResult::success(
                providerPaymentId: $data['id'],
                status: $this->mapPaymentStatus($data['status']),
                providerData: [
                    'amount' => (int) ($data['value'] * 100),
                    'currency' => 'BRL',
                    'billing_type' => 'CREDIT_CARD',
                    'invoice_url' => $data['invoiceUrl'] ?? null,
                    'card' => [
                        'last_four' => $data['creditCard']['creditCardNumber'] ?? null,
                        'brand' => $data['creditCard']['creditCardBrand'] ?? null,
                        'token' => $data['creditCardToken'] ?? null,
                    ],
                    'installments' => $installments,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Asaas card charge with data exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return ChargeResult::failed($e->getMessage());
        }
    }

    /**
     * Tokenize a credit card for future use.
     *
     * Creates a token that can be stored and used for future payments.
     * The token is tied to a specific customer and cannot be shared.
     *
     * @param  Customer  $customer  The customer owning the card
     * @param  array  $cardData  Card details:
     *                           - holder_name: Name on card
     *                           - number: Card number (16 digits)
     *                           - exp_month: Expiration month (MM)
     *                           - exp_year: Expiration year (YYYY)
     *                           - cvv: Security code
     * @param  array  $holderInfo  Cardholder details:
     *                             - name: Full name
     *                             - email: Email address
     *                             - cpf_cnpj: CPF or CNPJ
     *                             - postal_code: CEP
     *                             - address_number: Street number
     * @param  array  $options  Additional options:
     *                          - remote_ip: Client IP for fraud prevention
     * @return array{success: bool, token?: string, last_four?: string, brand?: string, error?: string}
     */
    public function tokenizeCard(
        Customer $customer,
        array $cardData,
        array $holderInfo,
        array $options = []
    ): array {
        $asaasId = $this->ensureCustomer($customer);

        try {
            $response = $this->http()->post('/creditCard/tokenize', [
                'customer' => $asaasId,
                'creditCard' => [
                    'holderName' => $cardData['holder_name'],
                    'number' => $cardData['number'],
                    'expiryMonth' => $cardData['exp_month'],
                    'expiryYear' => $cardData['exp_year'],
                    'ccv' => $cardData['cvv'],
                ],
                'creditCardHolderInfo' => [
                    'name' => $holderInfo['name'],
                    'email' => $holderInfo['email'],
                    'cpfCnpj' => preg_replace('/\D/', '', $holderInfo['cpf_cnpj']),
                    'postalCode' => preg_replace('/\D/', '', $holderInfo['postal_code']),
                    'addressNumber' => $holderInfo['address_number'],
                    'addressComplement' => $holderInfo['address_complement'] ?? null,
                    'phone' => $holderInfo['phone'] ?? null,
                    'mobilePhone' => $holderInfo['mobile_phone'] ?? null,
                ],
                'remoteIp' => $options['remote_ip'] ?? request()->ip(),
            ]);

            if ($response->failed()) {
                Log::error('Asaas card tokenization failed', [
                    'customer_id' => $customer->id,
                    'response' => $response->json(),
                ]);

                return [
                    'success' => false,
                    'error' => $response->json('errors.0.description', 'Tokenization failed'),
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'token' => $data['creditCardToken'],
                'last_four' => $data['creditCardNumber'],
                'brand' => $data['creditCardBrand'],
            ];
        } catch (\Exception $e) {
            Log::error('Asaas card tokenization exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get customer's saved credit cards.
     *
     * Note: Asaas stores cards per customer, not as separate entities.
     * This retrieves the tokenized card info stored for a customer.
     *
     * @param  Customer  $customer  The customer
     * @return array List of saved cards with token, last_four, brand
     */
    public function getCustomerCards(Customer $customer): array
    {
        $asaasId = $customer->getProviderId('asaas');

        if (! $asaasId) {
            return [];
        }

        try {
            // Asaas doesn't have a dedicated endpoint for listing cards
            // Cards are returned with customer info or during payment
            $response = $this->http()->get("/customers/{$asaasId}");

            if ($response->failed()) {
                return [];
            }

            // Check if customer has credit card info
            // Note: This may not return full card list - Asaas API limitation
            $data = $response->json();

            // If we have stored tokens in our system, return those
            // This is a placeholder for actual card storage logic
            return [];
        } catch (\Exception $e) {
            Log::error('Failed to get Asaas customer cards', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Validate card data before processing.
     *
     * Performs basic validation on card data format.
     *
     * @param  array  $cardData  Card details to validate
     * @return array{valid: bool, errors: array}
     */
    public function validateCardData(array $cardData): array
    {
        $errors = [];

        // Check required fields
        $required = ['holder_name', 'number', 'exp_month', 'exp_year', 'cvv'];
        foreach ($required as $field) {
            if (empty($cardData[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Validate card number (basic Luhn check)
        if (! empty($cardData['number'])) {
            $number = preg_replace('/\D/', '', $cardData['number']);
            if (strlen($number) < 13 || strlen($number) > 19) {
                $errors[] = 'Invalid card number length';
            } elseif (! $this->luhnCheck($number)) {
                $errors[] = 'Invalid card number';
            }
        }

        // Validate expiry
        if (! empty($cardData['exp_month']) && ! empty($cardData['exp_year'])) {
            $month = (int) $cardData['exp_month'];
            $year = (int) $cardData['exp_year'];

            if ($month < 1 || $month > 12) {
                $errors[] = 'Invalid expiration month';
            }

            $currentYear = (int) date('Y');
            $currentMonth = (int) date('m');

            if ($year < $currentYear || ($year === $currentYear && $month < $currentMonth)) {
                $errors[] = 'Card has expired';
            }
        }

        // Validate CVV
        if (! empty($cardData['cvv'])) {
            $cvv = preg_replace('/\D/', '', $cardData['cvv']);
            if (strlen($cvv) < 3 || strlen($cvv) > 4) {
                $errors[] = 'Invalid CVV length';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Detect card brand from number.
     *
     * @param  string  $number  Card number
     * @return string|null Card brand or null if unknown
     */
    public function detectCardBrand(string $number): ?string
    {
        $number = preg_replace('/\D/', '', $number);

        $patterns = [
            'visa' => '/^4/',
            'mastercard' => '/^(5[1-5]|2[2-7])/',
            'amex' => '/^3[47]/',
            'diners' => '/^3(?:0[0-5]|[68])/',
            'discover' => '/^6(?:011|5)/',
            'jcb' => '/^(?:2131|1800|35)/',
            'elo' => '/^(?:4011(78|79)|43(1274|8935)|45(1416|7393|763(1|2))|50(4175|6699|67[0-7][0-9]|9000)|627780|63(6297|6368)|650(03([^4])|04([0-9])|05(0|1)|4(0[5-9]|3[0-9]|8[5-9]|9[0-9])|5([0-2][0-9]|3[0-8])|9([2-6][0-9]|7[0-8])|541|700|720|901)|651652|655000|655021)/',
            'hipercard' => '/^(606282|3841)/',
        ];

        foreach ($patterns as $brand => $pattern) {
            if (preg_match($pattern, $number)) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * Luhn algorithm for card number validation.
     */
    protected function luhnCheck(string $number): bool
    {
        $sum = 0;
        $length = strlen($number);
        $parity = $length % 2;

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $number[$i];

            if ($i % 2 === $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    // =========================================================================
    // PIX Payment Methods
    // =========================================================================

    /**
     * Create a PIX charge.
     *
     * Creates a payment with PIX billing type and retrieves the QR code data.
     *
     * @param  Customer  $customer  The customer to charge
     * @param  int  $amount  Amount in cents (BRL)
     * @param  array  $options  Additional options:
     *                          - description: Payment description
     *                          - reference: External reference ID
     *                          - due_date: Payment due date (Y-m-d format)
     *                          - expires_in_seconds: QR code expiration (default: 3600)
     * @return ChargeResult Contains QR code, copy-paste code, and expiration
     */
    public function createPixCharge(Customer $customer, int $amount, array $options = []): ChargeResult
    {
        $asaasId = $this->ensureCustomer($customer);

        $dueDate = $options['due_date'] ?? now()->addDays(1)->format('Y-m-d');

        try {
            $response = $this->http()->post('/payments', [
                'customer' => $asaasId,
                'billingType' => 'PIX',
                'value' => $amount / 100,
                'dueDate' => $dueDate,
                'description' => $options['description'] ?? 'PIX Payment',
                'externalReference' => $options['reference'] ?? null,
            ]);

            if ($response->failed()) {
                Log::error('Asaas PIX charge failed', [
                    'customer_id' => $customer->id,
                    'response' => $response->json(),
                ]);

                return ChargeResult::failed(
                    $response->json('errors.0.description', 'PIX charge failed')
                );
            }

            $paymentData = $response->json();

            // Fetch PIX QR code data
            $pixData = $this->fetchPixQrCode($paymentData['id']);

            return ChargeResult::success(
                providerPaymentId: $paymentData['id'],
                status: $this->mapPaymentStatus($paymentData['status']),
                providerData: [
                    'amount' => (int) ($paymentData['value'] * 100),
                    'currency' => 'BRL',
                    'billing_type' => 'PIX',
                    'due_date' => $paymentData['dueDate'],
                    'invoice_url' => $paymentData['invoiceUrl'] ?? null,
                    'pix' => $pixData,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Asaas PIX charge exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return ChargeResult::failed($e->getMessage());
        }
    }

    /**
     * Get PIX QR code data for an existing payment.
     *
     * Useful for refreshing QR code display or retrieving after creation.
     *
     * @param  string  $paymentId  The Asaas payment ID
     * @return array|null QR code data or null if not available
     */
    public function getPixQrCode(string $paymentId): ?array
    {
        return $this->fetchPixQrCode($paymentId);
    }

    /**
     * Fetch PIX QR code from Asaas API.
     */
    protected function fetchPixQrCode(string $paymentId): ?array
    {
        try {
            $response = $this->http()->get("/payments/{$paymentId}/pixQrCode");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'qr_code' => $data['encodedImage'] ?? null,
                    'qr_code_base64' => $data['encodedImage'] ?? null,
                    'copy_paste' => $data['payload'] ?? null,
                    'payload' => $data['payload'] ?? null,
                    'expiration' => $data['expirationDate'] ?? null,
                    'expiration_date' => $data['expirationDate'] ?? null,
                ];
            }

            Log::warning('Failed to fetch PIX QR code', [
                'payment_id' => $paymentId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        } catch (\Exception $e) {
            Log::error('PIX QR code fetch exception', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    // =========================================================================
    // Boleto Payment Methods
    // =========================================================================

    /**
     * Create a Boleto charge.
     *
     * Creates a payment with BOLETO billing type and retrieves the barcode data.
     *
     * @param  Customer  $customer  The customer to charge
     * @param  int  $amount  Amount in cents (BRL)
     * @param  array  $options  Additional options:
     *                          - description: Payment description
     *                          - reference: External reference ID
     *                          - due_date: Payment due date (Y-m-d format, default: 3 days)
     *                          - fine_value: Late payment fine percentage (0-10)
     *                          - interest_value: Monthly interest percentage (0-10)
     * @return ChargeResult Contains barcode, URL, and digitable line
     */
    public function createBoletoCharge(Customer $customer, int $amount, array $options = []): ChargeResult
    {
        $asaasId = $this->ensureCustomer($customer);

        $dueDate = $options['due_date'] ?? now()->addDays(3)->format('Y-m-d');

        $paymentData = [
            'customer' => $asaasId,
            'billingType' => 'BOLETO',
            'value' => $amount / 100,
            'dueDate' => $dueDate,
            'description' => $options['description'] ?? 'Boleto Payment',
            'externalReference' => $options['reference'] ?? null,
        ];

        // Add optional fine and interest settings
        if (isset($options['fine_value'])) {
            $paymentData['fine'] = [
                'value' => $options['fine_value'],
                'type' => 'PERCENTAGE',
            ];
        }

        if (isset($options['interest_value'])) {
            $paymentData['interest'] = [
                'value' => $options['interest_value'],
                'type' => 'PERCENTAGE',
            ];
        }

        try {
            $response = $this->http()->post('/payments', $paymentData);

            if ($response->failed()) {
                Log::error('Asaas Boleto charge failed', [
                    'customer_id' => $customer->id,
                    'response' => $response->json(),
                ]);

                return ChargeResult::failed(
                    $response->json('errors.0.description', 'Boleto charge failed')
                );
            }

            $data = $response->json();

            // Fetch boleto identification field (digitable line)
            $boletoDetails = $this->fetchBoletoDetails($data['id']);

            return ChargeResult::success(
                providerPaymentId: $data['id'],
                status: $this->mapPaymentStatus($data['status']),
                providerData: [
                    'amount' => (int) ($data['value'] * 100),
                    'currency' => 'BRL',
                    'billing_type' => 'BOLETO',
                    'due_date' => $data['dueDate'],
                    'invoice_url' => $data['invoiceUrl'] ?? null,
                    'boleto' => array_merge([
                        'url' => $data['bankSlipUrl'] ?? null,
                        'bank_slip_url' => $data['bankSlipUrl'] ?? null,
                        'nosso_numero' => $data['nossoNumero'] ?? null,
                    ], $boletoDetails ?? []),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Asaas Boleto charge exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return ChargeResult::failed($e->getMessage());
        }
    }

    /**
     * Get Boleto identification field (digitable line/barcode) for an existing payment.
     *
     * @param  string  $paymentId  The Asaas payment ID
     * @return array|null Boleto details or null if not available
     */
    public function getBoletoIdentificationField(string $paymentId): ?array
    {
        return $this->fetchBoletoDetails($paymentId);
    }

    /**
     * Fetch Boleto details from Asaas API.
     */
    protected function fetchBoletoDetails(string $paymentId): ?array
    {
        try {
            $response = $this->http()->get("/payments/{$paymentId}/identificationField");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'identification_field' => $data['identificationField'] ?? null,
                    'digitable_line' => $data['identificationField'] ?? null,
                    'barcode' => $data['barCode'] ?? null,
                    'bar_code' => $data['barCode'] ?? null,
                ];
            }

            Log::warning('Failed to fetch Boleto identification field', [
                'payment_id' => $paymentId,
                'status' => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::error('Boleto identification field fetch exception', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    // =========================================================================
    // Payment Retrieval Methods
    // =========================================================================

    /**
     * Retrieve payment details from Asaas.
     *
     * @param  string  $paymentId  The Asaas payment ID
     * @return array Payment data
     *
     * @throws \RuntimeException If retrieval fails
     */
    public function retrievePayment(string $paymentId): array
    {
        $response = $this->http()->get("/payments/{$paymentId}");

        if ($response->failed()) {
            throw new \RuntimeException(
                'Failed to retrieve payment: '.$response->json('errors.0.description', 'Unknown error')
            );
        }

        return $response->json();
    }

    /**
     * List payments for a customer.
     *
     * @param  Customer  $customer  The customer
     * @param  array  $filters  Optional filters:
     *                          - status: Payment status
     *                          - billing_type: PIX, BOLETO, CREDIT_CARD
     *                          - due_date_start: Start date (Y-m-d)
     *                          - due_date_end: End date (Y-m-d)
     *                          - limit: Results per page (default: 10)
     *                          - offset: Pagination offset
     * @return array List of payments
     */
    public function listPayments(Customer $customer, array $filters = []): array
    {
        $asaasId = $customer->getProviderId('asaas');

        if (! $asaasId) {
            return ['data' => [], 'totalCount' => 0];
        }

        $queryParams = ['customer' => $asaasId];

        if (isset($filters['status'])) {
            $queryParams['status'] = $filters['status'];
        }

        if (isset($filters['billing_type'])) {
            $queryParams['billingType'] = strtoupper($filters['billing_type']);
        }

        if (isset($filters['due_date_start'])) {
            $queryParams['dueDate[ge]'] = $filters['due_date_start'];
        }

        if (isset($filters['due_date_end'])) {
            $queryParams['dueDate[le]'] = $filters['due_date_end'];
        }

        $queryParams['limit'] = $filters['limit'] ?? 10;
        $queryParams['offset'] = $filters['offset'] ?? 0;

        try {
            $response = $this->http()->get('/payments', $queryParams);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error('Failed to list Asaas payments', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }

        return ['data' => [], 'totalCount' => 0];
    }

    // =========================================================================
    // Webhook Processing Methods
    // =========================================================================

    /**
     * Parse and validate webhook payload.
     *
     * @param  array  $payload  Raw webhook payload from Asaas
     * @return array Normalized webhook data
     */
    public function parseWebhookPayload(array $payload): array
    {
        $event = $payload['event'] ?? '';
        $payment = $payload['payment'] ?? [];

        return [
            'event' => $event,
            'event_type' => $this->normalizeWebhookEvent($event),
            'payment_id' => $payment['id'] ?? null,
            'external_reference' => $payment['externalReference'] ?? null,
            'status' => $payment['status'] ?? null,
            'normalized_status' => isset($payment['status'])
                ? $this->mapPaymentStatus($payment['status'])
                : null,
            'billing_type' => $payment['billingType'] ?? null,
            'value' => isset($payment['value']) ? (int) ($payment['value'] * 100) : null,
            'net_value' => isset($payment['netValue']) ? (int) ($payment['netValue'] * 100) : null,
            'due_date' => $payment['dueDate'] ?? null,
            'payment_date' => $payment['paymentDate'] ?? null,
            'confirmed_date' => $payment['confirmedDate'] ?? null,
            'raw_payload' => $payload,
        ];
    }

    /**
     * Normalize Asaas webhook event to internal event type.
     */
    protected function normalizeWebhookEvent(string $event): string
    {
        return match ($event) {
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => 'payment.confirmed',
            'PAYMENT_CREATED' => 'payment.created',
            'PAYMENT_UPDATED' => 'payment.updated',
            'PAYMENT_OVERDUE' => 'payment.overdue',
            'PAYMENT_DELETED' => 'payment.deleted',
            'PAYMENT_REFUNDED' => 'payment.refunded',
            'PAYMENT_REFUND_IN_PROGRESS' => 'payment.refund_pending',
            'PAYMENT_CHARGEBACK_REQUESTED' => 'payment.disputed',
            'PAYMENT_CHARGEBACK_DISPUTE' => 'payment.dispute_created',
            'PAYMENT_AWAITING_CHARGEBACK_REVERSAL' => 'payment.dispute_pending',
            default => 'payment.unknown',
        };
    }

    /**
     * Check if webhook event indicates successful payment.
     */
    public function isPaymentConfirmedEvent(string $event): bool
    {
        return in_array($event, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED']);
    }

    /**
     * Check if webhook event indicates payment failure.
     */
    public function isPaymentFailedEvent(string $event): bool
    {
        return in_array($event, [
            'PAYMENT_OVERDUE',
            'PAYMENT_DELETED',
            'PAYMENT_REFUNDED',
            'PAYMENT_CHARGEBACK_REQUESTED',
        ]);
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
