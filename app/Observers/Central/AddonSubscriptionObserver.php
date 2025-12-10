<?php

namespace App\Observers\Central;

use App\Models\Central\AddonSubscription;
use App\Services\Central\AddonService;

/**
 * CENTRAL OBSERVER
 *
 * Observes AddonSubscription model in the central database.
 * Syncs tenant limits when addon subscriptions change.
 */
class AddonSubscriptionObserver
{
    public function __construct(protected AddonService $addonService) {}

    public function created(AddonSubscription $subscription): void
    {
        $this->syncLimits($subscription);
    }

    public function updated(AddonSubscription $subscription): void
    {
        if ($subscription->isDirty(['status', 'quantity'])) {
            $this->syncLimits($subscription);
        }
    }

    public function deleted(AddonSubscription $subscription): void
    {
        $this->syncLimits($subscription);
    }

    public function restored(AddonSubscription $subscription): void
    {
        $this->syncLimits($subscription);
    }

    protected function syncLimits(AddonSubscription $subscription): void
    {
        // Avoid recursive calls during tests or manual updates
        if ($subscription->tenant) {
            $this->addonService->syncTenantLimits($subscription->tenant);
        }
    }
}
