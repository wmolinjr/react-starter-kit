<?php

declare(strict_types=1);

namespace App\Listeners\Central;

use App\Events\Payment\WebhookReceived;
use App\Models\Central\Customer;
use App\Models\Central\Plan;

/**
 * Updates tenant limits when subscription changes.
 *
 * Handles provider-agnostic webhook events to update tenant limits
 * based on their plan.
 */
class UpdateTenantLimits
{
    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        // Only handle Stripe subscription updates for now
        // TODO: Add support for other providers
        if (! $event->isFromProvider('stripe')) {
            return;
        }

        if (! $event->isType('customer.subscription.updated')) {
            return;
        }

        $object = $event->getObject();
        $providerCustomerId = $object['customer'] ?? null;
        $priceId = $object['items']['data'][0]['price']['id'] ?? null;

        if (! $providerCustomerId || ! $priceId) {
            return;
        }

        // Find customer by provider ID
        $customer = Customer::whereJsonContains('provider_ids->stripe', $providerCustomerId)->first();

        if (! $customer) {
            return;
        }

        // Get all tenants owned by this customer
        $tenants = $customer->ownedTenants;

        // Find the plan by price ID
        $plan = Plan::whereJsonContains('prices', [['stripe_price_id' => $priceId]])->first()
            ?? Plan::where('stripe_price_id', $priceId)->first();

        if (! $plan) {
            return;
        }

        // Update limits for all tenants owned by this customer
        foreach ($tenants as $tenant) {
            $tenant->update(['max_users' => $plan->limits['max_users'] ?? null]);
            $tenant->updateSetting('limits', $plan->limits);
        }
    }
}
