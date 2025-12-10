<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Smart PersonalAccessToken model for multi-database tenancy.
 *
 * MULTI-DATABASE TENANCY:
 * - In tenant context: uses 'personal_access_tokens' table in tenant database
 * - In central context: uses 'personal_access_tokens' table in central database
 *
 * This model dynamically resolves the correct connection based on tenancy state.
 * Both databases use the same table name for consistency.
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
     * Both contexts use 'personal_access_tokens' in their respective databases.
     */
    public function getTable(): string
    {
        return 'personal_access_tokens';
    }
}
