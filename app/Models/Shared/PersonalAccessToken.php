<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Smart PersonalAccessToken model for multi-database tenancy.
 *
 * MULTI-DATABASE TENANCY:
 * - In tenant context: uses 'personal_access_tokens' table in tenant database
 * - In central context: uses 'admin_personal_access_tokens' table in central database
 *
 * This model dynamically resolves the correct table and connection based on tenancy state.
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

    /**
     * Get the current connection name for the model.
     *
     * @return string|null
     */
    public function getConnectionName(): ?string
    {
        // In tenant context, use tenant connection (default)
        // In central context, use central connection
        if (tenancy()->initialized) {
            return null; // Use default (tenant) connection
        }

        return 'central';
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        // In tenant context, use personal_access_tokens
        // In central context, use admin_personal_access_tokens
        if (tenancy()->initialized) {
            return 'personal_access_tokens';
        }

        return 'admin_personal_access_tokens';
    }
}
