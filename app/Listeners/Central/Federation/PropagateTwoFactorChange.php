<?php

namespace App\Listeners\Central\Federation;

use App\Events\Central\Federation\FederatedUserTwoFactorChanged;
use App\Jobs\Central\Federation\PropagateTwoFactorChangeJob;

/**
 * Propagates 2FA changes to all other tenants in the group.
 */
class PropagateTwoFactorChange
{
    /**
     * Handle the event.
     */
    public function handle(FederatedUserTwoFactorChanged $event): void
    {
        PropagateTwoFactorChangeJob::dispatch(
            $event->federatedUser,
            $event->twoFactorData,
            $event->sourceTenant->id
        );
    }
}
