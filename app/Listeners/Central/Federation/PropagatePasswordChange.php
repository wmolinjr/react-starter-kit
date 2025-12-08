<?php

namespace App\Listeners\Central\Federation;

use App\Events\Central\Federation\FederatedUserPasswordChanged;
use App\Jobs\Central\Federation\PropagatePasswordChangeJob;

/**
 * Propagates password changes to all other tenants in the group.
 */
class PropagatePasswordChange
{
    /**
     * Handle the event.
     */
    public function handle(FederatedUserPasswordChanged $event): void
    {
        PropagatePasswordChangeJob::dispatch(
            $event->federatedUser,
            $event->hashedPassword,
            $event->sourceTenant->id
        );
    }
}
