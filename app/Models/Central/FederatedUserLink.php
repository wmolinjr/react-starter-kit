<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * FederatedUserLink - Links a FederatedUser to a specific tenant's local user.
 *
 * Each link represents the existence of the federated user in a tenant's database.
 * The tenant_user_id references the user's ID in that tenant's database.
 *
 * @property string $id
 * @property string $federated_user_id
 * @property string $tenant_id
 * @property string $tenant_user_id
 * @property string $sync_status
 * @property \Carbon\Carbon|null $last_synced_at
 * @property int $sync_attempts
 * @property string|null $last_sync_error
 * @property array|null $metadata
 */
class FederatedUserLink extends Model
{
    use CentralConnection, HasUuids;

    protected $fillable = [
        'federated_user_id',
        'tenant_id',
        'tenant_user_id',
        'sync_status',
        'last_synced_at',
        'sync_attempts',
        'last_sync_error',
        'metadata',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'sync_attempts' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Sync status constants.
     */
    public const STATUS_SYNCED = 'synced';
    public const STATUS_PENDING_SYNC = 'pending_sync';
    public const STATUS_SYNC_FAILED = 'sync_failed';
    public const STATUS_CONFLICT = 'conflict';
    public const STATUS_DISABLED = 'disabled';

    /**
     * Created via constants.
     */
    public const CREATED_VIA_AUTO_SYNC = 'auto_sync';
    public const CREATED_VIA_MANUAL_LINK = 'manual_link';
    public const CREATED_VIA_IMPORT = 'import';
    public const CREATED_VIA_LOGIN = 'login';
    public const CREATED_VIA_BULK_SYNC = 'bulk_sync';

    /**
     * Federated user relationship.
     */
    public function federatedUser(): BelongsTo
    {
        return $this->belongsTo(FederatedUser::class);
    }

    /**
     * Tenant relationship.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get metadata value.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Update metadata value.
     */
    public function setMetadata(string $key, mixed $value): bool
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);

        return $this->update(['metadata' => $metadata]);
    }

    /**
     * How this link was created.
     */
    public function getCreatedVia(): string
    {
        return $this->getMetadata('created_via', self::CREATED_VIA_AUTO_SYNC);
    }

    /**
     * Mark as synced.
     */
    public function markAsSynced(): bool
    {
        return $this->update([
            'sync_status' => self::STATUS_SYNCED,
            'last_synced_at' => now(),
            'sync_attempts' => 0,
            'last_sync_error' => null,
        ]);
    }

    /**
     * Mark as pending sync.
     */
    public function markAsPendingSync(): bool
    {
        return $this->update([
            'sync_status' => self::STATUS_PENDING_SYNC,
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $error): bool
    {
        return $this->update([
            'sync_status' => self::STATUS_SYNC_FAILED,
            'sync_attempts' => $this->sync_attempts + 1,
            'last_sync_error' => $error,
        ]);
    }

    /**
     * Mark as conflict.
     */
    public function markAsConflict(): bool
    {
        return $this->update([
            'sync_status' => self::STATUS_CONFLICT,
        ]);
    }

    /**
     * Disable sync for this link.
     */
    public function disable(): bool
    {
        return $this->update([
            'sync_status' => self::STATUS_DISABLED,
        ]);
    }

    /**
     * Enable sync for this link.
     */
    public function enable(): bool
    {
        return $this->update([
            'sync_status' => self::STATUS_PENDING_SYNC,
        ]);
    }

    /**
     * Check if should retry sync.
     */
    public function shouldRetry(int $maxAttempts = 3): bool
    {
        return $this->sync_status === self::STATUS_SYNC_FAILED
            && $this->sync_attempts < $maxAttempts;
    }

    /**
     * Increment sync attempts counter.
     */
    public function incrementSyncAttempts(): bool
    {
        return $this->update([
            'sync_attempts' => $this->sync_attempts + 1,
        ]);
    }

    /**
     * Check if synced.
     */
    public function isSynced(): bool
    {
        return $this->sync_status === self::STATUS_SYNCED;
    }

    /**
     * Check if disabled.
     */
    public function isDisabled(): bool
    {
        return $this->sync_status === self::STATUS_DISABLED;
    }

    /**
     * Scope for synced links.
     */
    public function scopeSynced($query)
    {
        return $query->where('sync_status', self::STATUS_SYNCED);
    }

    /**
     * Scope for pending links.
     */
    public function scopePending($query)
    {
        return $query->where('sync_status', self::STATUS_PENDING_SYNC);
    }

    /**
     * Scope for failed links.
     */
    public function scopeFailed($query)
    {
        return $query->where('sync_status', self::STATUS_SYNC_FAILED);
    }

    /**
     * Scope for conflict links.
     */
    public function scopeConflict($query)
    {
        return $query->where('sync_status', self::STATUS_CONFLICT);
    }

    /**
     * Scope for links of a specific tenant.
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Find by tenant user ID.
     */
    public static function findByTenantUser(string $tenantId, string $userId): ?self
    {
        return static::where('tenant_id', $tenantId)
            ->where('tenant_user_id', $userId)
            ->first();
    }
}
