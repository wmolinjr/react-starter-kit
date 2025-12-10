<?php

namespace App\Services\Central;

use App\Enums\BillingPeriod;
use App\Exceptions\Central\AddonException;
use App\Models\Central\Addon;
use App\Models\Central\AddonBundle;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class CartCheckoutService
{
    protected ?StripeClient $stripe = null;

    public function __construct(
        protected AddonService $addonService
    ) {
        $secret = config('cashier.secret');
        if ($secret) {
            $this->stripe = new StripeClient($secret);
        }
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
     * @return array{stripe_item: array<string, mixed>, is_recurring: bool}
     */
    protected function buildAddonLineItem(Tenant $tenant, array $item): array
    {
        $addon = Addon::where('slug', $item['slug'])->firstOrFail();
        $billingPeriod = BillingPeriod::from($item['billing_period']);

        // Validate the purchase is allowed
        $this->validateAddonPurchase($tenant, $addon, $item['quantity'], $billingPeriod);

        $isRecurring = $billingPeriod !== BillingPeriod::ONE_TIME;
        $stripePriceId = $addon->getStripePriceId($billingPeriod->value);

        // Use existing Stripe price if available
        if ($stripePriceId) {
            return [
                'stripe_item' => [
                    'price' => $stripePriceId,
                    'quantity' => $item['quantity'],
                ],
                'is_recurring' => $isRecurring,
            ];
        }

        // Create ad-hoc price
        $price = $this->getAddonPrice($addon, $billingPeriod);

        $priceData = [
            'currency' => config('cashier.currency', 'usd'),
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
        ];
    }

    /**
     * Build Stripe line item for a bundle
     *
     * @param  array{type: string, slug: string, quantity: int, billing_period: string}  $item
     * @return array{stripe_item: array<string, mixed>, is_recurring: bool}
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
            'currency' => config('cashier.currency', 'usd'),
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
