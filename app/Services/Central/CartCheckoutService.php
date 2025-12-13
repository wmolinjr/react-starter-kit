<?php

namespace App\Services\Central;

use App\Contracts\Payment\CheckoutGatewayInterface;
use App\Contracts\Payment\PaymentGatewayInterface;
use App\Enums\BillingPeriod;
use App\Enums\PaymentGateway;
use App\Exceptions\Central\AddonException;
use App\Models\Central\Addon;
use App\Models\Central\AddonBundle;
use App\Models\Central\AddonPurchase;
use App\Models\Central\PaymentSetting;
use App\Models\Central\Tenant;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Support\Facades\Log;

class CartCheckoutService
{
    protected ?PaymentGatewayInterface $gateway = null;

    public function __construct(
        protected AddonService $addonService,
        protected PaymentGatewayManager $gatewayManager,
        protected PaymentSettingsService $paymentSettingsService
    ) {
        $this->gateway = $this->gatewayManager->driver();
    }

    /**
     * Check if payment gateway is configured.
     */
    public function isGatewayConfigured(): bool
    {
        return $this->gateway !== null && $this->gateway->isAvailable();
    }

    /**
     * Check if gateway supports checkout operations.
     */
    protected function supportsCheckout(): bool
    {
        return $this->gateway instanceof CheckoutGatewayInterface;
    }

    /**
     * Get the current provider identifier.
     */
    public function getProvider(): string
    {
        return $this->gateway?->getIdentifier() ?? 'unknown';
    }

    /**
     * Resolve the enabled gateway for a payment method.
     *
     * Uses PaymentSettingsService to determine which gateway should handle
     * the payment method based on admin configuration.
     *
     * @throws AddonException if no gateway is enabled for the payment method
     */
    protected function resolveGatewayForPaymentMethod(string $paymentMethod): PaymentSetting
    {
        $setting = $this->paymentSettingsService->getEnabledGatewayForMethod($paymentMethod);

        if (! $setting) {
            throw new AddonException(
                __('billing.errors.payment_method_unavailable', ['method' => $paymentMethod])
            );
        }

        return $setting;
    }

    /**
     * Process cart checkout with multi-payment method support.
     *
     * Routes payments to the configured gateway based on PaymentSettings.
     *
     * @param  array<int, array{type: string, slug: string, quantity: int, billing_period: string}>  $items
     * @param  string  $paymentMethod  Payment method type: 'card', 'pix', 'boleto'
     * @return array Checkout result with redirect URL or async payment data
     */
    public function processCartCheckout(
        Tenant $tenant,
        array $items,
        string $paymentMethod = 'card'
    ): array {
        // Resolve gateway from PaymentSettings - validates method is available
        $paymentSetting = $this->resolveGatewayForPaymentMethod($paymentMethod);
        // PaymentSetting model already casts gateway to PaymentGateway enum
        $gateway = $paymentSetting->gateway;

        // Validate items and calculate total
        $lineItems = [];
        $totalAmount = 0;
        $hasRecurring = false;

        foreach ($items as $item) {
            $result = $this->buildLineItem($tenant, $item);
            $lineItems[] = $result;
            $totalAmount += $result['amount'] * $result['quantity'];
            if ($result['is_recurring']) {
                $hasRecurring = true;
            }
        }

        // PIX/Boleto don't support recurring subscriptions
        if ($hasRecurring && in_array($paymentMethod, ['pix', 'boleto'])) {
            throw new AddonException(
                __('billing.errors.async_no_recurring',
                    ['method' => strtoupper($paymentMethod)]
                )
            );
        }

        // Route to appropriate gateway based on payment method and configuration
        return match ($paymentMethod) {
            'card' => $this->processCardCheckout($tenant, $items, $lineItems, $hasRecurring, $gateway),
            'pix' => $this->processPixCheckout($tenant, $items, $lineItems, $totalAmount, $gateway),
            'boleto' => $this->processBoletoCheckout($tenant, $items, $lineItems, $totalAmount, $gateway),
            default => throw new AddonException('Invalid payment method: '.$paymentMethod),
        };
    }

