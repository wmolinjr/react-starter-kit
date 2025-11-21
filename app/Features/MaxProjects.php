<?php

namespace App\Features;

use App\Models\Tenant;

class MaxProjects
{
    /**
     * Resolve the feature's initial value.
     *
     * Returns the project limit as an integer (rich value)
     */
    public function resolve(Tenant $tenant): int
    {
        return $tenant->getLimit('projects');
    }
}
