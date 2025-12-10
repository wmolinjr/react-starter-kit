<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * FederationGroupTenant - Pivot model for FederationGroup <-> Tenant relationship.
 *
 * Using a custom Pivot model to support UUID primary keys and proper casting.
 *
 * @property string $id
 * @property string $federation_group_id
 * @property string $tenant_id
 * @property bool $sync_enabled
 * @property \Carbon\Carbon $joined_at
 * @property \Carbon\Carbon|null $left_at
 * @property array|null $settings
 */
class FederationGroupTenant extends Pivot
{
    use CentralConnection, HasUuids;

    protected $table = 'federation_group_tenants';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'federation_group_id',
        'tenant_id',
        'sync_enabled',
        'joined_at',
        'left_at',
        'settings',
    ];

    protected $casts = [
        'sync_enabled' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * Federation group relationship.
     */
    public function federationGroup(): BelongsTo
    {
        return $this->belongsTo(FederationGroup::class);
    }

    /**
     * Tenant relationship.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Get default role for new users.
     */
    public function getDefaultRole(): string
    {
        return $this->getSetting('default_role', 'member');
    }

    /**
     * Check if auto-accept is enabled.
     */
    public function shouldAutoAcceptUsers(): bool
    {
        return $this->getSetting('auto_accept_users', true);
    }

    /**
     * Check if this tenant is active in the group.
     */
    public function isActive(): bool
    {
        return $this->sync_enabled && $this->left_at === null;
    }

    /**
     * Leave the federation group.
     */
    public function leave(): bool
    {
        return $this->update([
            'left_at' => now(),
            'sync_enabled' => false,
        ]);
    }

    /**
     * Rejoin the federation group.
     */
    public function rejoin(): bool
    {
        return $this->update([
            'left_at' => null,
            'sync_enabled' => true,
            'joined_at' => now(),
        ]);
    }

    /**
     * Enable sync for this tenant.
     */
    public function enableSync(): bool
    {
        return $this->update([
            'sync_enabled' => true,
        ]);
    }

    /**
     * Disable sync for this tenant.
     */
    public function disableSync(): bool
    {
        return $this->update([
            'sync_enabled' => false,
        ]);
    }

    /**
     * Toggle sync for this tenant.
     */
    public function toggleSync(): bool
    {
        return $this->update([
            'sync_enabled' => ! $this->sync_enabled,
        ]);
    }
}
