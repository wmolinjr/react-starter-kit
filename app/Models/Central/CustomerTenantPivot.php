<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Stancl\Tenancy\ResourceSyncing\TriggerSyncingEvents;

/**
 * Custom Pivot for Customer-Tenant relationship.
 *
 * Uses UUID as primary key for consistency with the rest of the application.
 * Includes TriggerSyncingEvents for Resource Syncing support.
 */
class CustomerTenantPivot extends Pivot
{
    use HasUuids;
    use TriggerSyncingEvents;

    /**
     * The table associated with the model.
     */
    protected $table = 'customer_tenants';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The primary key type.
     */
    protected $keyType = 'string';
}
