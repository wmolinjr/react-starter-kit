<?php

namespace App\Observers;

use App\Models\Project;

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
