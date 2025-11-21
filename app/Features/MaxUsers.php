<?php

namespace App\Features;

use App\Models\Tenant;

class MaxUsers
{
    /**
     * Resolve the feature's initial value.
     *
     * Returns the user limit as an integer (rich value)
     */
    public function resolve(Tenant $tenant): int
    {
        return $tenant->getLimit('users');
    }
}