    /**
     * Process card checkout via configured gateway.
     *
     * Routes to appropriate gateway:
     * - Stripe: Uses hosted Checkout sessions (redirect)
     * - Asaas: Returns purchase ID for card form submission
     *
     * @return array{type: string, ...}
     */
    protected function processCardCheckout(
        Tenant $tenant,
        array $items,
        array $lineItems,
        bool $hasRecurring,
        PaymentGateway $gateway
    ): array {
        return match ($gateway) {
            PaymentGateway::STRIPE => $this->processStripeCardCheckout($tenant, $items, $lineItems, $hasRecurring),
            PaymentGateway::ASAAS => $this->processAsaasCardCheckout($tenant, $items, $lineItems, $hasRecurring),
            default => throw new AddonException(
                __('billing.errors.gateway_not_supported_for_method', [
                    'gateway' => $gateway->displayName(),
                    'method' => 'card',
                ])
            ),
        };
    }

    /**
     * Process card checkout via Stripe Checkout Sessions.
     *
     * @return array{type: string, session_id: string, url: string}
     */
    protected function processStripeCardCheckout(
        Tenant $tenant,
        array $items,
        array $lineItems,
        bool $hasRecurring
    ): array {
        // Get Stripe gateway specifically for Stripe card checkout
        $stripeGateway = $this->gatewayManager->stripe();
        if (! $stripeGateway || ! $stripeGateway->isAvailable()) {
            throw new AddonException('Stripe is not configured');
        }

        // Ensure tenant has a customer for billing
        $customer = $tenant->customer;
        if (! $customer) {
            throw new AddonException('Tenant has no associated customer for billing');
        }

        // Gateway handles customer creation
        $stripeGateway->ensureCustomer($customer);

        $stripeLineItems = array_map(fn ($item) => $item['stripe_item'], $lineItems);
        $mode = $hasRecurring ? 'subscription' : 'payment';

        $session = $stripeGateway->createCheckoutWithItems($customer, $stripeLineItems, [
            'mode' => $mode,
            'locale' => stripe_locale(),
            'success_url' => $tenant->url().route('tenant.admin.billing.cart-success', [], false).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $tenant->url().route('tenant.admin.addons.index', [], false),
            'metadata' => [
                'tenant_id' => $tenant->id,
                'cart_type' => 'cart_checkout',
                'cart_items' => json_encode($items),
            ],
        ]);

        Log::info('Cart checkout session created', [
            'tenant_id' => $tenant->id,
            'session_id' => $session['id'],
            'items_count' => count($items),
            'mode' => $mode,
        ]);

        return [
            'type' => 'redirect',
            'session_id' => $session['id'],
            'url' => $session['url'],
        ];
    }

