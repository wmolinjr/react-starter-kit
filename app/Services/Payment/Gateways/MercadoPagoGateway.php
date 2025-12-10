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
 * MercadoPago Gateway
 *
 * Latin America payment provider supporting:
 * - Credit Card
 * - Debit Card
 * - PIX (Brazil)
 * - Boleto (Brazil)
 * - Local payment methods
 *
 * @see https://www.mercadopago.com.br/developers/
 */
class MercadoPagoGateway implements PaymentGatewayInterface, PaymentMethodGatewayInterface, SubscriptionGatewayInterface
{
    protected string $accessToken;

    protected string $publicKey;

    protected string $baseUrl = 'https://api.mercadopago.com';

    protected bool $sandbox;

    public function __construct(array $config)
    {
        $this->accessToken = $config['access_token'] ?? '';
        $this->publicKey = $config['public_key'] ?? '';
        $this->sandbox = $config['sandbox'] ?? true;
    }

    /**
     * Get HTTP client with authentication.
     */
    protected function http(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
            'X-Idempotency-Key' => uniqid('mp_'),
        ])->baseUrl($this->baseUrl);
    }

    // =========================================================================
    // PaymentGatewayInterface Implementation
    // =========================================================================

    public function getIdentifier(): string
    {
        return 'mercadopago';
    }

    public function getDisplayName(): string
    {
        return 'MercadoPago';
    }

    public function getSupportedTypes(): array
    {
        return ['card', 'pix', 'boleto', 'debit'];
    }

    public function getSupportedCurrencies(): array
    {
        return ['BRL', 'ARS', 'CLP', 'COP', 'MXN', 'PEN', 'UYU'];
    }

    public function isAvailable(): bool
    {
        return ! empty($this->accessToken);
    }

    public function ensureCustomer(Customer $customer): string
    {
        $mpCustomerId = $customer->getProviderId('mercadopago');

        if ($mpCustomerId) {
            return $mpCustomerId;
        }

        return $this->createMercadoPagoCustomer($customer);
    }

    /**
     * Create a charge for a customer.
     */
    public function charge(Customer $customer, int $amount, array $options = []): ChargeResult
    {
        $paymentType = strtolower($options['payment_type'] ?? $options['billing_type'] ?? 'card');

        return match ($paymentType) {
            'pix' => $this->createPixCharge($customer, $amount, $options),
            'boleto' => $this->createBoletoCharge($customer, $amount, $options),
            default => $this->createCardCharge($customer, $amount, $options),
        };
    }

    /**
     * Create a PIX charge.
     */
    public function createPixCharge(Customer $customer, int $amount, array $options = []): ChargeResult
    {
        try {
            $response = $this->http()->post('/v1/payments', [
                'transaction_amount' => $amount / 100,
                'description' => $options['description'] ?? 'PIX Payment',
                'payment_method_id' => 'pix',
                'payer' => $this->buildPayerData($customer),
                'external_reference' => $options['reference'] ?? null,
                'notification_url' => config('app.url') . '/webhooks/mercadopago',
            ]);

            if ($response->failed()) {
                Log::error('MercadoPago PIX charge failed', [
                    'customer_id' => $customer->id,
                    'response' => $response->json(),
                ]);

                return ChargeResult::failed(
                    $response->json('message', 'PIX charge failed')
                );
            }

            $data = $response->json();
            $pix = $data['point_of_interaction']['transaction_data'] ?? null;

            return ChargeResult::success(
                providerPaymentId: (string) $data['id'],
                status: $this->mapPaymentStatus($data['status']),
                providerData: [
                    'amount' => $amount,
                    'currency' => 'BRL',
                    'billing_type' => 'PIX',
                    'pix' => [
                        'qr_code' => $pix['qr_code'] ?? null,
                        'qr_code_base64' => $pix['qr_code_base64'] ?? null,
                        'copy_paste' => $pix['qr_code'] ?? null,
                        'ticket_url' => $pix['ticket_url'] ?? null,
                        'expiration_date' => $data['date_of_expiration'] ?? null,
                    ],
                ]
            );
        } catch (\Exception $e) {
            Log::error('MercadoPago PIX charge exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return ChargeResult::failed($e->getMessage());
        }
    }

    /**
     * Create a Boleto charge.
     */
    public function createBoletoCharge(Customer $customer, int $amount, array $options = []): ChargeResult
    {
        $dueDate = $options['due_date'] ?? now()->addDays(3)->toIso8601String();

        try {
            $response = $this->http()->post('/v1/payments', [
                'transaction_amount' => $amount / 100,
                'description' => $options['description'] ?? 'Boleto Payment',
                'payment_method_id' => 'bolbradesco', // Bradesco boleto
                'payer' => $this->buildPayerData($customer, true), // Boleto requires address
                'external_reference' => $options['reference'] ?? null,
                'date_of_expiration' => $dueDate,
                'notification_url' => config('app.url') . '/webhooks/mercadopago',
            ]);

            if ($response->failed()) {
                Log::error('MercadoPago Boleto charge failed', [
                    'customer_id' => $customer->id,
                    'response' => $response->json(),
                ]);

                return ChargeResult::failed(
                    $response->json('message', 'Boleto charge failed')
                );
            }

            $data = $response->json();

            return ChargeResult::success(
                providerPaymentId: (string) $data['id'],
                status: $this->mapPaymentStatus($data['status']),
                providerData: [
                    'amount' => $amount,
                    'currency' => 'BRL',
                    'billing_type' => 'BOLETO',
                    'boleto' => [
                        'barcode' => $data['barcode']['content'] ?? null,
                        'digitable_line' => $data['barcode']['content'] ?? null,
                        'url' => $data['transaction_details']['external_resource_url'] ?? null,
                        'due_date' => $data['date_of_expiration'] ?? $dueDate,
                    ],
                ]
            );
        } catch (\Exception $e) {
            Log::error('MercadoPago Boleto charge exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return ChargeResult::failed($e->getMessage());
        }
    }

    /**
     * Create a Card charge.
     */
    public function createCardCharge(Customer $customer, int $amount, array $options = []): ChargeResult
    {
        $installments = $options['installments'] ?? 1;

        try {
            $paymentData = [
                'transaction_amount' => $amount / 100,
                'description' => $options['description'] ?? 'Card Payment',
                'installments' => $installments,
                'payer' => $this->buildPayerData($customer),
                'external_reference' => $options['reference'] ?? null,
                'notification_url' => config('app.url') . '/webhooks/mercadopago',
            ];

            // Use card token from MercadoPago.js
            if (isset($options['card_token'])) {
                $paymentData['token'] = $options['card_token'];
            }

            // Payment method ID (visa, master, etc.)
            if (isset($options['payment_method_id'])) {
                $paymentData['payment_method_id'] = $options['payment_method_id'];
            }

            // Issuer ID for specific bank
            if (isset($options['issuer_id'])) {
                $paymentData['issuer_id'] = $options['issuer_id'];
            }

            $response = $this->http()->post('/v1/payments', $paymentData);

            if ($response->failed()) {
                Log::error('MercadoPago Card charge failed', [
                    'customer_id' => $customer->id,
                    'response' => $response->json(),
                ]);

                return ChargeResult::failed(
                    $response->json('message', 'Card charge failed')
                );
            }

            $data = $response->json();

            return ChargeResult::success(
                providerPaymentId: (string) $data['id'],
                status: $this->mapPaymentStatus($data['status']),
                providerData: [
                    'amount' => $amount,
                    'currency' => $data['currency_id'] ?? 'BRL',
                    'billing_type' => 'CREDIT_CARD',
                    'card' => [
                        'last_four' => $data['card']['last_four_digits'] ?? null,
                        'first_six' => $data['card']['first_six_digits'] ?? null,
                        'brand' => $data['payment_method_id'] ?? null,
                        'expiration_month' => $data['card']['expiration_month'] ?? null,
                        'expiration_year' => $data['card']['expiration_year'] ?? null,
                    ],
                    'installments' => $installments,
                    'status_detail' => $data['status_detail'] ?? null,
                ]
            );
        } catch (\Exception $e) {
            Log::error('MercadoPago Card charge exception', [
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
            $payload = [];
            if ($amount) {
                $payload['amount'] = $amount / 100;
            }

            $response = $this->http()->post("/v1/payments/{$paymentId}/refunds", $payload);

            if ($response->failed()) {
                return RefundResult::failed(
                    $response->json('message', 'Refund failed')
                );
            }

            $data = $response->json();

            return RefundResult::success(
                providerRefundId: (string) ($data['id'] ?? $paymentId),
                amountRefunded: (int) (($data['amount'] ?? ($amount / 100)) * 100)
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
            $response = $this->http()->get("/v1/payments/{$paymentId}");

            if ($response->failed()) {
                return 'failed';
            }

            $data = $response->json();

            return $this->mapPaymentStatus($data['status'] ?? 'rejected');
        } catch (\Exception $e) {
            Log::error('MercadoPago checkStatus failed', [
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
        $webhookSecret = config('payment.drivers.mercadopago.webhook_secret', '');

        if (empty($webhookSecret)) {
            return true; // Skip validation if no secret configured
        }

        // MercadoPago uses x-signature header with format: ts=xxx,v1=xxx
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = explode('=', $part, 2);
            $parts[$key] = $value;
        }

        $ts = $parts['ts'] ?? '';
        $v1 = $parts['v1'] ?? '';
        $id = $parts['id'] ?? '';
        $requestId = $parts['request-id'] ?? '';

        // Build the signed payload
        $manifest = "id:{$id};request-id:{$requestId};ts:{$ts};";
        $expectedSignature = hash_hmac('sha256', $manifest, $webhookSecret);

        return hash_equals($expectedSignature, $v1);
    }

    /**
     * Handle incoming webhook.
     */
    public function handleWebhook(array $payload, array $headers = []): void
    {
        $type = $payload['type'] ?? null;
        $action = $payload['action'] ?? null;
        $dataId = $payload['data']['id'] ?? null;

        Log::info('MercadoPago webhook received', [
            'type' => $type,
            'action' => $action,
            'data_id' => $dataId,
        ]);

        if ($type === 'payment' && $dataId) {
            $this->processPaymentWebhook((string) $dataId);
        }
    }

    /**
     * Process payment webhook.
     */
    protected function processPaymentWebhook(string $paymentId): void
    {
        try {
            // Fetch payment details
            $response = $this->http()->get("/v1/payments/{$paymentId}");

            if ($response->failed()) {
                Log::warning('Failed to fetch MercadoPago payment', [
                    'payment_id' => $paymentId,
                ]);

                return;
            }

            $data = $response->json();
            $status = $data['status'] ?? null;
            $reference = $data['external_reference'] ?? null;

            if (! $reference) {
                return;
            }

            $purchase = $this->findPurchaseByReference($reference);

            if (! $purchase) {
                Log::info('MercadoPago payment but no matching purchase', [
                    'reference' => $reference,
                    'payment_id' => $paymentId,
                ]);

                return;
            }

            // Dispatch appropriate event based on status
            if ($status === 'approved') {
                \App\Events\Payment\PaymentConfirmed::dispatch(
                    $purchase,
                    'mercadopago',
                    $paymentId,
                    [
                        'payment_type' => $data['payment_type_id'] ?? null,
                        'payment_method' => $data['payment_method_id'] ?? null,
                        'net_amount' => $data['transaction_details']['net_received_amount'] ?? null,
                    ]
                );
            } elseif (in_array($status, ['rejected', 'cancelled'])) {
                \App\Events\Payment\PaymentFailed::dispatch(
                    $purchase,
                    'mercadopago',
                    $data['status_detail'] ?? 'Payment failed',
                    ['status' => $status, 'status_detail' => $data['status_detail'] ?? null]
                );
            }
        } catch (\Exception $e) {
            Log::error('Error processing MercadoPago webhook', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Find AddonPurchase by external reference.
     */
    protected function findPurchaseByReference(string $reference): ?\App\Models\Central\AddonPurchase
    {
        $purchaseId = str_starts_with($reference, 'addon_purchase_')
            ? str_replace('addon_purchase_', '', $reference)
            : $reference;

        return \App\Models\Central\AddonPurchase::find($purchaseId);
    }

    // =========================================================================
    // PaymentMethodGatewayInterface Implementation
    // =========================================================================

    public function createSetupIntent(Customer $customer): SetupIntentResult
    {
        // MercadoPago uses card tokens created client-side
        $intentId = 'mp_setup_' . uniqid();

        return SetupIntentResult::success(
            clientSecret: $intentId,
            providerIntentId: $intentId,
            providerData: [
                'message' => 'Use MercadoPago.js to create card token',
                'public_key' => $this->publicKey,
            ]
        );
    }

    public function attachPaymentMethod(Customer $customer, string $providerMethodId, array $options = []): PaymentMethodResult
    {
        $mpCustomerId = $this->ensureCustomer($customer);

        try {
            // Save card to customer
            $response = $this->http()->post("/v1/customers/{$mpCustomerId}/cards", [
                'token' => $providerMethodId,
            ]);

            if ($response->failed()) {
                return PaymentMethodResult::failed(
                    $response->json('message', 'Failed to save card')
                );
            }

            $data = $response->json();

            return new PaymentMethodResult(
                success: true,
                providerMethodId: $data['id'],
                type: 'card',
                brand: $data['payment_method']['id'] ?? null,
                last4: $data['last_four_digits'] ?? null,
                isDefault: $options['set_as_default'] ?? true
            );
        } catch (\Exception $e) {
            return PaymentMethodResult::failed($e->getMessage());
        }
    }

    public function detachPaymentMethod(PaymentMethod $paymentMethod): bool
    {
        $cardId = $paymentMethod->provider_method_id;
        $customer = $paymentMethod->customer;
        $mpCustomerId = $customer->getProviderId('mercadopago');

        if (! $mpCustomerId || ! $cardId) {
            return false;
        }

        try {
            $response = $this->http()->delete("/v1/customers/{$mpCustomerId}/cards/{$cardId}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to detach MercadoPago card', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function listPaymentMethods(Customer $customer): array
    {
        $mpCustomerId = $customer->getProviderId('mercadopago');

        if (! $mpCustomerId) {
            return [];
        }

        try {
            $response = $this->http()->get("/v1/customers/{$mpCustomerId}/cards");

            if ($response->failed()) {
                return [];
            }

            $cards = $response->json();

            return array_map(fn ($card) => new PaymentMethodResult(
                success: true,
                providerMethodId: $card['id'],
                type: 'card',
                brand: $card['payment_method']['id'] ?? null,
                last4: $card['last_four_digits'] ?? null,
                isDefault: false
            ), $cards);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function setDefaultPaymentMethod(Customer $customer, PaymentMethod $paymentMethod): bool
    {
        // MercadoPago doesn't have a default card concept
        return true;
    }

    public function syncPaymentMethods(Customer $customer): void
    {
        // Sync cards from MercadoPago to local database
        $cards = $this->listPaymentMethods($customer);

        foreach ($cards as $card) {
            if ($card->success) {
                PaymentMethod::updateOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'provider' => 'mercadopago',
                        'provider_method_id' => $card->providerMethodId,
                    ],
                    [
                        'type' => 'card',
                        'brand' => $card->brand,
                        'last_four' => $card->last4,
                    ]
                );
            }
        }
    }

    // =========================================================================
    // SubscriptionGatewayInterface Implementation
    // =========================================================================

    public function createSubscription(Customer $customer, string $priceId, array $options = []): SubscriptionResult
    {
        // MercadoPago has a "Preapproval" (subscription) API
        try {
            $mpCustomerId = $this->ensureCustomer($customer);

            $response = $this->http()->post('/preapproval', [
                'payer_email' => $customer->email,
                'back_url' => $options['success_url'] ?? config('app.url'),
                'reason' => $options['description'] ?? 'Subscription',
                'external_reference' => $priceId,
                'auto_recurring' => [
                    'frequency' => 1,
                    'frequency_type' => $this->mapFrequencyType($options['interval'] ?? 'month'),
                    'transaction_amount' => ($options['amount'] ?? 0) / 100,
                    'currency_id' => 'BRL',
                    'start_date' => now()->toIso8601String(),
                    'end_date' => now()->addYears(10)->toIso8601String(),
                ],
                'notification_url' => config('app.url') . '/webhooks/mercadopago',
            ]);

            if ($response->failed()) {
                return new SubscriptionResult(
                    success: false,
                    providerSubscriptionId: null,
                    status: 'failed',
                    failureMessage: $response->json('message', 'Subscription creation failed')
                );
            }

            $data = $response->json();

            return new SubscriptionResult(
                success: true,
                providerSubscriptionId: $data['id'],
                status: $this->mapPreapprovalStatus($data['status'] ?? 'pending'),
                currentPeriodStart: now(),
                currentPeriodEnd: now()->addMonth(),
                providerData: [
                    'init_point' => $data['init_point'] ?? null,
                    'sandbox_init_point' => $data['sandbox_init_point'] ?? null,
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

    public function updateSubscription(Subscription $subscription, string $newPriceId, array $options = []): SubscriptionResult
    {
        $subscriptionId = $subscription->provider_subscription_id;

        try {
            $updateData = [];

            if (isset($options['amount'])) {
                $updateData['auto_recurring']['transaction_amount'] = $options['amount'] / 100;
            }

            if (isset($options['description'])) {
                $updateData['reason'] = $options['description'];
            }

            $response = $this->http()->put("/preapproval/{$subscriptionId}", $updateData);

            if ($response->failed()) {
                return new SubscriptionResult(
                    success: false,
                    providerSubscriptionId: $subscriptionId,
                    status: 'failed',
                    failureMessage: $response->json('message', 'Update failed')
                );
            }

            $data = $response->json();

            return new SubscriptionResult(
                success: true,
                providerSubscriptionId: $data['id'],
                status: $this->mapPreapprovalStatus($data['status'] ?? 'authorized')
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

    public function pauseSubscription(Subscription $subscription): SubscriptionResult
    {
        $subscriptionId = $subscription->provider_subscription_id;

        try {
            $response = $this->http()->put("/preapproval/{$subscriptionId}", [
                'status' => 'paused',
            ]);

            if ($response->failed()) {
                return new SubscriptionResult(
                    success: false,
                    providerSubscriptionId: $subscriptionId,
                    status: 'failed',
                    failureMessage: $response->json('message', 'Pause failed')
                );
            }

            return new SubscriptionResult(
                success: true,
                providerSubscriptionId: $subscriptionId,
                status: 'paused'
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

    public function syncSubscription(Subscription $subscription): SubscriptionResult
    {
        $subscriptionId = $subscription->provider_subscription_id;

        try {
            $response = $this->http()->get("/preapproval/{$subscriptionId}");

            if ($response->failed()) {
                return new SubscriptionResult(
                    success: false,
                    providerSubscriptionId: $subscriptionId,
                    status: 'failed',
                    failureMessage: 'Failed to sync subscription'
                );
            }

            $data = $response->json();

            return new SubscriptionResult(
                success: true,
                providerSubscriptionId: $subscriptionId,
                status: $this->mapPreapprovalStatus($data['status'] ?? 'pending')
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

    public function createCheckoutSession(Customer $customer, string $priceId, array $options = []): string
    {
        // Create a preference for Checkout Pro
        try {
            $response = $this->http()->post('/checkout/preferences', [
                'items' => [
                    [
                        'title' => $options['description'] ?? 'Subscription',
                        'quantity' => 1,
                        'unit_price' => ($options['amount'] ?? 0) / 100,
                        'currency_id' => 'BRL',
                    ],
                ],
                'payer' => [
                    'email' => $customer->email,
                ],
                'external_reference' => $priceId,
                'back_urls' => [
                    'success' => $options['success_url'] ?? config('app.url'),
                    'failure' => $options['cancel_url'] ?? config('app.url'),
                    'pending' => $options['success_url'] ?? config('app.url'),
                ],
                'notification_url' => config('app.url') . '/webhooks/mercadopago',
                'auto_return' => 'approved',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $this->sandbox
                    ? ($data['sandbox_init_point'] ?? '')
                    : ($data['init_point'] ?? '');
            }
        } catch (\Exception $e) {
            Log::error('Failed to create MercadoPago checkout', ['error' => $e->getMessage()]);
        }

        return $options['success_url'] ?? '/checkout';
    }

    public function createBillingPortalSession(Customer $customer, string $returnUrl): string
    {
        // MercadoPago doesn't have a billing portal
        return $returnUrl;
    }

    public function cancelSubscription(Subscription $subscription, bool $immediately = false): SubscriptionResult
    {
        $subscriptionId = $subscription->provider_subscription_id;

        try {
            $response = $this->http()->put("/preapproval/{$subscriptionId}", [
                'status' => 'cancelled',
            ]);

            if ($response->failed()) {
                return new SubscriptionResult(
                    success: false,
                    providerSubscriptionId: $subscriptionId,
                    status: 'failed',
                    failureMessage: $response->json('message', 'Cancellation failed')
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

    public function resumeSubscription(Subscription $subscription): SubscriptionResult
    {
        $subscriptionId = $subscription->provider_subscription_id;

        try {
            $response = $this->http()->put("/preapproval/{$subscriptionId}", [
                'status' => 'authorized',
            ]);

            if ($response->failed()) {
                return new SubscriptionResult(
                    success: false,
                    providerSubscriptionId: $subscriptionId,
                    status: 'failed',
                    failureMessage: $response->json('message', 'Resume failed')
                );
            }

            return new SubscriptionResult(
                success: true,
                providerSubscriptionId: $subscriptionId,
                status: 'active'
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

    public function retrieveSubscription(string $subscriptionId): array
    {
        try {
            $response = $this->http()->get("/preapproval/{$subscriptionId}");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error('Failed to retrieve MercadoPago subscription', ['error' => $e->getMessage()]);
        }

        return [];
    }

    public function addSubscriptionItem(Subscription $subscription, string $priceId, int $quantity = 1): array
    {
        throw new \RuntimeException('MercadoPago does not support subscription items');
    }

    public function updateSubscriptionItem(Subscription $subscription, string $priceId, int $quantity): void
    {
        throw new \RuntimeException('MercadoPago does not support subscription items');
    }

    public function removeSubscriptionItem(Subscription $subscription, string $priceId): void
    {
        throw new \RuntimeException('MercadoPago does not support subscription items');
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Create a customer in MercadoPago.
     */
    protected function createMercadoPagoCustomer(Customer $customer): string
    {
        try {
            $response = $this->http()->post('/v1/customers', [
                'email' => $customer->email,
                'first_name' => explode(' ', $customer->name ?? '')[0] ?? null,
                'last_name' => explode(' ', $customer->name ?? '', 2)[1] ?? null,
                'identification' => $customer->tax_id ? [
                    'type' => strlen(preg_replace('/\D/', '', $customer->tax_id)) === 11 ? 'CPF' : 'CNPJ',
                    'number' => preg_replace('/\D/', '', $customer->tax_id),
                ] : null,
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to create MercadoPago customer: ' . $response->json('message', 'Unknown error'));
            }

            $mpCustomerId = $response->json('id');
            $customer->setProviderId('mercadopago', $mpCustomerId);

            return $mpCustomerId;
        } catch (\Exception $e) {
            Log::error('MercadoPago customer creation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Build payer data for MercadoPago API.
     */
    protected function buildPayerData(Customer $customer, bool $includeAddress = false): array
    {
        $payer = [
            'email' => $customer->email,
            'first_name' => explode(' ', $customer->name ?? '')[0] ?? null,
            'last_name' => explode(' ', $customer->name ?? '', 2)[1] ?? null,
        ];

        if ($customer->tax_id) {
            $taxId = preg_replace('/\D/', '', $customer->tax_id);
            $payer['identification'] = [
                'type' => strlen($taxId) === 11 ? 'CPF' : 'CNPJ',
                'number' => $taxId,
            ];
        }

        if ($includeAddress && $customer->postal_code) {
            $payer['address'] = [
                'zip_code' => preg_replace('/\D/', '', $customer->postal_code),
                'street_name' => $customer->street ?? null,
                'street_number' => $customer->street_number ?? null,
            ];
        }

        return $payer;
    }

    /**
     * Map MercadoPago payment status to internal status.
     */
    protected function mapPaymentStatus(string $status): string
    {
        return match ($status) {
            'approved' => 'paid',
            'pending', 'in_process', 'in_mediation' => 'pending',
            'authorized' => 'processing',
            'rejected', 'cancelled' => 'failed',
            'refunded' => 'refunded',
            'charged_back' => 'disputed',
            default => 'pending',
        };
    }

    /**
     * Map frequency type for subscriptions.
     */
    protected function mapFrequencyType(string $interval): string
    {
        return match ($interval) {
            'day', 'daily' => 'days',
            'week', 'weekly' => 'weeks',
            'month', 'monthly' => 'months',
            'year', 'yearly' => 'years',
            default => 'months',
        };
    }

    /**
     * Map preapproval (subscription) status.
     */
    protected function mapPreapprovalStatus(string $status): string
    {
        return match ($status) {
            'authorized' => 'active',
            'pending' => 'pending',
            'paused' => 'paused',
            'cancelled' => 'canceled',
            default => 'incomplete',
        };
    }
}
