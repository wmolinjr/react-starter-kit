<?php

namespace App\Http\Controllers\Billing;

use App\Enums\BillingPeriod;
use App\Models\Central\Addon;
use App\Models\Central\AddonPurchase;
use App\Models\Central\AddonSubscription;
use App\Models\Central\Tenant;
use App\Services\Central\AddonService;
use App\Services\Central\CheckoutService;
use App\Services\Central\MeteredBillingService;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;

class AddonWebhookController extends CashierWebhookController
{
    public function __construct(
        protected AddonService $addonService,
        protected CheckoutService $checkoutService,
        protected MeteredBillingService $meteredService
    ) {
    }

    /**
     * Handle checkout.session.completed webhook
     */
    protected function handleCheckoutSessionCompleted(array $payload): Response
    {
        $session = $payload['data']['object'];

        Log::info('Checkout session completed', ['session_id' => $session['id']]);

        // Check if this is an addon purchase
        $metadata = $session['metadata'] ?? [];

        if (($metadata['purchase_type'] ?? null) === 'one_time') {
            $this->processOneTimePurchase($session);
        }

        return $this->successMethod();
    }

    /**
     * Process one-time addon purchase
     */
    protected function processOneTimePurchase(array $session): void
    {
        $purchase = AddonPurchase::where('stripe_checkout_session_id', $session['id'])->first();

        if (! $purchase) {
            Log::warning('Purchase not found for checkout session', ['session_id' => $session['id']]);

            return;
        }

        if ($purchase->isCompleted()) {
            return; // Already processed
        }

        if ($session['payment_status'] === 'paid') {
            $purchase->update([
                'stripe_payment_intent_id' => $session['payment_intent'] ?? null,
            ]);
            $purchase->markAsCompleted();

            // Create the addon record for this one-time purchase
            $addon = Addon::where('slug', $purchase->addon_slug)->first();

            if ($addon) {
                AddonSubscription::create([
                    'tenant_id' => $purchase->tenant_id,
                    'addon_slug' => $purchase->addon_slug,
                    'addon_type' => $addon->type->value,
                    'name' => $addon->trans('name'),
                    'description' => $addon->trans('description'),
                    'quantity' => $purchase->quantity,
                    'price' => $purchase->amount_paid / $purchase->quantity,
                    'billing_period' => BillingPeriod::ONE_TIME,
                    'status' => 'active',
                    'started_at' => now(),
                    'expires_at' => $purchase->valid_until,
                ]);

                // Sync tenant limits
                $this->addonService->syncTenantLimits($purchase->tenant);
            } else {
                Log::warning('Addon not found in database', ['addon_slug' => $purchase->addon_slug]);
            }

            Log::info('One-time purchase completed', [
                'purchase_id' => $purchase->id,
                'addon_slug' => $purchase->addon_slug,
            ]);
        }
    }

