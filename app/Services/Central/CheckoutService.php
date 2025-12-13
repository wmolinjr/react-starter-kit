<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Exceptions\Central\AddonException;
use App\Models\Central\Addon;
use App\Models\Central\AddonPurchase;
use App\Models\Central\Customer;
use App\Models\Central\Tenant;
use App\Services\Payment\Gateways\StripeGateway;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Support\Facades\Log;

class CheckoutService
{
    protected ?StripeGateway $gateway = null;

    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {
        // Get the Stripe gateway (will be null if not configured)
        try {
            $this->gateway = $this->gatewayManager->stripe();
        } catch (\Exception $e) {
            // Gateway not available
        }
    }

    /**
     * Check if Stripe is configured.
     */
    public function isConfigured(): bool
    {
        return $this->gateway !== null && $this->gateway->isAvailable();
    }

    /**
     * Create Stripe Checkout session for one-time addon purchase.
     */
    public function createCheckoutSession(
        Tenant $tenant,
        string $addonSlug,
        int $quantity = 1,
        ?string $successUrl = null,
        ?string $cancelUrl = null
    ): array {
        if (! $this->isConfigured()) {
            throw new AddonException('Stripe gateway not configured');
        }

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

        // Ensure customer has Stripe customer ID
        $customer = $tenant->customer;
        if (! $customer) {
            throw new AddonException('Tenant has no associated customer for billing');
        }

        // Gateway handles customer creation
        $this->gateway->ensureCustomer($customer);

        // Create pending purchase record
        $purchase = AddonPurchase::create([
            'tenant_id' => $tenant->id,
            'addon_slug' => $addon->slug,
            'addon_type' => $addon->type->value,
            'quantity' => $quantity,
            'amount_paid' => $addon->price_one_time * $quantity,
            'payment_method' => 'stripe_checkout',
            'status' => 'pending',
            'valid_from' => now(),
            'valid_until' => now()->addMonths($addon->validity_months ?? 12),
            'metadata' => [
                'addon_name' => $addon->name,
                'unit_value' => $addon->unit_value,
            ],
        ]);

        // Build line items
        $lineItems = $addon->stripe_price_one_time_id
            ? [['price' => $addon->stripe_price_one_time_id, 'quantity' => $quantity]]
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
            $session = $this->gateway->createCheckoutWithItems($customer, $lineItems, [
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

            $purchase->update(['stripe_checkout_session_id' => $session['id']]);

            return [
                'session_id' => $session['id'],
                'url' => $session['url'],
                'purchase_id' => $purchase->id,
            ];
        } catch (\Exception $e) {
            $purchase->markAsFailed('Checkout session creation failed: '.$e->getMessage());
            Log::error('Failed to create Stripe checkout session', [
                'tenant_id' => $tenant->id,
                'addon_slug' => $addon->slug,
                'error' => $e->getMessage(),
            ]);
            throw new AddonException('Failed to create checkout session: '.$e->getMessage());
        }
    }

    /**
     * Create Stripe Checkout session for subscription addon purchase.
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
            throw new AddonException('Stripe gateway not configured');
        }

        $addon = Addon::where('slug', $addonSlug)->first();

        if (! $addon) {
            throw new AddonException("Addon not found: {$addonSlug}");
        }

        $priceField = $billingPeriod === 'yearly' ? 'price_yearly' : 'price_monthly';
        $stripePriceField = $billingPeriod === 'yearly' ? 'stripe_price_yearly_id' : 'stripe_price_monthly_id';

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

        // Ensure customer has Stripe customer ID
        $customer = $tenant->customer;
        if (! $customer) {
            throw new AddonException('Tenant has no associated customer for billing');
        }

        // Gateway handles customer creation
        $this->gateway->ensureCustomer($customer);

        // Build line items
        $lineItems = $addon->{$stripePriceField}
            ? [['price' => $addon->{$stripePriceField}, 'quantity' => $quantity]]
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
            $session = $this->gateway->createCheckoutWithItems($customer, $lineItems, [
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
        if (! $this->isConfigured()) {
            return null;
        }

        $purchase = AddonPurchase::where('stripe_checkout_session_id', $sessionId)->first();

        if (! $purchase) {
            Log::warning('Purchase not found for checkout session', ['session_id' => $sessionId]);

            return null;
        }

        if ($purchase->isCompleted()) {
            return $purchase; // Already processed
        }

        // Verify with Stripe
        try {
            $session = $this->gateway->retrieveCheckoutSession($sessionId);

            if (($session['payment_status'] ?? null) === 'paid') {
                $purchase->update([
                    'stripe_payment_intent_id' => $session['payment_intent'] ?? null,
                ]);
                $purchase->markAsCompleted();

                return $purchase;
            }
        } catch (\Exception $e) {
            Log::error('Failed to verify checkout session', [
                'session_id' => $sessionId,
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
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $session = $this->gateway->retrieveCheckoutSession($sessionId);

            return [
                'status' => $session['status'] ?? null,
                'payment_status' => $session['payment_status'] ?? null,
                'customer_email' => $session['customer_details']['email'] ?? null,
                'amount_total' => $session['amount_total'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve checkout session', [
                'session_id' => $sessionId,
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
            throw new AddonException('Stripe gateway not configured');
        }

        if (! $purchase->stripe_payment_intent_id) {
            throw new AddonException('No payment intent found for this purchase');
        }

        if ($purchase->isRefunded()) {
            throw new AddonException('Purchase has already been refunded');
        }

        try {
            $this->gateway->createRefund($purchase->stripe_payment_intent_id, $amount);
            $purchase->refund();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to process refund', [
                'purchase_id' => $purchase->id,
                'error' => $e->getMessage(),
            ]);

            throw new AddonException('Failed to process refund: '.$e->getMessage());
        }
    }
}
