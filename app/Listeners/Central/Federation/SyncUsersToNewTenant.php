<?php

namespace App\Listeners\Central\Federation;

use App\Events\Central\Federation\TenantJoinedFederation;
use App\Jobs\Central\Federation\SyncAllUsersToTenantJob;

/**
 * Syncs all federated users to a newly joined tenant.
 */
class SyncUsersToNewTenant
{
    /**
     * Handle the event.
     */
    public function handle(TenantJoinedFederation $event): void
    {
        // Skip if this is the master tenant (first tenant in group)
        if ($event->group->isMaster($event->tenant)) {
            return;
        }

        SyncAllUsersToTenantJob::dispatch(
            $event->group,
            $event->tenant
        );
    }
}