    /**
     * Process card checkout via Asaas.
     *
     * Asaas requires card data to be submitted directly (not hosted checkout).
     * This method creates a pending purchase and returns it for the frontend
     * to collect card data and complete the payment.
     *
     * Supports both one-time charges and recurring subscriptions with credit card.
     *
     * @return array{type: string, purchase_id: string, amount: int, gateway: string, requires_card_data: bool}
     */
    protected function processAsaasCardCheckout(
        Tenant $tenant,
        array $items,
        array $lineItems,
        bool $hasRecurring
    ): array {
        $customer = $tenant->customer;
        if (! $customer) {
            throw new AddonException('Tenant has no associated customer for billing');
        }

        // Calculate total amount
        $totalAmount = array_reduce($lineItems, function ($carry, $item) {
            return $carry + ($item['amount'] * $item['quantity']);
        }, 0);

        // Separate recurring and one-time items for processing
        $recurringItems = array_filter($lineItems, fn ($item) => $item['recurring'] ?? false);
        $oneTimeItems = array_filter($lineItems, fn ($item) => ! ($item['recurring'] ?? false));

        // Create pending purchase with recurring info in metadata
        $purchase = $this->createPendingPurchase(
            $tenant,
            $items,
            $totalAmount,
            'card',
            PaymentGateway::ASAAS,
            [
                'has_recurring' => $hasRecurring,
                'line_items' => $lineItems,
                'recurring_items' => array_values($recurringItems),
                'one_time_items' => array_values($oneTimeItems),
            ]
        );

        Log::info('Asaas card checkout initiated', [
            'tenant_id' => $tenant->id,
            'purchase_id' => $purchase->id,
            'amount' => $totalAmount,
            'items_count' => count($items),
            'has_recurring' => $hasRecurring,
            'recurring_count' => count($recurringItems),
            'one_time_count' => count($oneTimeItems),
        ]);

        return [
            'type' => 'asaas_card',
            'purchase_id' => $purchase->id,
            'amount' => $totalAmount,
            'gateway' => 'asaas',
            'requires_card_data' => true,
            'has_recurring' => $hasRecurring,
            'required_fields' => [
                'card' => ['holder_name', 'number', 'exp_month', 'exp_year', 'cvv'],
                'holder' => ['name', 'email', 'cpf_cnpj', 'postal_code', 'address_number'],
            ],
        ];
    }

