<?php

namespace App\Features;

use App\Models\Tenant;

class StorageLimit
{
    /**
     * Resolve the feature's initial value.
     *
     * Returns storage limit in MB
     */
    public function resolve(Tenant $tenant): int
    {
        return $tenant->getLimit('storage');
    }
}
