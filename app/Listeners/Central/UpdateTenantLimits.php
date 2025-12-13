<?php

declare(strict_types=1);

namespace App\Listeners\Central;

use App\Events\Payment\WebhookReceived;
use App\Models\Central\Customer;
use App\Models\Central\Plan;
use Illuminate\Support\Facades\Log;

/**
 * Updates tenant limits when subscription changes.
 *
 * Handles multi-provider webhook events to update tenant limits based on their plan.
 *
 * SUPPORTED PROVIDERS:
 * - Stripe: Handles subscription-based plan changes (customer.subscription.*)
 * - Asaas: Handles one-time payments (PIX/Boleto) - no subscription events
 *
 * Note: Asaas doesn't have native subscription support, so limit updates
 * from Asaas payments are handled in the checkout completion flow instead.
 */
class UpdateTenantLimits
{
    /**
     * Provider-specific event handlers.
     */
    protected array $providerHandlers = [
        'stripe' => 'handleStripeEvent',
        'asaas' => 'handleAsaasEvent',
    ];

    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        $handler = $this->providerHandlers[$event->provider] ?? null;

        if (! $handler || ! method_exists($this, $handler)) {
            return;
        }

        $this->{$handler}($event);
    }

    /**
     * Handle Stripe subscription events.
     */
    protected function handleStripeEvent(WebhookReceived $event): void
    {
        if (! $event->isType('customer.subscription.updated')) {
            return;
        }

        $object = $event->getObject();
        $providerCustomerId = $object['customer'] ?? null;
        $priceId = $object['items']['data'][0]['price']['id'] ?? null;

        if (! $providerCustomerId || ! $priceId) {
            return;
        }

        $customer = Customer::whereJsonContains('provider_ids->stripe', $providerCustomerId)->first();

        if (! $customer) {
            Log::info('UpdateTenantLimits: Customer not found for Stripe ID', [
                'stripe_customer_id' => $providerCustomerId,
            ]);

            return;
        }

        $tenants = $customer->ownedTenants;

        $plan = Plan::whereJsonContains('prices', [['stripe_price_id' => $priceId]])->first()
            ?? Plan::where('stripe_price_id', $priceId)->first();

        if (! $plan) {
            Log::warning('UpdateTenantLimits: Plan not found for price', [
                'price_id' => $priceId,
            ]);

            return;
        }

        foreach ($tenants as $tenant) {
            Log::info('UpdateTenantLimits: Updating tenant limits', [
                'tenant_id' => $tenant->id,
                'plan' => $plan->slug,
            ]);

            $tenant->update(['max_users' => $plan->limits['max_users'] ?? null]);
            $tenant->updateSetting('limits', $plan->limits);
        }
    }

    /**
     * Handle Asaas payment events.
     *
     * Asaas handles PIX and Boleto payments which are typically one-time.
     * Limit updates for Asaas payments are handled in the checkout flow,
     * not via webhooks, since Asaas doesn't have native subscription support.
     */
    protected function handleAsaasEvent(WebhookReceived $event): void
    {
        // Asaas payment confirmations (PAYMENT_CONFIRMED, PAYMENT_RECEIVED)
        // are handled in the checkout completion flow.
        // No limit updates needed here as Asaas doesn't manage subscriptions.
    }
}
