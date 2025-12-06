<?php

namespace App\Listeners\Central;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
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
                $plan = Plan::where('stripe_price_id', $priceId)->first();

                if ($plan) {
                    $tenant->update(['max_users' => $plan->limits['max_users'] ?? null]);
                    $tenant->updateSetting('limits', $plan->limits);
                }
            }
        }
    }
}
