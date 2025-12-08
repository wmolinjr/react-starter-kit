<?php

namespace App\Listeners\Central\Federation;

use App\Events\Central\Federation\FederatedUserUpdated;
use App\Jobs\Central\Federation\SyncUserToFederatedTenantsJob;

/**
 * Syncs updated user data to all other tenants in the group.
 */
class SyncUpdatedFederatedUser
{
    /**
     * Handle the event.
     */
    public function handle(FederatedUserUpdated $event): void
    {
        // Dispatch job to sync only changed fields
        SyncUserToFederatedTenantsJob::dispatch(
            $event->federatedUser,
            $event->changedFields,
            $event->sourceTenant->id
        );
    }
}
