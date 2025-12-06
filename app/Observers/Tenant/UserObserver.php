<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\User;

/**
 * TENANT OBSERVER
 *
 * Observes User model in tenant databases.
 * Tracks user usage for plan limits.
 */
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
