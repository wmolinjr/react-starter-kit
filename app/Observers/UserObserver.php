<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Only track for tenant users
        if (!tenancy()->initialized) {
            return;
        }

        $tenant = tenant();
        if ($tenant) {
            $tenant->incrementUsage('users');
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        // Only track for tenant users
        if (!tenancy()->initialized) {
            return;
        }

        $tenant = tenant();
        if ($tenant) {
            $tenant->decrementUsage('users');
        }
    }
}