    /**
     * Handle customer.subscription.created webhook
     */
    protected function handleCustomerSubscriptionCreated(array $payload): Response
    {
        $subscription = $payload['data']['object'];
        $metadata = $subscription['metadata'] ?? [];

        Log::info('Subscription created', [
            'subscription_id' => $subscription['id'],
            'metadata' => $metadata,
        ]);

        $tenantId = $metadata['tenant_id'] ?? null;
        $addonSlug = $metadata['addon_slug'] ?? null;
        $billingPeriod = $metadata['billing_period'] ?? 'monthly';

        if (! $tenantId || ! $addonSlug) {
            Log::warning('Subscription created without addon metadata', ['subscription_id' => $subscription['id']]);

            return $this->successMethod();
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            Log::warning('Tenant not found for subscription', ['tenant_id' => $tenantId]);

            return $this->successMethod();
        }

        // Update tenant stripe_id if not set
        if (! $tenant->stripe_id) {
            $tenant->update(['stripe_id' => $subscription['customer']]);
        }

        $addon = Addon::where('slug', $addonSlug)->first();

        if (! $addon) {
            Log::warning('Addon not found in database', ['addon_slug' => $addonSlug]);

            return $this->successMethod();
        }

        // Get quantity from subscription items
        $quantity = 1;
        foreach ($subscription['items']['data'] ?? [] as $item) {
            $quantity = $item['quantity'] ?? 1;
            break;
        }

        // Get price based on billing period
        $price = $billingPeriod === 'yearly' ? $addon->price_yearly : $addon->price_monthly;

        // Create or update addon record
        $tenantAddon = AddonSubscription::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'addon_slug' => $addonSlug,
                'billing_period' => BillingPeriod::from($billingPeriod),
            ],
            [
                'addon_type' => $addon->type->value,
                'name' => $addon->trans('name'),
                'description' => $addon->trans('description'),
                'quantity' => $quantity,
                'price' => $price ?? 0,
                'status' => 'active',
                'stripe_subscription_id' => $subscription['id'],
                'started_at' => now(),
            ]
        );

        // Sync tenant limits
        $this->addonService->syncTenantLimits($tenant);

        Log::info('Addon created from subscription', [
            'tenant_id' => $tenant->id,
            'addon_id' => $tenantAddon->id,
            'addon_slug' => $addonSlug,
        ]);

        return $this->successMethod();
    }

    /**
     * Handle customer.subscription.updated webhook
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        // Let Cashier handle base subscription updates
        parent::handleCustomerSubscriptionUpdated($payload);

        $subscription = $payload['data']['object'];
        $tenant = Tenant::where('stripe_id', $subscription['customer'])->first();

        if ($tenant) {
            // Sync addon limits when subscription changes
            $this->addonService->syncTenantLimits($tenant);

            Log::info('Subscription updated, synced addon limits', ['tenant_id' => $tenant->id]);
        }

        return $this->successMethod();
    }

    /**
     * Handle customer.subscription.deleted webhook
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        parent::handleCustomerSubscriptionDeleted($payload);

        $subscription = $payload['data']['object'];
        $tenant = Tenant::where('stripe_id', $subscription['customer'])->first();

        if ($tenant) {
            // Cancel all subscription-based addons
            $tenant->addons()
                ->whereIn('billing_period', [BillingPeriod::MONTHLY, BillingPeriod::YEARLY])
                ->where('status', 'active')
                ->each(fn ($addon) => $addon->cancel('Subscription canceled'));

            Log::info('Subscription deleted, canceled subscription addons', ['tenant_id' => $tenant->id]);
        }

        return $this->successMethod();
    }

    /**
     * Handle invoice.payment_succeeded webhook
     */
    protected function handleInvoicePaymentSucceeded(array $payload): Response
    {
        $invoice = $payload['data']['object'];
        $tenant = Tenant::where('stripe_id', $invoice['customer'])->first();

        if ($tenant) {
            // Reset metered usage after successful billing
            if ($this->hasMeteredItems($invoice)) {
                $this->meteredService->resetMeteredUsage($tenant);

                Log::info('Reset metered usage after invoice payment', ['tenant_id' => $tenant->id]);
            }
        }

        return $this->successMethod();
    }

    /**
     * Handle invoice.payment_failed webhook
     */
    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        $invoice = $payload['data']['object'];
        $tenant = Tenant::where('stripe_id', $invoice['customer'])->first();

        if ($tenant) {
            Log::warning('Invoice payment failed', [
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice['id'],
            ]);

            // Could send notification, pause features, etc.
        }

        return $this->successMethod();
    }

    /**
     * Handle charge.refunded webhook
     */
    protected function handleChargeRefunded(array $payload): Response
    {
        $charge = $payload['data']['object'];
        $paymentIntentId = $charge['payment_intent'] ?? null;

        if ($paymentIntentId) {
            $purchase = AddonPurchase::where('stripe_payment_intent_id', $paymentIntentId)->first();

            if ($purchase && ! $purchase->isRefunded()) {
                $purchase->refund();

                // Deactivate associated addon
                $addon = AddonSubscription::where('tenant_id', $purchase->tenant_id)
                    ->where('addon_slug', $purchase->addon_slug)
                    ->where('billing_period', BillingPeriod::ONE_TIME)
                    ->first();

                if ($addon) {
                    $addon->cancel('Refunded');
                    $this->addonService->syncTenantLimits($purchase->tenant);
                }

                Log::info('Purchase refunded', ['purchase_id' => $purchase->id]);
            }
        }

        return $this->successMethod();
    }

    /**
     * Check if invoice has metered items
     */
    protected function hasMeteredItems(array $invoice): bool
    {
        foreach ($invoice['lines']['data'] ?? [] as $line) {
            if (($line['price']['recurring']['usage_type'] ?? null) === 'metered') {
                return true;
            }
        }

        return false;
    }
}
