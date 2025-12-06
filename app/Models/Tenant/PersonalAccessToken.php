<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Custom PersonalAccessToken model for tenant context.
 *
 * TENANT-ONLY ARCHITECTURE (Option C):
 * - Stored in tenant database (personal_access_tokens table)
 * - Uses UUID for primary key (consistency with other models)
 * - Isolated per tenant (database-level isolation)
 *
 * Required by Tenancy v4 for proper Sanctum integration.
 *
 * @see https://v4.tenancyforlaravel.com/integrations/sanctum/
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
}
