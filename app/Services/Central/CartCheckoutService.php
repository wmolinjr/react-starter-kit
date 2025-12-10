<?php

namespace App\Services\Central;

use App\Enums\BillingPeriod;
use App\Exceptions\Central\AddonException;
use App\Models\Central\Addon;
use App\Models\Central\AddonBundle;
use App\Models\Central\AddonPurchase;
use App\Models\Central\Tenant;
use App\Services\Payment\Gateways\AsaasGateway;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class CartCheckoutService
{
    protected ?StripeClient $stripe = null;

    public function __construct(
        protected AddonService $addonService,
        protected PaymentGatewayManager $gatewayManager
    ) {
        $secret = config('payment.drivers.stripe.secret');
        if ($secret) {
            $this->stripe = new StripeClient($secret);
        }
    }

    /**
     * Process cart checkout with multi-payment method support.
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

        return match ($paymentMethod) {
            'card' => $this->processCardCheckout($tenant, $items, $lineItems, $hasRecurring),
            'pix' => $this->processPixCheckout($tenant, $items, $lineItems, $totalAmount),
            'boleto' => $this->processBoletoCheckout($tenant, $items, $lineItems, $totalAmount),
            default => throw new AddonException('Invalid payment method: '.$paymentMethod),
        };
    }

    /**
     * Process card checkout via Stripe.
     *
     * @return array{type: string, session_id: string, url: string}
     */
    protected function processCardCheckout(
        Tenant $tenant,
        array $items,
        array $lineItems,
        bool $hasRecurring
    ): array {
        if (! $this->stripe) {
            throw new AddonException('Stripe is not configured');
        }

        // Ensure tenant has a Stripe customer ID
        $customer = $tenant->customer;
        if (! $customer) {
            throw new AddonException('Tenant has no associated customer for billing');
        }

        $stripeCustomerId = $this->ensureStripeCustomer($customer);

        $stripeLineItems = array_map(fn ($item) => $item['stripe_item'], $lineItems);
        $mode = $hasRecurring ? 'subscription' : 'payment';

        $session = $this->stripe->checkout->sessions->create([
            'customer' => $stripeCustomerId,
            'mode' => $mode,
            'locale' => stripe_locale(),
            'line_items' => $stripeLineItems,
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
            'session_id' => $session->id,
            'items_count' => count($items),
            'mode' => $mode,
        ]);

        return [
            'type' => 'redirect',
            'session_id' => $session->id,
            'url' => $session->url,
        ];
    }

    /**
     * Process PIX checkout via Asaas.
     *
     * @return array{type: string, payment_id: string, purchase_id: string, pix: array}
     */
    protected function processPixCheckout(
        Tenant $tenant,
        array $items,
        array $lineItems,
        int $totalAmount
    ): array {
        $customer = $tenant->customer;
        if (! $customer) {
            throw new AddonException('Tenant has no associated customer for billing');
        }

        // Create pending purchase record for tracking
        $purchase = $this->createPendingPurchase($tenant, $items, $totalAmount, 'pix');

        /** @var AsaasGateway $asaas */
        $asaas = $this->gatewayManager->gateway('asaas');

        $result = $asaas->createPixCharge($customer, $totalAmount, [
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
            'amount' => $totalAmount,
        ]);

        return [
            'type' => 'pix',
            'payment_id' => $result->providerPaymentId,
            'purchase_id' => $purchase->id,
            'amount' => $totalAmount,
            'pix' => $result->providerData['pix'] ?? [],
        ];
    }

    /**
     * Process Boleto checkout via Asaas.
     *
     * @return array{type: string, payment_id: string, purchase_id: string, boleto: array}
     */
    protected function processBoletoCheckout(
        Tenant $tenant,
        array $items,
        array $lineItems,
        int $totalAmount
    ): array {
        $customer = $tenant->customer;
        if (! $customer) {
            throw new AddonException('Tenant has no associated customer for billing');
        }

        // Create pending purchase record for tracking
        $purchase = $this->createPendingPurchase($tenant, $items, $totalAmount, 'boleto');

        /** @var AsaasGateway $asaas */
        $asaas = $this->gatewayManager->gateway('asaas');

        $result = $asaas->createBoletoCharge($customer, $totalAmount, [
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
            'amount' => $totalAmount,
        ]);

        return [
            'type' => 'boleto',
            'payment_id' => $result->providerPaymentId,
            'purchase_id' => $purchase->id,
            'amount' => $totalAmount,
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
        string $paymentMethod
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
            'metadata' => [
                'cart_items' => $items,
                'payment_provider' => 'asaas',
            ],
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
     * Ensure customer has a Stripe customer ID.
     */
    protected function ensureStripeCustomer($customer): string
    {
        $stripeId = $customer->getProviderId('stripe');

        if ($stripeId) {
            return $stripeId;
        }

        $stripeCustomer = $this->stripe->customers->create([
            'email' => $customer->email,
            'name' => $customer->name ?? $customer->email,
            'metadata' => [
                'customer_id' => $customer->id,
            ],
        ]);

        $customer->setProviderId('stripe', $stripeCustomer->id);

        return $stripeCustomer->id;
    }

    /**
     * Check payment status for async payments (PIX/Boleto).
     */
    public function checkPaymentStatus(string $paymentId): array
    {
        /** @var AsaasGateway $asaas */
        $asaas = $this->gatewayManager->gateway('asaas');

        try {
            $paymentData = $asaas->retrievePayment($paymentId);
            $rawStatus = $paymentData['status'] ?? 'FAILED';

            return [
                'status' => $this->mapAsaasPaymentStatus($rawStatus),
                'raw_status' => $rawStatus,
                'payment_date' => $paymentData['paymentDate'] ?? null,
                'confirmed_date' => $paymentData['confirmedDate'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to check payment status', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Map Asaas payment status to internal status.
     */
    protected function mapAsaasPaymentStatus(string $status): string
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
     * Refresh PIX QR code for an existing payment.
     */
    public function refreshPixQrCode(string $paymentId): ?array
    {
        /** @var AsaasGateway $asaas */
        $asaas = $this->gatewayManager->gateway('asaas');

        return $asaas->getPixQrCode($paymentId);
    }

    /**
     * Create a Stripe Checkout session for cart items
     *
     * @param  array<int, array{type: string, slug: string, quantity: int, billing_period: string}>  $items
     * @return array{session_id: string, url: string}
     */
    public function createCartCheckoutSession(Tenant $tenant, array $items): array
    {
        if (! $this->stripe) {
            throw new AddonException('Stripe is not configured');
        }

        // Ensure tenant has a Stripe customer ID
        if (! $tenant->stripe_id) {
            $tenant->createAsStripeCustomer();
        }

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

        $session = $this->stripe->checkout->sessions->create([
            'customer' => $tenant->stripe_id,
            'mode' => $mode,
            'locale' => stripe_locale(),
            'line_items' => $lineItems,
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
            'session_id' => $session->id,
            'items_count' => count($items),
            'mode' => $mode,
        ]);

        return [
            'session_id' => $session->id,
            'url' => $session->url,
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
        $stripePriceId = $addon->getStripePriceId($billingPeriod->value);
        $price = $this->getAddonPrice($addon, $billingPeriod);

        // Use existing Stripe price if available
        if ($stripePriceId) {
            return [
                'stripe_item' => [
                    'price' => $stripePriceId,
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
