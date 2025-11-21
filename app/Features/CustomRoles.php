<?php

namespace App\Features;

use App\Models\Tenant;

class CustomRoles
{
    /**
     * Resolve the feature's initial value.
     */
    public function resolve(Tenant $tenant): bool
    {
        return $tenant->hasFeature('customRoles');
    }
}
