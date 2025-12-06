<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Project;

/**
 * TENANT OBSERVER
 *
 * Observes Project model in tenant databases.
 * Tracks project usage for plan limits.
 */
class ProjectObserver
{
    /**
     * Handle the Project "created" event.
     */
    public function created(Project $project): void
    {
        // Only track for tenant projects
        if (!tenancy()->initialized) {
            return;
        }

        $tenant = tenant();
        if ($tenant) {
            $tenant->incrementUsage('projects');
        }
    }

    /**
     * Handle the Project "deleted" event.
     */
    public function deleted(Project $project): void
    {
        // Only track for tenant projects
        if (!tenancy()->initialized) {
            return;
        }

        $tenant = tenant();
        if ($tenant) {
            $tenant->decrementUsage('projects');
        }
    }
}
