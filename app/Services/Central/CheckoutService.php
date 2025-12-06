<?php

namespace App\Services\Central;

use App\Exceptions\Central\AddonException;
use App\Models\Central\Addon;
use App\Models\Central\AddonPurchase;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class CheckoutService
{
    protected ?StripeClient $stripe = null;

    public function __construct()
    {
        $secret = config('cashier.secret');
        if ($secret) {
            $this->stripe = new StripeClient($secret);
        }
    }

    /**
     * Create Stripe Checkout session for one-time addon purchase
     */
    public function createCheckoutSession(
        Tenant $tenant,
        string $addonSlug,
        int $quantity = 1,
        ?string $successUrl = null,
        ?string $cancelUrl = null
    ): array {
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

        // Ensure tenant has Stripe customer
        if (! $tenant->stripe_id) {
            $tenant->createAsStripeCustomer();
        }

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
                'addon_name' => $addon->trans('name'),
                'unit_value' => $addon->unit_value,
            ],
        ]);

        // Build checkout session
        $sessionParams = [
            'customer' => $tenant->stripe_id,
            'mode' => 'payment',
            'locale' => stripe_locale(),
            'success_url' => $successUrl ?? $tenant->url().route('tenant.admin.addons.success', [], false).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl ?? $tenant->url().route('tenant.admin.addons.index', [], false),
            'metadata' => [
                'tenant_id' => $tenant->id,
                'addon_slug' => $addon->slug,
                'quantity' => $quantity,
                'purchase_id' => $purchase->id,
                'purchase_type' => 'one_time',
            ],
        ];

        // Use Stripe Price ID if available, otherwise ad-hoc
        if ($addon->stripe_price_one_time_id) {
            $sessionParams['line_items'] = [
                ['price' => $addon->stripe_price_one_time_id, 'quantity' => $quantity],
            ];
        } else {
            $sessionParams['line_items'] = [
                [
                    'price_data' => [
                        'currency' => config('cashier.currency', 'usd'),
                        'product_data' => [
                            'name' => $addon->trans('name'),
                            'description' => $addon->trans('description'),
                        ],
                        'unit_amount' => $addon->price_one_time,
                    ],
                    'quantity' => $quantity,
                ],
            ];
        }

        try {
            $session = $this->stripe->checkout->sessions->create($sessionParams);
            $purchase->update(['stripe_checkout_session_id' => $session->id]);

            return [
                'session_id' => $session->id,
                'url' => $session->url,
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
     * Create Stripe Checkout session for subscription addon purchase
     */
    public function createSubscriptionCheckout(
        Tenant $tenant,
        string $addonSlug,
        string $billingPeriod, // 'monthly' or 'yearly'
        int $quantity = 1,
        ?string $successUrl = null,
        ?string $cancelUrl = null
    ): array {
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

        // Ensure tenant has Stripe customer
        if (! $tenant->stripe_id) {
            $tenant->createAsStripeCustomer();
        }

        // Build checkout session
        $sessionParams = [
            'customer' => $tenant->stripe_id,
            'mode' => 'subscription',
            'locale' => stripe_locale(),
            'success_url' => $successUrl ?? $tenant->url().route('tenant.admin.addons.success', [], false).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl ?? $tenant->url().route('tenant.admin.addons.index', [], false),
            'metadata' => [
                'tenant_id' => $tenant->id,
                'addon_slug' => $addon->slug,
                'quantity' => $quantity,
                'billing_period' => $billingPeriod,
                'purchase_type' => 'subscription',
            ],
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'addon_slug' => $addon->slug,
                    'billing_period' => $billingPeriod,
                ],
            ],
        ];

        // Use Stripe Price ID if available
        if ($addon->{$stripePriceField}) {
            $sessionParams['line_items'] = [
                ['price' => $addon->{$stripePriceField}, 'quantity' => $quantity],
            ];
        } else {
            $interval = $billingPeriod === 'yearly' ? 'year' : 'month';
            $sessionParams['line_items'] = [
                [
                    'price_data' => [
                        'currency' => config('cashier.currency', 'usd'),
                        'product_data' => [
                            'name' => $addon->trans('name'),
                            'description' => $addon->trans('description'),
                            'metadata' => [
                                'addon_slug' => $addon->slug,
                                'addon_type' => $addon->type->value,
                            ],
                        ],
                        'unit_amount' => $addon->{$priceField},
                        'recurring' => ['interval' => $interval],
                    ],
                    'quantity' => $quantity,
                ],
            ];
        }

        try {
            $session = $this->stripe->checkout->sessions->create($sessionParams);

            Log::info('Subscription checkout session created', [
                'tenant_id' => $tenant->id,
                'addon_slug' => $addon->slug,
                'billing_period' => $billingPeriod,
                'session_id' => $session->id,
            ]);

            return [
                'session_id' => $session->id,
                'url' => $session->url,
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
     * Handle successful checkout completion
     */
    public function handleCheckoutCompleted(string $sessionId): ?AddonPurchase
    {
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
            $session = $this->stripe->checkout->sessions->retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                $purchase->update([
                    'stripe_payment_intent_id' => $session->payment_intent,
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
     * Get checkout session status
     */
    public function getSessionStatus(string $sessionId): ?array
    {
        try {
            $session = $this->stripe->checkout->sessions->retrieve($sessionId);

            return [
                'status' => $session->status,
                'payment_status' => $session->payment_status,
                'customer_email' => $session->customer_details?->email,
                'amount_total' => $session->amount_total,
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
     * Process refund for a purchase
     */
    public function refundPurchase(AddonPurchase $purchase, ?int $amount = null): bool
    {
        if (! $purchase->stripe_payment_intent_id) {
            throw new AddonException('No payment intent found for this purchase');
        }

        if ($purchase->isRefunded()) {
            throw new AddonException('Purchase has already been refunded');
        }

        try {
            $refundParams = [
                'payment_intent' => $purchase->stripe_payment_intent_id,
            ];

            if ($amount) {
                $refundParams['amount'] = $amount;
            }

            $this->stripe->refunds->create($refundParams);

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
