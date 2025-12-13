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
 * PagSeguro Gateway
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

        // Use URLs from config (single source of truth)
        $this->baseUrl = $this->sandbox
            ? ($config['sandbox_url'] ?? config('payment.drivers.pagseguro.sandbox_url'))
            : ($config['api_url'] ?? config('payment.drivers.pagseguro.api_url'));
    }

    /**
     * Get HTTP client with authentication.
     */
    protected function http(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
            'x-api-version' => '4.0',
        ])->baseUrl($this->baseUrl);
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
        // PagSeguro doesn't require pre-created customers
        // Customer data is sent with each transaction
        return $customer->id;
    }

    /**
     * Create a charge for a customer.
     */
    public function charge(Customer $customer, int $amount, array $options = []): ChargeResult
    {
        $paymentType = strtoupper($options['payment_type'] ?? $options['billing_type'] ?? 'CREDIT_CARD');

        return match ($paymentType) {
            'PIX' => $this->createPixCharge($customer, $amount, $options),
            'BOLETO' => $this->createBoletoCharge($customer, $amount, $options),
            default => $this->createCardCharge($customer, $amount, $options),
        };
    }

    /**
     * Create a PIX charge.
     */
    public function createPixCharge(Customer $customer, int $amount, array $options = []): ChargeResult
    {
        try {
            $response = $this->http()->post('/orders', [
                'reference_id' => $options['reference'] ?? uniqid('pix_'),
                'customer' => $this->buildCustomerData($customer),
                'items' => [
                    [
                        'reference_id' => 'item_1',
                        'name' => $options['description'] ?? 'PIX Payment',
                        'quantity' => 1,
                        'unit_amount' => $amount,
                    ],
                ],
                'qr_codes' => [
                    [
                        'amount' => ['value' => $amount],
                        'expiration_date' => now()->addMinutes($options['expires_in_minutes'] ?? 30)->toIso8601String(),
                    ],
                ],
                'notification_urls' => [
                    config('app.url').'/webhooks/pagseguro',
                ],
            ]);

            if ($response->failed()) {
                Log::error('PagSeguro PIX charge failed', [
                    'customer_id' => $customer->id,
                    'response' => $response->json(),
                ]);

                return ChargeResult::failed(
                    $response->json('error_messages.0.description', 'PIX charge failed')
                );
            }

            $data = $response->json();
            $qrCode = $data['qr_codes'][0] ?? null;

            return ChargeResult::success(
                providerPaymentId: $data['id'],
                status: $this->mapOrderStatus($data['status'] ?? 'PENDING'),
                providerData: [
                    'amount' => $amount,
                    'currency' => 'BRL',
                    'billing_type' => 'PIX',
                    'pix' => [
                        'qr_code' => $qrCode['links'][0]['href'] ?? null,
                        'qr_code_base64' => $qrCode['text'] ?? null,
                        'copy_paste' => $qrCode['text'] ?? null,
                        'expiration_date' => $qrCode['expiration_date'] ?? null,
                    ],
                ]
            );
        } catch (\Exception $e) {
            Log::error('PagSeguro PIX charge exception', [
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
        $dueDate = $options['due_date'] ?? now()->addDays(3)->format('Y-m-d');

        try {
            $response = $this->http()->post('/orders', [
                'reference_id' => $options['reference'] ?? uniqid('boleto_'),
                'customer' => $this->buildCustomerData($customer),
                'items' => [
                    [
                        'reference_id' => 'item_1',
                        'name' => $options['description'] ?? 'Boleto Payment',
                        'quantity' => 1,
                        'unit_amount' => $amount,
                    ],
                ],
                'charges' => [
                    [
                        'reference_id' => 'charge_1',
                        'description' => $options['description'] ?? 'Boleto Payment',
                        'amount' => [
                            'value' => $amount,
                            'currency' => 'BRL',
                        ],
                        'payment_method' => [
                            'type' => 'BOLETO',
                            'boleto' => [
                                'due_date' => $dueDate,
                                'instruction_lines' => [
                                    'line_1' => 'Pagamento referente a '.($options['description'] ?? 'serviço'),
                                    'line_2' => 'Não receber após o vencimento',
                                ],
                                'holder' => [
                                    'name' => $customer->name ?? $customer->email,
                                    'tax_id' => preg_replace('/\D/', '', $customer->tax_id ?? ''),
                                    'email' => $customer->email,
                                ],
                            ],
                        ],
                    ],
                ],
                'notification_urls' => [
                    config('app.url').'/webhooks/pagseguro',
                ],
            ]);

            if ($response->failed()) {
                Log::error('PagSeguro Boleto charge failed', [
                    'customer_id' => $customer->id,
                    'response' => $response->json(),
                ]);

                return ChargeResult::failed(
                    $response->json('error_messages.0.description', 'Boleto charge failed')
                );
            }

            $data = $response->json();
            $charge = $data['charges'][0] ?? null;
            $boleto = $charge['payment_method']['boleto'] ?? null;

            return ChargeResult::success(
                providerPaymentId: $data['id'],
                status: $this->mapChargeStatus($charge['status'] ?? 'WAITING'),
                providerData: [
                    'amount' => $amount,
                    'currency' => 'BRL',
                    'billing_type' => 'BOLETO',
                    'charge_id' => $charge['id'] ?? null,
                    'boleto' => [
                        'barcode' => $boleto['barcode'] ?? null,
                        'formatted_barcode' => $boleto['formatted_barcode'] ?? null,
                        'digitable_line' => $boleto['formatted_barcode'] ?? null,
                        'url' => $this->getBoletoUrl($charge['links'] ?? []),
                        'due_date' => $boleto['due_date'] ?? $dueDate,
                    ],
                ]
            );
        } catch (\Exception $e) {
            Log::error('PagSeguro Boleto charge exception', [
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
            $chargeData = [
                'reference_id' => 'charge_1',
                'description' => $options['description'] ?? 'Card Payment',
                'amount' => [
                    'value' => $amount,
                    'currency' => 'BRL',
                ],
                'payment_method' => [
                    'type' => 'CREDIT_CARD',
                    'installments' => $installments,
                    'capture' => true,
                ],
            ];

            // Use encrypted card if provided
            if (isset($options['encrypted_card'])) {
                $chargeData['payment_method']['card'] = [
                    'encrypted' => $options['encrypted_card'],
                ];
            } elseif (isset($options['card_token'])) {
                $chargeData['payment_method']['card'] = [
                    'id' => $options['card_token'],
                ];
            }

            $response = $this->http()->post('/orders', [
                'reference_id' => $options['reference'] ?? uniqid('card_'),
                'customer' => $this->buildCustomerData($customer),
                'items' => [
                    [
                        'reference_id' => 'item_1',
                        'name' => $options['description'] ?? 'Card Payment',
                        'quantity' => 1,
                        'unit_amount' => $amount,
                    ],
                ],
                'charges' => [$chargeData],
                'notification_urls' => [
                    config('app.url').'/webhooks/pagseguro',
                ],
            ]);

            if ($response->failed()) {
                Log::error('PagSeguro Card charge failed', [
                    'customer_id' => $customer->id,
                    'response' => $response->json(),
                ]);

                return ChargeResult::failed(
                    $response->json('error_messages.0.description', 'Card charge failed')
                );
            }

            $data = $response->json();
            $charge = $data['charges'][0] ?? null;

            return ChargeResult::success(
                providerPaymentId: $data['id'],
                status: $this->mapChargeStatus($charge['status'] ?? 'WAITING'),
                providerData: [
                    'amount' => $amount,
                    'currency' => 'BRL',
                    'billing_type' => 'CREDIT_CARD',
                    'charge_id' => $charge['id'] ?? null,
                    'card' => [
                        'last_four' => $charge['payment_response']['raw_data']['card_last_digits'] ?? null,
                        'brand' => $charge['payment_method']['card']['brand'] ?? null,
                    ],
                    'installments' => $installments,
                ]
            );
        } catch (\Exception $e) {
            Log::error('PagSeguro Card charge exception', [
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
        $chargeId = $payment->provider_data['charge_id'] ?? null;

        if (! $chargeId) {
            return RefundResult::failed('No charge ID found for refund');
        }

        try {
            $payload = [];
            if ($amount) {
                $payload['amount'] = ['value' => $amount];
            }

            $response = $this->http()->post("/charges/{$chargeId}/cancel", $payload);

            if ($response->failed()) {
                return RefundResult::failed(
                    $response->json('error_messages.0.description', 'Refund failed')
                );
            }

            $data = $response->json();

            return RefundResult::success(
                providerRefundId: $data['id'] ?? $chargeId,
                amountRefunded: $amount ?? $payment->amount
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
        $orderId = $payment->provider_payment_id;

        if (! $orderId) {
            return 'failed';
        }

        try {
            $response = $this->http()->get("/orders/{$orderId}");

            if ($response->failed()) {
                return 'failed';
            }

            $data = $response->json();
            $charge = $data['charges'][0] ?? null;

            return $this->mapChargeStatus($charge['status'] ?? 'DECLINED');
        } catch (\Exception $e) {
            Log::error('PagSeguro checkStatus failed', [
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
        // PagSeguro uses notification_code - verify by fetching the notification
        return ! empty($signature);
    }

    /**
     * Handle incoming webhook.
     */
    public function handleWebhook(array $payload, array $headers = []): void
    {
        $notificationType = $payload['notificationType'] ?? null;
        $notificationCode = $payload['notificationCode'] ?? null;

        Log::info('PagSeguro webhook received', [
            'type' => $notificationType,
            'code' => $notificationCode,
        ]);

        if ($notificationType === 'transaction' && $notificationCode) {
            $this->processTransactionNotification($notificationCode);
        }
    }

    /**
     * Process transaction notification.
     */
    protected function processTransactionNotification(string $notificationCode): void
    {
        try {
            // Fetch notification details
            $response = $this->http()->get("/notifications/{$notificationCode}");

            if ($response->failed()) {
                Log::warning('Failed to fetch PagSeguro notification', [
                    'notification_code' => $notificationCode,
                ]);

                return;
            }

            $data = $response->json();
            $status = $data['status'] ?? null;
            $reference = $data['reference_id'] ?? null;

            if (! $reference) {
                return;
            }

            $purchase = $this->findPurchaseByReference($reference);

            if (! $purchase) {
                Log::info('PagSeguro notification but no matching purchase', [
                    'reference' => $reference,
                ]);

                return;
            }

            // Dispatch appropriate event based on status
            if (in_array($status, ['PAID', 'AVAILABLE'])) {
                \App\Events\Payment\PaymentConfirmed::dispatch(
                    $purchase,
                    'pagseguro',
                    $data['id'] ?? '',
                    ['raw_data' => $data]
                );
            } elseif (in_array($status, ['CANCELED', 'DECLINED'])) {
                \App\Events\Payment\PaymentFailed::dispatch(
                    $purchase,
                    'pagseguro',
                    $data['payment_response']['message'] ?? 'Payment failed',
                    ['raw_data' => $data]
                );
            }
        } catch (\Exception $e) {
            Log::error('Error processing PagSeguro notification', [
                'notification_code' => $notificationCode,
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
        // PagSeguro tokenization is done client-side with PagSeguro.js
        $intentId = 'pagseguro_setup_'.uniqid();

        return SetupIntentResult::success(
            clientSecret: $intentId,
            providerIntentId: $intentId,
            providerData: [
                'message' => 'Use PagSeguro.js to encrypt card data',
                'public_key' => config('payment.drivers.pagseguro.public_key'),
            ]
        );
    }

    public function attachPaymentMethod(Customer $customer, string $providerMethodId, array $options = []): PaymentMethodResult
    {
        // PagSeguro stores encrypted card data, not tokens
        return new PaymentMethodResult(
            success: true,
            providerMethodId: $providerMethodId,
            type: 'card',
            isDefault: $options['set_as_default'] ?? true
        );
    }

    public function detachPaymentMethod(PaymentMethod $paymentMethod): bool
    {
        // PagSeguro doesn't support detaching cards
        return true;
    }

    public function listPaymentMethods(Customer $customer): array
    {
        // PagSeguro doesn't store payment methods externally
        return [];
    }

    public function setDefaultPaymentMethod(Customer $customer, PaymentMethod $paymentMethod): bool
    {
        return true;
    }

    public function syncPaymentMethods(Customer $customer): void
    {
        // Not applicable for PagSeguro
    }

    // =========================================================================
    // SubscriptionGatewayInterface Implementation
    // =========================================================================

    public function createSubscription(Customer $customer, string $priceId, array $options = []): SubscriptionResult
    {
        // PagSeguro has a "Pagamento Recorrente" API
        try {
            $response = $this->http()->post('/pre-approvals', [
                'reference' => $priceId,
                'pre_approval' => [
                    'name' => $options['description'] ?? 'Subscription',
                    'charge' => 'AUTO',
                    'period' => $this->mapPeriod($options['interval'] ?? 'month'),
                    'amount_per_payment' => ($options['amount'] ?? 0) / 100,
                    'membership_fee' => 0,
                    'trial_period_duration' => $options['trial_days'] ?? 0,
                ],
                'receiver_email' => config('payment.drivers.pagseguro.receiver_email'),
                'redirect_url' => $options['success_url'] ?? config('app.url'),
                'notification_url' => config('app.url').'/webhooks/pagseguro',
            ]);

            if ($response->failed()) {
                return new SubscriptionResult(
                    success: false,
                    providerSubscriptionId: null,
                    status: 'failed',
                    failureMessage: $response->json('error_messages.0.description', 'Subscription creation failed')
                );
            }

            $data = $response->json();

            return new SubscriptionResult(
                success: true,
                providerSubscriptionId: $data['code'] ?? $data['id'] ?? null,
                status: 'pending', // User needs to complete subscription via redirect
                currentPeriodStart: now(),
                currentPeriodEnd: now()->addMonth(),
                providerData: [
                    'checkout_url' => $data['redirect_url'] ?? null,
                    'code' => $data['code'] ?? null,
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
        // PagSeguro doesn't support subscription updates easily
        return SubscriptionResult::failed('PagSeguro subscription updates require cancellation and recreation');
    }

    public function pauseSubscription(Subscription $subscription): SubscriptionResult
    {
        return SubscriptionResult::failed('PagSeguro does not support pausing subscriptions');
    }

    public function syncSubscription(Subscription $subscription): SubscriptionResult
    {
        $subscriptionCode = $subscription->provider_subscription_id;

        try {
            $response = $this->http()->get("/pre-approvals/{$subscriptionCode}");

            if ($response->failed()) {
                return new SubscriptionResult(
                    success: false,
                    providerSubscriptionId: $subscriptionCode,
                    status: 'failed',
                    failureMessage: 'Failed to sync subscription'
                );
            }

            $data = $response->json();

            return new SubscriptionResult(
                success: true,
                providerSubscriptionId: $subscriptionCode,
                status: $this->mapPreApprovalStatus($data['status'] ?? 'PENDING')
            );
        } catch (\Exception $e) {
            return new SubscriptionResult(
                success: false,
                providerSubscriptionId: $subscriptionCode,
                status: 'failed',
                failureMessage: $e->getMessage()
            );
        }
    }

    public function createCheckoutSession(Customer $customer, string $priceId, array $options = []): string
    {
        // For PagSeguro, return the redirect URL from createSubscription
        return $options['success_url'] ?? '/checkout';
    }

    public function createBillingPortalSession(Customer $customer, string $returnUrl): string
    {
        // PagSeguro doesn't have a customer billing portal
        return $returnUrl;
    }

    public function cancelSubscription(Subscription $subscription, bool $immediately = false): SubscriptionResult
    {
        $subscriptionCode = $subscription->provider_subscription_id;

        try {
            $response = $this->http()->put("/pre-approvals/{$subscriptionCode}/cancel");

            if ($response->failed()) {
                return new SubscriptionResult(
                    success: false,
                    providerSubscriptionId: $subscriptionCode,
                    status: 'failed',
                    failureMessage: $response->json('error_messages.0.description', 'Cancellation failed')
                );
            }

            return new SubscriptionResult(
                success: true,
                providerSubscriptionId: $subscriptionCode,
                status: 'canceled'
            );
        } catch (\Exception $e) {
            return new SubscriptionResult(
                success: false,
                providerSubscriptionId: $subscriptionCode,
                status: 'failed',
                failureMessage: $e->getMessage()
            );
        }
    }

    public function resumeSubscription(Subscription $subscription): SubscriptionResult
    {
        return SubscriptionResult::failed('PagSeguro does not support resuming subscriptions');
    }

    public function retrieveSubscription(string $subscriptionId): array
    {
        try {
            $response = $this->http()->get("/pre-approvals/{$subscriptionId}");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error('Failed to retrieve PagSeguro subscription', ['error' => $e->getMessage()]);
        }

        return [];
    }

    public function addSubscriptionItem(Subscription $subscription, string $priceId, int $quantity = 1): array
    {
        throw new \RuntimeException('PagSeguro does not support subscription items');
    }

    public function updateSubscriptionItem(Subscription $subscription, string $priceId, int $quantity): void
    {
        throw new \RuntimeException('PagSeguro does not support subscription items');
    }

    public function removeSubscriptionItem(Subscription $subscription, string $priceId): void
    {
        throw new \RuntimeException('PagSeguro does not support subscription items');
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Build customer data for PagSeguro API.
     */
    protected function buildCustomerData(Customer $customer): array
    {
        return [
            'name' => $customer->name ?? $customer->email,
            'email' => $customer->email,
            'tax_id' => preg_replace('/\D/', '', $customer->tax_id ?? ''),
            'phones' => $customer->phone ? [
                [
                    'country' => '55',
                    'area' => substr(preg_replace('/\D/', '', $customer->phone), 0, 2),
                    'number' => substr(preg_replace('/\D/', '', $customer->phone), 2),
                    'type' => 'MOBILE',
                ],
            ] : [],
        ];
    }

    /**
     * Get Boleto PDF URL from links.
     */
    protected function getBoletoUrl(array $links): ?string
    {
        foreach ($links as $link) {
            if (($link['media'] ?? '') === 'application/pdf') {
                return $link['href'];
            }
        }

        return null;
    }

    /**
     * Map PagSeguro order status to internal status.
     */
    protected function mapOrderStatus(string $status): string
    {
        return match ($status) {
            'PAID' => 'paid',
            'AUTHORIZED', 'IN_ANALYSIS' => 'processing',
            'PENDING', 'WAITING' => 'pending',
            'CANCELED', 'DECLINED' => 'failed',
            default => 'pending',
        };
    }

    /**
     * Map PagSeguro charge status to internal status.
     */
    protected function mapChargeStatus(string $status): string
    {
        return match ($status) {
            'PAID' => 'paid',
            'AUTHORIZED' => 'processing',
            'IN_ANALYSIS' => 'processing',
            'WAITING' => 'pending',
            'DECLINED' => 'failed',
            'CANCELED' => 'canceled',
            default => 'pending',
        };
    }

    /**
     * Map subscription period.
     */
    protected function mapPeriod(string $interval): string
    {
        return match ($interval) {
            'day', 'daily' => 'DAILY',
            'week', 'weekly' => 'WEEKLY',
            'month', 'monthly' => 'MONTHLY',
            'year', 'yearly' => 'YEARLY',
            default => 'MONTHLY',
        };
    }

    /**
     * Map pre-approval (subscription) status.
     */
    protected function mapPreApprovalStatus(string $status): string
    {
        return match ($status) {
            'ACTIVE' => 'active',
            'PENDING' => 'pending',
            'CANCELLED', 'CANCELLED_BY_RECEIVER', 'CANCELLED_BY_SENDER' => 'canceled',
            'EXPIRED' => 'expired',
            'SUSPENDED' => 'paused',
            default => 'incomplete',
        };
    }
}
