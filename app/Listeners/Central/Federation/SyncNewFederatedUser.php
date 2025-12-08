<?php

namespace App\Listeners\Central\Federation;

use App\Events\Central\Federation\FederatedUserCreated;
use App\Jobs\Central\Federation\SyncUserToFederatedTenantsJob;

/**
 * Syncs a newly created federated user to all other tenants in the group.
 */
class SyncNewFederatedUser
{
    /**
     * Handle the event.
     */
    public function handle(FederatedUserCreated $event): void
    {
        // Dispatch job to sync user to all other tenants
        SyncUserToFederatedTenantsJob::dispatch(
            $event->federatedUser,
            null, // all fields
            $event->sourceTenant->id // exclude source tenant
        );
    }
}