    /**
     * Complete Asaas card payment with card data.
     *
     * Called after the frontend collects card data from the user.
     * Supports both one-time charges and recurring subscriptions.
     *
     * @param  string  $purchaseId  The pending purchase ID
     * @param  array  $cardData  Card details: holder_name, number, exp_month, exp_year, cvv
     * @param  array  $holderInfo  Holder details: name, email, cpf_cnpj, postal_code, address_number
     * @return array{success: bool, message?: string, card?: array}
     */
    public function completeAsaasCardPayment(
        string $purchaseId,
        array $cardData,
        array $holderInfo,
        array $options = []
    ): array {
        $purchase = AddonPurchase::findOrFail($purchaseId);

        // Validate purchase is pending and for card payment
        if ($purchase->status !== 'pending') {
            throw new AddonException(__('billing.errors.purchase_not_pending'));
        }

        if ($purchase->payment_method !== 'card') {
            throw new AddonException(__('billing.errors.invalid_payment_method'));
        }

        $tenant = $purchase->tenant;
        $customer = $tenant->customer;

        if (! $customer) {
            throw new AddonException('Tenant has no associated customer for billing');
        }

        // Get Asaas gateway instance
        $gateway = $this->gatewayManager->gateway('asaas');

        // Check if we have recurring items
        $hasRecurring = $purchase->metadata['has_recurring'] ?? false;
        $recurringItems = $purchase->metadata['recurring_items'] ?? [];
        $oneTimeItems = $purchase->metadata['one_time_items'] ?? [];

        try {
            $providerPaymentIds = [];
            $subscriptionIds = [];
            $cardInfo = null;

            // Process one-time items with direct charge
            if (! empty($oneTimeItems)) {
                $oneTimeAmount = array_reduce($oneTimeItems, function ($carry, $item) {
                    return $carry + ($item['amount'] * $item['quantity']);
                }, 0);

                $chargeResult = $gateway->createCardChargeWithData(
                    $customer,
                    $oneTimeAmount,
                    $cardData,
                    $holderInfo,
                    [
                        'description' => $this->buildCartDescription($purchase->metadata['cart_items'] ?? []),
                        'reference' => 'addon_purchase_'.$purchase->id,
                        'installments' => $options['installments'] ?? 1,
                        'remote_ip' => $options['remote_ip'] ?? request()->ip(),
                    ]
                );

                if (! $chargeResult->success) {
                    $purchase->markAsFailed($chargeResult->failureMessage ?? 'Card charge failed');

                    return [
                        'success' => false,
                        'message' => $chargeResult->failureMessage ?? __('billing.errors.card_charge_failed'),
                    ];
                }

                $providerPaymentIds[] = $chargeResult->providerPaymentId;
                $cardInfo = [
                    'last_four' => $chargeResult->providerData['card']['last_four'] ?? null,
                    'brand' => $chargeResult->providerData['card']['brand'] ?? null,
                    'token' => $chargeResult->providerData['card']['token'] ?? null,
                ];
            }

            // Process recurring items with subscriptions
            if (! empty($recurringItems)) {
                foreach ($recurringItems as $item) {
                    $subscriptionResult = $gateway->createSubscription(
                        $customer,
                        $item['slug'] ?? 'addon_subscription',
                        [
                            'billing_type' => 'CREDIT_CARD',
                            'amount' => $item['amount'] * $item['quantity'],
                            'cycle' => $this->mapBillingPeriodToCycle($item['billing_period'] ?? 'monthly'),
                            'description' => $item['name'] ?? 'Addon Subscription',
                            'start_date' => now()->format('Y-m-d'),
                            'credit_card' => $cardData,
                            'credit_card_holder_info' => $holderInfo,
                        ]
                    );

                    if (! $subscriptionResult->success) {
                        Log::error('Asaas subscription creation failed', [
                            'purchase_id' => $purchaseId,
                            'item' => $item,
                            'error' => $subscriptionResult->failureMessage,
                        ]);
                        // Continue with other items, don't fail the entire purchase
                        continue;
                    }

                    $subscriptionIds[] = $subscriptionResult->providerSubscriptionId;
                }
            }

            // Update purchase with payment info
            $purchase->update([
                'provider_payment_id' => implode(',', $providerPaymentIds) ?: null,
                'status' => 'paid',
                'paid_at' => now(),
                'metadata' => array_merge($purchase->metadata ?? [], [
                    'card_last_four' => $cardInfo['last_four'] ?? null,
                    'card_brand' => $cardInfo['brand'] ?? null,
                    'card_token' => $cardInfo['token'] ?? null,
                    'subscription_ids' => $subscriptionIds,
                ]),
            ]);

            Log::info('Asaas card payment completed', [
                'tenant_id' => $tenant->id,
                'purchase_id' => $purchase->id,
                'payment_ids' => $providerPaymentIds,
                'subscription_ids' => $subscriptionIds,
                'has_recurring' => $hasRecurring,
            ]);

            return [
                'success' => true,
                'message' => __('billing.success.payment_completed'),
                'purchase_id' => $purchase->id,
                'card' => [
                    'last_four' => $cardInfo['last_four'] ?? null,
                    'brand' => $cardInfo['brand'] ?? null,
                ],
                'subscriptions' => $subscriptionIds,
            ];
        } catch (\Exception $e) {
            Log::error('Asaas card payment failed', [
                'purchase_id' => $purchaseId,
                'error' => $e->getMessage(),
            ]);

            $purchase->markAsFailed($e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Map billing period to Asaas subscription cycle.
     */
    protected function mapBillingPeriodToCycle(string $billingPeriod): string
    {
        return match ($billingPeriod) {
            'weekly' => 'WEEKLY',
            'biweekly' => 'BIWEEKLY',
            'monthly' => 'MONTHLY',
            'quarterly' => 'QUARTERLY',
            'semiannually' => 'SEMIANNUALLY',
            'yearly', 'annual' => 'YEARLY',
            default => 'MONTHLY',
        };
    }

    /**
     * Process PIX checkout via configured gateway.
     *
     * Supports: Asaas, PagSeguro, MercadoPago
     *
     * @return array{type: string, payment_id: string, purchase_id: string, pix: array}
     */
    protected function processPixCheckout(
        Tenant $tenant,
        array $items,
        array $lineItems,
        int $totalAmount,
        PaymentGateway $gateway
    ): array {
        $customer = $tenant->customer;
        if (! $customer) {
            throw new AddonException('Tenant has no associated customer for billing');
        }

        // Create pending purchase record for tracking
        $purchase = $this->createPendingPurchase($tenant, $items, $totalAmount, 'pix', $gateway);

        // Get the configured gateway instance
        $gatewayInstance = $this->gatewayManager->gateway($gateway->value);

        $result = $gatewayInstance->createPixCharge($customer, $totalAmount, [
            'description' => $this->buildCartDescription($items),
            'reference' => 'addon_purchase_'.$purchase->id,
        ]);

        if (! $result->success) {
            $purchase->markAsFailed($result->failureMessage ?? 'PIX charge failed');
            throw new AddonException($result->failureMessage ?? 'Failed to create PIX charge');
        }

        // Update purchase with provider payment ID
        $purchase->update([
            'provider_payment_id' => $result->providerPaymentId,
        ]);

        Log::info('PIX cart checkout created', [
            'tenant_id' => $tenant->id,
            'purchase_id' => $purchase->id,
            'payment_id' => $result->providerPaymentId,
            'gateway' => $gateway->value,
            'amount' => $totalAmount,
        ]);

        return [
            'type' => 'pix',
            'payment_id' => $result->providerPaymentId,
            'purchase_id' => $purchase->id,
            'amount' => $totalAmount,
            'gateway' => $gateway->value,
            'pix' => $result->providerData['pix'] ?? [],
        ];
    }

    /**
     * Process Boleto checkout via configured gateway.
     *
     * Supports: Asaas, PagSeguro, MercadoPago
     *
     * @return array{type: string, payment_id: string, purchase_id: string, boleto: array}
     */
    protected function processBoletoCheckout(
        Tenant $tenant,
        array $items,
        array $lineItems,
        int $totalAmount,
        PaymentGateway $gateway
    ): array {
        $customer = $tenant->customer;
        if (! $customer) {
            throw new AddonException('Tenant has no associated customer for billing');
        }

        // Create pending purchase record for tracking
        $purchase = $this->createPendingPurchase($tenant, $items, $totalAmount, 'boleto', $gateway);

        // Get the configured gateway instance
        $gatewayInstance = $this->gatewayManager->gateway($gateway->value);

        $result = $gatewayInstance->createBoletoCharge($customer, $totalAmount, [
            'description' => $this->buildCartDescription($items),
            'reference' => 'addon_purchase_'.$purchase->id,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        if (! $result->success) {
            $purchase->markAsFailed($result->failureMessage ?? 'Boleto charge failed');
            throw new AddonException($result->failureMessage ?? 'Failed to create Boleto charge');
        }

        // Update purchase with provider payment ID
        $purchase->update([
            'provider_payment_id' => $result->providerPaymentId,
        ]);

        Log::info('Boleto cart checkout created', [
            'tenant_id' => $tenant->id,
            'purchase_id' => $purchase->id,
            'payment_id' => $result->providerPaymentId,
            'gateway' => $gateway->value,
            'amount' => $totalAmount,
        ]);

        return [
            'type' => 'boleto',
            'payment_id' => $result->providerPaymentId,
            'purchase_id' => $purchase->id,
            'amount' => $totalAmount,
            'gateway' => $gateway->value,
            'due_date' => $result->providerData['due_date'] ?? null,
            'boleto' => $result->providerData['boleto'] ?? [],
        ];
    }

    /**
     * Create a pending purchase record for async payments.
     */
    protected function createPendingPurchase(
        Tenant $tenant,
        array $items,
        int $totalAmount,
        string $paymentMethod,
        PaymentGateway $gateway,
        array $additionalMetadata = []
    ): AddonPurchase {
        // For cart purchases, we create a single purchase with cart metadata
        $firstItem = $items[0] ?? null;

        return AddonPurchase::create([
            'tenant_id' => $tenant->id,
            'addon_slug' => $firstItem['slug'] ?? 'cart_purchase',
            'addon_type' => 'credit', // Cart purchases treated as credits
            'quantity' => 1,
            'amount_paid' => $totalAmount,
            'payment_method' => $paymentMethod,
            'status' => 'pending',
            'valid_from' => now(),
            'valid_until' => now()->addMonths(12),
            'metadata' => array_merge([
                'cart_items' => $items,
                'payment_provider' => $gateway->value,
            ], $additionalMetadata),
        ]);
    }

    /**
     * Build description for cart checkout.
     */
    protected function buildCartDescription(array $items): string
    {
        $names = array_map(function ($item) {
            $name = $item['type'] === 'bundle'
                ? AddonBundle::where('slug', $item['slug'])->value('name')
                : Addon::where('slug', $item['slug'])->value('name');

            return $name ?? $item['slug'];
        }, $items);

        return implode(', ', array_slice($names, 0, 3)).(count($names) > 3 ? '...' : '');
    }


    /**
     * Check payment status for async payments (PIX/Boleto).
     *
     * Looks up the gateway from the purchase record and queries the appropriate provider.
     */
    public function checkPaymentStatus(string $paymentId): array
    {
        // Look up the purchase to find which gateway was used
        $purchase = AddonPurchase::where('provider_payment_id', $paymentId)->first();
        $gatewayName = $purchase?->metadata['payment_provider'] ?? 'asaas';

        $gateway = $this->gatewayManager->gateway($gatewayName);

        try {
            $paymentData = $gateway->retrievePayment($paymentId);
            $rawStatus = $paymentData['status'] ?? 'FAILED';

            return [
                'status' => $this->mapPaymentStatus($rawStatus, $gatewayName),
                'raw_status' => $rawStatus,
                'gateway' => $gatewayName,
                'payment_date' => $paymentData['paymentDate'] ?? $paymentData['date_approved'] ?? null,
                'confirmed_date' => $paymentData['confirmedDate'] ?? $paymentData['date_approved'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to check payment status', [
                'payment_id' => $paymentId,
                'gateway' => $gatewayName,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'gateway' => $gatewayName,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Map payment status to internal status based on gateway.
     */
    protected function mapPaymentStatus(string $status, string $gateway): string
    {
        return match ($gateway) {
            'asaas' => match ($status) {
                'CONFIRMED', 'RECEIVED' => 'paid',
                'PENDING', 'AWAITING_RISK_ANALYSIS' => 'pending',
                'OVERDUE' => 'past_due',
                'REFUNDED', 'REFUND_REQUESTED', 'REFUND_IN_PROGRESS' => 'refunded',
                'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE', 'AWAITING_CHARGEBACK_REVERSAL' => 'disputed',
                default => 'failed',
            },
            'mercadopago' => match ($status) {
                'approved' => 'paid',
                'pending', 'in_process', 'authorized' => 'pending',
                'rejected', 'cancelled' => 'failed',
                'refunded' => 'refunded',
                'charged_back' => 'disputed',
                default => 'failed',
            },
            'pagseguro' => match ($status) {
                'PAID', 'AVAILABLE' => 'paid',
                'WAITING', 'IN_ANALYSIS', 'AUTHORIZED' => 'pending',
                'DECLINED', 'CANCELED' => 'failed',
                'REFUNDED' => 'refunded',
                'CONTESTED' => 'disputed',
                default => 'failed',
            },
            default => 'failed',
        };
    }

    /**
     * Refresh PIX QR code for an existing payment.
     *
     * Looks up the gateway from the purchase record and queries the appropriate provider.
     */
    public function refreshPixQrCode(string $paymentId): ?array
    {
        // Look up the purchase to find which gateway was used
        $purchase = AddonPurchase::where('provider_payment_id', $paymentId)->first();
        $gatewayName = $purchase?->metadata['payment_provider'] ?? 'asaas';

        $gateway = $this->gatewayManager->gateway($gatewayName);

        return $gateway->getPixQrCode($paymentId);
    }

    /**
     * Create a Stripe Checkout session for cart items
     *
     * @param  array<int, array{type: string, slug: string, quantity: int, billing_period: string}>  $items
     * @return array{session_id: string, url: string}
     */
    public function createCartCheckoutSession(Tenant $tenant, array $items): array
    {
        // Get Stripe gateway specifically for Stripe checkout sessions
        $stripeGateway = $this->gatewayManager->stripe();
        if (! $stripeGateway || ! $stripeGateway->isAvailable()) {
            throw new AddonException('Stripe is not configured');
        }

        // Ensure tenant has a customer for billing
        $customer = $tenant->customer;
        if (! $customer) {
            throw new AddonException('Tenant has no associated customer for billing');
        }

        // Gateway handles customer creation
        $stripeGateway->ensureCustomer($customer);

        $lineItems = [];
        $hasRecurring = false;

        foreach ($items as $item) {
            $result = $this->buildLineItem($tenant, $item);
            $lineItems[] = $result['stripe_item'];
            if ($result['is_recurring']) {
                $hasRecurring = true;
            }
        }

        $mode = $hasRecurring ? 'subscription' : 'payment';

        $session = $stripeGateway->createCheckoutWithItems($customer, $lineItems, [
            'mode' => $mode,
            'locale' => stripe_locale(),
            'success_url' => $tenant->url().route('tenant.admin.billing.cart-success', [], false).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $tenant->url().route('tenant.admin.addons.index', [], false),
            'metadata' => [
                'tenant_id' => $tenant->id,
                'cart_type' => 'cart_checkout',
                'cart_items' => json_encode($items),
            ],
        ]);

        Log::info('Cart checkout session created', [
            'tenant_id' => $tenant->id,
            'session_id' => $session['id'],
            'items_count' => count($items),
            'mode' => $mode,
        ]);

        return [
            'session_id' => $session['id'],
            'url' => $session['url'],
        ];
    }

    /**
     * Build a Stripe line item for a cart item
     *
     * @param  array{type: string, slug: string, quantity: int, billing_period: string}  $item
     * @return array{stripe_item: array<string, mixed>, is_recurring: bool}
     */
    protected function buildLineItem(Tenant $tenant, array $item): array
    {
        return $item['type'] === 'bundle'
            ? $this->buildBundleLineItem($tenant, $item)
            : $this->buildAddonLineItem($tenant, $item);
    }

    /**
     * Build Stripe line item for an addon
     *
     * @param  array{type: string, slug: string, quantity: int, billing_period: string}  $item
     * @return array{stripe_item: array<string, mixed>, is_recurring: bool, amount: int, quantity: int}
     */
    protected function buildAddonLineItem(Tenant $tenant, array $item): array
    {
        $addon = Addon::where('slug', $item['slug'])->firstOrFail();
        $billingPeriod = BillingPeriod::from($item['billing_period']);

        // Validate the purchase is allowed
        $this->validateAddonPurchase($tenant, $addon, $item['quantity'], $billingPeriod);

        $isRecurring = $billingPeriod !== BillingPeriod::ONE_TIME;
        $provider = $this->gatewayManager->getDefaultDriver();
        $providerPriceId = $addon->getProviderPriceId($provider, $billingPeriod->value);
        $price = $this->getAddonPrice($addon, $billingPeriod);

        // Use existing provider price if available
        if ($providerPriceId) {
            return [
                'stripe_item' => [
                    'price' => $providerPriceId,
                    'quantity' => $item['quantity'],
                ],
                'is_recurring' => $isRecurring,
                'amount' => $price,
                'quantity' => $item['quantity'],
            ];
        }

        $priceData = [
            'currency' => config('payment.currency', 'usd'),
            'product_data' => [
                'name' => $addon->name,
                'description' => $addon->description,
            ],
            'unit_amount' => $price,
        ];

        if ($isRecurring) {
            $priceData['recurring'] = [
                'interval' => $billingPeriod === BillingPeriod::YEARLY ? 'year' : 'month',
            ];
        }

        return [
            'stripe_item' => [
                'price_data' => $priceData,
                'quantity' => $item['quantity'],
            ],
            'is_recurring' => $isRecurring,
            'amount' => $price,
            'quantity' => $item['quantity'],
        ];
    }

    /**
     * Build Stripe line item for a bundle
     *
     * @param  array{type: string, slug: string, quantity: int, billing_period: string}  $item
     * @return array{stripe_item: array<string, mixed>, is_recurring: bool, amount: int, quantity: int}
     */
    protected function buildBundleLineItem(Tenant $tenant, array $item): array
    {
        $bundle = AddonBundle::with('addons')->where('slug', $item['slug'])->firstOrFail();
        $billingPeriod = BillingPeriod::from($item['billing_period']);

        // Validate the bundle purchase is allowed
        $this->validateBundlePurchase($tenant, $bundle, $billingPeriod);

        $isRecurring = $billingPeriod !== BillingPeriod::ONE_TIME;
        $price = $billingPeriod === BillingPeriod::YEARLY
            ? $bundle->getEffectivePriceYearly()
            : $bundle->getEffectivePriceMonthly();

        $priceData = [
            'currency' => config('payment.currency', 'usd'),
            'product_data' => [
                'name' => $bundle->name,
                'description' => $bundle->description ?? "Bundle: {$bundle->name}",
            ],
            'unit_amount' => $price,
        ];

        if ($isRecurring) {
            $priceData['recurring'] = [
                'interval' => $billingPeriod === BillingPeriod::YEARLY ? 'year' : 'month',
            ];
        }

        return [
            'stripe_item' => [
                'price_data' => $priceData,
                'quantity' => 1, // Bundles are always quantity 1
            ],
            'is_recurring' => $isRecurring,
            'amount' => $price,
            'quantity' => 1, // Bundles are always quantity 1
        ];
    }

    /**
     * Get addon price for billing period
     */
    protected function getAddonPrice(Addon $addon, BillingPeriod $billingPeriod): int
    {
        return match ($billingPeriod) {
            BillingPeriod::MONTHLY => $addon->price_monthly ?? 0,
            BillingPeriod::YEARLY => $addon->price_yearly ?? 0,
            BillingPeriod::ONE_TIME => $addon->price_one_time ?? 0,
            default => $addon->price_monthly ?? 0,
        };
    }

    /**
     * Validate addon purchase
     */
    protected function validateAddonPurchase(
        Tenant $tenant,
        Addon $addon,
        int $quantity,
        BillingPeriod $billingPeriod
    ): void {
        // Check if available for plan
        if ($tenant->plan && ! $addon->isAvailableForPlan($tenant->plan)) {
            throw new AddonException('This addon is not available for your plan');
        }

        // Check if billing period is supported
        if (! $addon->supportsBillingPeriod($billingPeriod->value)) {
            throw new AddonException("Billing period '{$billingPeriod->value}' not available for this addon");
        }

        // Check quantity limits
        if ($quantity < $addon->min_quantity) {
            throw new AddonException("Minimum quantity is {$addon->min_quantity}");
        }

        if ($addon->max_quantity && $quantity > $addon->max_quantity) {
            throw new AddonException("Maximum quantity is {$addon->max_quantity}");
        }
    }

    /**
     * Validate bundle purchase
     */
    protected function validateBundlePurchase(
        Tenant $tenant,
        AddonBundle $bundle,
        BillingPeriod $billingPeriod
    ): void {
        // Check if bundle is active
        if (! $bundle->active) {
            throw new AddonException('This bundle is no longer available');
        }

        // Check if available for plan
        if ($tenant->plan && ! $bundle->isAvailableForPlan($tenant->plan)) {
            throw new AddonException('This bundle is not available for your plan');
        }
    }
}
