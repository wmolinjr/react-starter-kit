<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Contracts\Payment\CheckoutGatewayInterface;
use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Payment\RefundGatewayInterface;
use App\Exceptions\Central\AddonException;
use App\Models\Central\Addon;
use App\Models\Central\AddonPurchase;
use App\Models\Central\Tenant;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Support\Facades\Log;

class CheckoutService
{
    protected ?PaymentGatewayInterface $gateway = null;

    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {
        $this->gateway = $this->gatewayManager->driver();
    }

    /**
     * Check if payment gateway is configured.
     */
    public function isConfigured(): bool
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
     * Check if gateway supports refund operations.
     */
    protected function supportsRefunds(): bool
    {
        return $this->gateway instanceof RefundGatewayInterface;
    }

    /**
     * Get the current provider identifier.
     */
    public function getProvider(): string
    {
        return $this->gateway?->getIdentifier() ?? 'unknown';
    }

    /**
     * Create Checkout session for one-time addon purchase.
     */
    public function createCheckoutSession(
        Tenant $tenant,
        string $addonSlug,
        int $quantity = 1,
        ?string $successUrl = null,
        ?string $cancelUrl = null
    ): array {
        if (! $this->isConfigured()) {
            throw new AddonException('Payment gateway not configured');
        }

        if (! $this->supportsCheckout()) {
            throw new AddonException("Payment gateway '{$this->getProvider()}' does not support checkout");
        }

        /** @var CheckoutGatewayInterface $gateway */
        $gateway = $this->gateway;
        $provider = $this->getProvider();

        $addon = Addon::where('slug', $addonSlug)->first();

        if (! $addon) {
            throw new AddonException("Addon not found: {$addonSlug}");
        }

        if (! $addon->price_one_time) {
            throw new AddonException("Addon {$addonSlug} does not support one-time purchase");
        }

        // Validate quantity
        if ($quantity < $addon->min_quantity) {
            throw new AddonException("Minimum quantity is {$addon->min_quantity}");
        }

        if ($addon->max_quantity && $quantity > $addon->max_quantity) {
            throw new AddonException("Maximum quantity is {$addon->max_quantity}");
        }

        // Ensure customer exists
        $customer = $tenant->customer;
        if (! $customer) {
            throw new AddonException('Tenant has no associated customer for billing');
        }

        // Gateway handles customer creation
        $gateway->ensureCustomer($customer);

        // Create pending purchase record
        $purchase = AddonPurchase::create([
            'tenant_id' => $tenant->id,
            'addon_slug' => $addon->slug,
            'addon_type' => $addon->type->value,
            'quantity' => $quantity,
            'amount_paid' => $addon->price_one_time * $quantity,
            'payment_method' => "{$provider}_checkout",
            'status' => 'pending',
            'valid_from' => now(),
            'valid_until' => now()->addMonths($addon->validity_months ?? 12),
            'metadata' => [
                'addon_name' => $addon->name,
                'unit_value' => $addon->unit_value,
                'provider' => $provider,
            ],
        ]);

        // Build line items - use provider-specific price ID if available
        $priceId = $addon->getProviderPriceId($provider, 'one_time');
        $lineItems = $priceId
            ? [['price' => $priceId, 'quantity' => $quantity]]
            : [[
                'price_data' => [
                    'currency' => config('payment.currency', 'brl'),
                    'product_data' => [
                        'name' => $addon->name,
                        'description' => $addon->description,
                    ],
                    'unit_amount' => $addon->price_one_time,
                ],
                'quantity' => $quantity,
            ]];

        try {
            $session = $gateway->createCheckoutWithItems($customer, $lineItems, [
                'mode' => 'payment',
                'locale' => stripe_locale(),
                'success_url' => $successUrl ?? $tenant->url().route('tenant.admin.addons.success', [], false).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl ?? $tenant->url().route('tenant.admin.addons.index', [], false),
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'addon_slug' => $addon->slug,
                    'quantity' => $quantity,
                    'purchase_id' => $purchase->id,
                    'purchase_type' => 'one_time',
                ],
            ]);

            $purchase->update([
                'provider' => $provider,
                'provider_session_id' => $session['id'],
            ]);

            return [
                'session_id' => $session['id'],
                'url' => $session['url'],
                'purchase_id' => $purchase->id,
            ];
        } catch (\Exception $e) {
            $purchase->markAsFailed('Checkout session creation failed: '.$e->getMessage());
            Log::error('Failed to create checkout session', [
                'tenant_id' => $tenant->id,
                'addon_slug' => $addon->slug,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            throw new AddonException('Failed to create checkout session: '.$e->getMessage());
        }
    }

    /**
     * Create Checkout session for subscription addon purchase.
     */
    public function createSubscriptionCheckout(
        Tenant $tenant,
        string $addonSlug,
        string $billingPeriod, // 'monthly' or 'yearly'
        int $quantity = 1,
        ?string $successUrl = null,
        ?string $cancelUrl = null
    ): array {
        if (! $this->isConfigured()) {
            throw new AddonException('Payment gateway not configured');
        }

        if (! $this->supportsCheckout()) {
            throw new AddonException("Payment gateway '{$this->getProvider()}' does not support checkout");
        }

        /** @var CheckoutGatewayInterface $gateway */
        $gateway = $this->gateway;
        $provider = $this->getProvider();

        $addon = Addon::where('slug', $addonSlug)->first();

        if (! $addon) {
            throw new AddonException("Addon not found: {$addonSlug}");
        }

        $priceField = $billingPeriod === 'yearly' ? 'price_yearly' : 'price_monthly';

        if (! $addon->{$priceField}) {
            throw new AddonException("Addon {$addonSlug} does not support {$billingPeriod} billing");
        }

        // Validate quantity
        if ($quantity < $addon->min_quantity) {
            throw new AddonException("Minimum quantity is {$addon->min_quantity}");
        }

        if ($addon->max_quantity && $quantity > $addon->max_quantity) {
            throw new AddonException("Maximum quantity is {$addon->max_quantity}");
        }

        // Ensure customer exists
        $customer = $tenant->customer;
        if (! $customer) {
            throw new AddonException('Tenant has no associated customer for billing');
        }

        // Gateway handles customer creation
        $gateway->ensureCustomer($customer);

        // Build line items - use provider-specific price ID if available
        $priceId = $addon->getProviderPriceId($provider, $billingPeriod);
        $lineItems = $priceId
            ? [['price' => $priceId, 'quantity' => $quantity]]
            : [[
                'price_data' => [
                    'currency' => config('payment.currency', 'brl'),
                    'product_data' => [
                        'name' => $addon->name,
                        'description' => $addon->description,
                        'metadata' => [
                            'addon_slug' => $addon->slug,
                            'addon_type' => $addon->type->value,
                        ],
                    ],
                    'unit_amount' => $addon->{$priceField},
                    'recurring' => ['interval' => $billingPeriod === 'yearly' ? 'year' : 'month'],
                ],
                'quantity' => $quantity,
            ]];

        try {
            $session = $gateway->createCheckoutWithItems($customer, $lineItems, [
                'mode' => 'subscription',
                'locale' => stripe_locale(),
                'success_url' => $successUrl ?? $tenant->url().route('tenant.admin.addons.success', [], false).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl ?? $tenant->url().route('tenant.admin.addons.index', [], false),
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'addon_slug' => $addon->slug,
                    'quantity' => $quantity,
                    'billing_period' => $billingPeriod,
                    'purchase_type' => 'subscription',
                ],
                'subscription_data' => [
                    'metadata' => [
                        'tenant_id' => $tenant->id,
                        'customer_id' => $customer->id,
                        'addon_slug' => $addon->slug,
                        'billing_period' => $billingPeriod,
                    ],
                ],
            ]);

            Log::info('Subscription checkout session created', [
                'tenant_id' => $tenant->id,
                'addon_slug' => $addon->slug,
                'billing_period' => $billingPeriod,
                'provider' => $provider,
                'session_id' => $session['id'],
            ]);

            return [
                'session_id' => $session['id'],
                'url' => $session['url'],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create subscription checkout session', [
                'tenant_id' => $tenant->id,
                'addon_slug' => $addon->slug,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            throw new AddonException('Failed to create checkout session: '.$e->getMessage());
        }
    }

    /**
     * Handle successful checkout completion.
     */
    public function handleCheckoutCompleted(string $sessionId): ?AddonPurchase
    {
        if (! $this->isConfigured() || ! $this->supportsCheckout()) {
            return null;
        }

        /** @var CheckoutGatewayInterface $gateway */
        $gateway = $this->gateway;

        $purchase = AddonPurchase::where('provider_session_id', $sessionId)->first();

        if (! $purchase) {
            Log::warning('Purchase not found for checkout session', ['session_id' => $sessionId]);

            return null;
        }

        if ($purchase->isCompleted()) {
            return $purchase; // Already processed
        }

        // Verify with payment provider
        try {
            $session = $gateway->retrieveCheckoutSession($sessionId);

            if (($session['payment_status'] ?? null) === 'paid') {
                $purchase->update([
                    'provider_payment_intent_id' => $session['payment_intent'] ?? null,
                ]);
                $purchase->markAsCompleted();

                return $purchase;
            }
        } catch (\Exception $e) {
            Log::error('Failed to verify checkout session', [
                'session_id' => $sessionId,
                'provider' => $this->getProvider(),
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get checkout session status.
     */
    public function getSessionStatus(string $sessionId): ?array
    {
        if (! $this->isConfigured() || ! $this->supportsCheckout()) {
            return null;
        }

        /** @var CheckoutGatewayInterface $gateway */
        $gateway = $this->gateway;

        try {
            $session = $gateway->retrieveCheckoutSession($sessionId);

            return [
                'status' => $session['status'] ?? null,
                'payment_status' => $session['payment_status'] ?? null,
                'customer_email' => $session['customer_details']['email'] ?? null,
                'amount_total' => $session['amount_total'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve checkout session', [
                'session_id' => $sessionId,
                'provider' => $this->getProvider(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Process refund for a purchase.
     */
    public function refundPurchase(AddonPurchase $purchase, ?int $amount = null): bool
    {
        if (! $this->isConfigured()) {
            throw new AddonException('Payment gateway not configured');
        }

        if (! $this->supportsRefunds()) {
            throw new AddonException("Payment gateway '{$this->getProvider()}' does not support refunds");
        }

        /** @var RefundGatewayInterface $gateway */
        $gateway = $this->gateway;

        if (! $purchase->provider_payment_intent_id) {
            throw new AddonException('No payment intent found for this purchase');
        }

        if ($purchase->isRefunded()) {
            throw new AddonException('Purchase has already been refunded');
        }

        try {
            $gateway->createRefund($purchase->provider_payment_intent_id, $amount);
            $purchase->refund();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to process refund', [
                'purchase_id' => $purchase->id,
                'provider' => $this->getProvider(),
                'error' => $e->getMessage(),
            ]);

            throw new AddonException('Failed to process refund: '.$e->getMessage());
        }
    }
}
