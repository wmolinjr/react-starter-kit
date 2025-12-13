<?php

declare(strict_types=1);

namespace App\Listeners\Central;

use App\Enums\BillingPeriod;
use App\Events\Payment\PaymentConfirmed;
use App\Events\Payment\PaymentFailed;
use App\Events\Payment\WebhookReceived;
use App\Models\Central\Addon;
use App\Models\Central\AddonPurchase;
use App\Models\Central\AddonSubscription;
use App\Models\Central\Customer;
use App\Models\Central\Tenant;
use App\Services\Central\AddonService;
use App\Services\Central\MeteredBillingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Handle Addon Webhooks
 *
 * Processes payment webhook events related to addon purchases and subscriptions.
 * Listens to the provider-agnostic WebhookReceived event.
 */
class HandleAddonWebhooks implements ShouldQueue
{
    public string $queue = 'high';

    public function __construct(
        protected AddonService $addonService,
        protected MeteredBillingService $meteredService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        $type = $event->getType();

        match ($type) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event),
            'checkout.session.async_payment_succeeded' => $this->handleAsyncPaymentSucceeded($event),
            'checkout.session.async_payment_failed' => $this->handleAsyncPaymentFailed($event),
            'customer.subscription.created' => $this->handleCustomerSubscriptionCreated($event),
            'customer.subscription.updated' => $this->handleCustomerSubscriptionUpdated($event),
            'customer.subscription.deleted' => $this->handleCustomerSubscriptionDeleted($event),
            'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($event),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event),
            'charge.refunded' => $this->handleChargeRefunded($event),
            default => null,
        };
    }

    /**
     * Handle checkout.session.completed webhook.
     */
    protected function handleCheckoutSessionCompleted(WebhookReceived $event): void
    {
        $session = $event->getObject();

        Log::info('Checkout session completed', ['session_id' => $session['id'] ?? 'unknown']);

        $metadata = $session['metadata'] ?? [];

        if (($metadata['purchase_type'] ?? null) === 'one_time') {
            $this->processOneTimePurchase($session);
        }
    }

    /**
     * Handle checkout.session.async_payment_succeeded webhook.
     *
     * Fired when async payment methods (PIX, Boleto, OXXO) are confirmed.
     */
    protected function handleAsyncPaymentSucceeded(WebhookReceived $event): void
    {
        $session = $event->getObject();

        Log::info('Async payment succeeded', ['session_id' => $session['id'] ?? 'unknown']);

        $purchase = AddonPurchase::where('provider_session_id', $session['id'])->first();

        if (! $purchase) {
            Log::warning('Purchase not found for async payment', ['session_id' => $session['id']]);

            return;
        }

        // Dispatch PaymentConfirmed event for centralized handling
        PaymentConfirmed::dispatch(
            $purchase,
            $event->provider,
            $session['payment_intent'] ?? $session['id'],
            [
                'session_id' => $session['id'],
                'payment_status' => $session['payment_status'] ?? 'paid',
                'event_type' => 'checkout.session.async_payment_succeeded',
            ]
        );
    }

    /**
     * Handle checkout.session.async_payment_failed webhook.
     *
     * Fired when async payment methods (PIX, Boleto, OXXO) fail or expire.
     */
    protected function handleAsyncPaymentFailed(WebhookReceived $event): void
    {
        $session = $event->getObject();

        Log::info('Async payment failed', ['session_id' => $session['id'] ?? 'unknown']);

        $purchase = AddonPurchase::where('provider_session_id', $session['id'])->first();

        if (! $purchase) {
            Log::warning('Purchase not found for async payment failure', ['session_id' => $session['id']]);

            return;
        }

        // Get failure reason from session
        $reason = 'Async payment failed or expired';
        if (isset($session['payment_intent'])) {
            // Could fetch more details from payment_intent if needed
            $reason = 'Payment method confirmation failed';
        }

        // Dispatch PaymentFailed event for centralized handling
        PaymentFailed::dispatch(
            $purchase,
            $event->provider,
            $reason,
            [
                'session_id' => $session['id'],
                'payment_status' => $session['payment_status'] ?? 'unpaid',
                'event_type' => 'checkout.session.async_payment_failed',
            ]
        );
    }

    /**
     * Process one-time addon purchase.
     */
    protected function processOneTimePurchase(array $session): void
    {
        $purchase = AddonPurchase::where('provider_session_id', $session['id'])->first();

        if (! $purchase) {
            Log::warning('Purchase not found for checkout session', ['session_id' => $session['id']]);

            return;
        }

        if ($purchase->isCompleted()) {
            return;
        }

        if ($session['payment_status'] === 'paid') {
            $purchase->update([
                'provider_payment_intent_id' => $session['payment_intent'] ?? null,
            ]);
            $purchase->markAsCompleted();

            $addon = Addon::where('slug', $purchase->addon_slug)->first();

            if ($addon) {
                AddonSubscription::create([
                    'tenant_id' => $purchase->tenant_id,
                    'addon_slug' => $purchase->addon_slug,
                    'addon_type' => $addon->type->value,
                    'name' => $addon->name,
                    'description' => $addon->description,
                    'quantity' => $purchase->quantity,
                    'price' => $purchase->amount_paid / $purchase->quantity,
                    'billing_period' => BillingPeriod::ONE_TIME,
                    'status' => 'active',
                    'started_at' => now(),
                    'expires_at' => $purchase->valid_until,
                ]);

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
     * Handle customer.subscription.created webhook.
     */
    protected function handleCustomerSubscriptionCreated(WebhookReceived $event): void
    {
        $subscription = $event->getObject();
        $metadata = $subscription['metadata'] ?? [];

        Log::info('Subscription created', [
            'subscription_id' => $subscription['id'] ?? 'unknown',
            'metadata' => $metadata,
        ]);

        $tenantId = $metadata['tenant_id'] ?? null;
        $addonSlug = $metadata['addon_slug'] ?? null;
        $billingPeriod = $metadata['billing_period'] ?? 'monthly';

        if (! $tenantId || ! $addonSlug) {
            Log::warning('Subscription created without addon metadata', ['subscription_id' => $subscription['id'] ?? 'unknown']);

            return;
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            Log::warning('Tenant not found for subscription', ['tenant_id' => $tenantId]);

            return;
        }

        // Update customer provider_ids if needed
        $customer = $tenant->customer;
        if ($customer && ! empty($subscription['customer'])) {
            $providerIds = $customer->provider_ids ?? [];
            if (empty($providerIds[$event->provider])) {
                $providerIds[$event->provider] = $subscription['customer'];
                $customer->update(['provider_ids' => $providerIds]);
            }
        }

        $addon = Addon::where('slug', $addonSlug)->first();

        if (! $addon) {
            Log::warning('Addon not found in database', ['addon_slug' => $addonSlug]);

            return;
        }

        $quantity = 1;
        foreach ($subscription['items']['data'] ?? [] as $item) {
            $quantity = $item['quantity'] ?? 1;
            break;
        }

        $price = $billingPeriod === 'yearly' ? $addon->price_yearly : $addon->price_monthly;

        $tenantAddon = AddonSubscription::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'addon_slug' => $addonSlug,
                'billing_period' => BillingPeriod::from($billingPeriod),
            ],
            [
                'addon_type' => $addon->type->value,
                'name' => $addon->name,
                'description' => $addon->description,
                'quantity' => $quantity,
                'price' => $price ?? 0,
                'status' => 'active',
                'provider' => $event->provider,
                'provider_item_id' => $subscription['id'],
                'started_at' => now(),
            ]
        );

        $this->addonService->syncTenantLimits($tenant);

        Log::info('Addon created from subscription', [
            'tenant_id' => $tenant->id,
            'addon_id' => $tenantAddon->id,
            'addon_slug' => $addonSlug,
        ]);
    }

    /**
     * Handle customer.subscription.updated webhook.
     */
    protected function handleCustomerSubscriptionUpdated(WebhookReceived $event): void
    {
        $subscription = $event->getObject();
        $customerId = $subscription['customer'] ?? null;

        if (! $customerId) {
            return;
        }

        $customer = Customer::whereJsonContains('provider_ids->'.$event->provider, $customerId)->first();

        if ($customer) {
            foreach ($customer->tenants as $tenant) {
                $this->addonService->syncTenantLimits($tenant);
            }

            Log::info('Subscription updated, synced addon limits', ['customer_id' => $customer->id]);
        }
    }

    /**
     * Handle customer.subscription.deleted webhook.
     */
    protected function handleCustomerSubscriptionDeleted(WebhookReceived $event): void
    {
        $subscription = $event->getObject();
        $customerId = $subscription['customer'] ?? null;

        if (! $customerId) {
            return;
        }

        $customer = Customer::whereJsonContains('provider_ids->'.$event->provider, $customerId)->first();

        if ($customer) {
            foreach ($customer->tenants as $tenant) {
                $tenant->addons()
                    ->whereIn('billing_period', [BillingPeriod::MONTHLY, BillingPeriod::YEARLY])
                    ->where('status', 'active')
                    ->each(fn ($addon) => $addon->cancel('Subscription canceled'));
            }

            Log::info('Subscription deleted, canceled subscription addons', ['customer_id' => $customer->id]);
        }
    }

    /**
     * Handle invoice.payment_succeeded webhook.
     */
    protected function handleInvoicePaymentSucceeded(WebhookReceived $event): void
    {
        $invoice = $event->getObject();
        $customerId = $invoice['customer'] ?? null;

        if (! $customerId) {
            return;
        }

        $customer = Customer::whereJsonContains('provider_ids->'.$event->provider, $customerId)->first();

        if ($customer && $this->hasMeteredItems($invoice)) {
            foreach ($customer->tenants as $tenant) {
                $this->meteredService->resetMeteredUsage($tenant);
            }

            Log::info('Reset metered usage after invoice payment', ['customer_id' => $customer->id]);
        }
    }

    /**
     * Handle invoice.payment_failed webhook.
     */
    protected function handleInvoicePaymentFailed(WebhookReceived $event): void
    {
        $invoice = $event->getObject();
        $customerId = $invoice['customer'] ?? null;

        if (! $customerId) {
            return;
        }

        $customer = Customer::whereJsonContains('provider_ids->'.$event->provider, $customerId)->first();

        if ($customer) {
            Log::warning('Invoice payment failed', [
                'customer_id' => $customer->id,
                'invoice_id' => $invoice['id'] ?? 'unknown',
            ]);
        }
    }

    /**
     * Handle charge.refunded webhook.
     */
    protected function handleChargeRefunded(WebhookReceived $event): void
    {
        $charge = $event->getObject();
        $paymentIntentId = $charge['payment_intent'] ?? null;

        if ($paymentIntentId) {
            $purchase = AddonPurchase::where('provider_payment_intent_id', $paymentIntentId)->first();

            if ($purchase && ! $purchase->isRefunded()) {
                $purchase->refund();

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
    }

    /**
     * Check if invoice has metered items.
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
