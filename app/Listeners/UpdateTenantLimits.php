<?php

namespace App\Listeners;

use App\Models\Tenant;
use Laravel\Cashier\Events\WebhookReceived;

class UpdateTenantLimits
{
    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        if ($event->payload['type'] === 'customer.subscription.updated') {
            $customerId = $event->payload['data']['object']['customer'];
            $priceId = $event->payload['data']['object']['items']['data'][0]['price']['id'];

            $tenant = Tenant::where('stripe_id', $customerId)->first();

            if ($tenant) {
                $plan = collect(billing_plans())->first(fn ($p) => $p['price_id'] === $priceId);

                if ($plan) {
                    $tenant->update(['max_users' => $plan['limits']['max_users']]);
                    $tenant->updateSetting('limits', $plan['limits']);
                }
            }
        }
    }
}
