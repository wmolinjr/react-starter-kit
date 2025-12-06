<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Custom PersonalAccessToken model for central context.
 *
 * CENTRAL DATABASE:
 * - Stored in central database (admin_personal_access_tokens table)
 * - Uses UUID for primary key (consistency with other models)
 * - Used by Central\User (admins) for API authentication
 *
 * @see https://v4.tenancyforlaravel.com/integrations/sanctum/
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use CentralConnection;
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'admin_personal_access_tokens';

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
