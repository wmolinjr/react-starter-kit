<?php

namespace App\Models\Central;

use App\Enums\FederatedUserStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * FederatedUser - Central record of a user synchronized across tenants.
 *
 * This model stores the "source of truth" data for a user that exists
 * in multiple tenants within a federation group.
 *
 * @property string $id
 * @property string $federation_group_id
 * @property string $global_email
 * @property array $synced_data
 * @property string $master_tenant_id
 * @property string $master_tenant_user_id
 * @property \Carbon\Carbon|null $last_synced_at
 * @property string|null $last_sync_source
 * @property int $sync_version
 * @property FederatedUserStatus $status
 */
class FederatedUser extends Model
{
    use CentralConnection, HasUuids, SoftDeletes;

    protected $fillable = [
        'federation_group_id',
        'global_email',
        'synced_data',
        'master_tenant_id',
        'master_tenant_user_id',
        'last_synced_at',
        'last_sync_source',
        'sync_version',
        'status',
    ];

    protected $casts = [
        'synced_data' => 'array',
        'last_synced_at' => 'datetime',
        'sync_version' => 'integer',
        'status' => FederatedUserStatus::class,
    ];

    /**
     * Federation group relationship.
     */
    public function federationGroup(): BelongsTo
    {
        return $this->belongsTo(FederationGroup::class);
    }

    /**
     * Master tenant relationship.
     */
    public function masterTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'master_tenant_id');
    }

    /**
     * Links to tenant users.
     */
    public function links(): HasMany
    {
        return $this->hasMany(FederatedUserLink::class);
    }

    /**
     * Active links only (not disabled).
     */
    public function activeLinks(): HasMany
    {
        return $this->links()->where('sync_status', '!=', FederatedUserLink::STATUS_DISABLED);
    }

    /**
     * Synced links only.
     */
    public function syncedLinks(): HasMany
    {
        return $this->links()->where('sync_status', FederatedUserLink::STATUS_SYNCED);
    }

    /**
     * Links pending sync.
     */
    public function pendingLinks(): HasMany
    {
        return $this->links()->where('sync_status', 'pending_sync');
    }

    /**
     * Get synced data field.
     */
    public function getSyncedField(string $field, mixed $default = null): mixed
    {
        return data_get($this->synced_data, $field, $default);
    }

    /**
     * Update synced data field.
     */
    public function updateSyncedField(string $field, mixed $value): bool
    {
        $data = $this->synced_data ?? [];
        data_set($data, $field, $value);

        return $this->update([
            'synced_data' => $data,
            'sync_version' => $this->sync_version + 1,
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Update multiple synced data fields.
     */
    public function updateSyncedData(array $fields, ?string $sourceId = null): bool
    {
        $data = $this->synced_data ?? [];

        foreach ($fields as $field => $value) {
            data_set($data, $field, $value);
        }

        return $this->update([
            'synced_data' => $data,
            'sync_version' => $this->sync_version + 1,
            'last_synced_at' => now(),
            'last_sync_source' => $sourceId,
        ]);
    }

    /**
     * Get the user's name from synced data.
     */
    public function getName(): ?string
    {
        return $this->getSyncedField('name');
    }

    /**
     * Get the user's password hash from synced data.
     */
    public function getPasswordHash(): ?string
    {
        return $this->getSyncedField('password_hash');
    }

    /**
     * Check if 2FA is enabled.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return (bool) $this->getSyncedField('two_factor_enabled', false);
    }

    /**
     * Get link for a specific tenant.
     */
    public function getLinkForTenant(Tenant|string $tenant): ?FederatedUserLink
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        return $this->links()->where('tenant_id', $tenantId)->first();
    }

    /**
     * Check if user has a link to a specific tenant.
     */
    public function hasLinkToTenant(Tenant|string $tenant): bool
    {
        return $this->getLinkForTenant($tenant) !== null;
    }

    /**
     * Get all tenant IDs where this user exists.
     */
    public function getLinkedTenantIds(): array
    {
        return $this->links()->pluck('tenant_id')->toArray();
    }

    /**
     * Scope for active users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', FederatedUserStatus::ACTIVE);
    }

    /**
     * Scope for users in a specific group.
     */
    public function scopeInGroup($query, string $groupId)
    {
        return $query->where('federation_group_id', $groupId);
    }

    /**
     * Scope for users by email.
     */
    public function scopeByEmail($query, string $email)
    {
        return $query->where('global_email', strtolower($email));
    }

    /**
     * Find by email within a group.
     */
    public static function findByEmailInGroup(string $email, string $groupId): ?self
    {
        return static::byEmail($email)->inGroup($groupId)->first();
    }
}
