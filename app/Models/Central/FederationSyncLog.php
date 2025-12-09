<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * FederationSyncLog - Audit trail for federation sync operations.
 *
 * Records all synchronization events for compliance and debugging.
 *
 * @property string $id
 * @property string $federation_group_id
 * @property string|null $federated_user_id
 * @property string $operation
 * @property string|null $source_tenant_id
 * @property string|null $target_tenant_id
 * @property array|null $old_data
 * @property array|null $new_data
 * @property string $status
 * @property string|null $error_message
 * @property string|null $actor_id
 * @property string|null $actor_type
 */
class FederationSyncLog extends Model
{
    use CentralConnection, HasUuids;

    protected $fillable = [
        'federation_group_id',
        'federated_user_id',
        'operation',
        'source_tenant_id',
        'target_tenant_id',
        'old_data',
        'new_data',
        'status',
        'error_message',
        'actor_id',
        'actor_type',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    /**
     * Operation constants.
     */
    public const OP_USER_CREATED = 'user_created';
    public const OP_USER_UPDATED = 'user_updated';
    public const OP_USER_DELETED = 'user_deleted';
    public const OP_PASSWORD_CHANGED = 'password_changed';
    public const OP_TWO_FACTOR_CHANGED = 'two_factor_changed';
    public const OP_TENANT_JOINED = 'tenant_joined';
    public const OP_TENANT_LEFT = 'tenant_left';
    public const OP_CONFLICT_DETECTED = 'conflict_detected';
    public const OP_CONFLICT_RESOLVED = 'conflict_resolved';
    public const OP_SYNC_FAILED = 'sync_failed';
    public const OP_SYNC_RETRY = 'sync_retry';
    public const OP_MASTER_CHANGED = 'master_changed';

    /**
     * Status constants.
     */
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PENDING = 'pending';

    /**
     * Actor type constants.
     */
    public const ACTOR_CENTRAL_USER = 'central_user';
    public const ACTOR_TENANT_USER = 'tenant_user';
    public const ACTOR_SYSTEM = 'system';

    /**
     * Federation group relationship.
     */
    public function federationGroup(): BelongsTo
    {
        return $this->belongsTo(FederationGroup::class);
    }

    /**
     * Federated user relationship.
     */
    public function federatedUser(): BelongsTo
    {
        return $this->belongsTo(FederatedUser::class);
    }

    /**
     * Source tenant relationship.
     */
    public function sourceTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'source_tenant_id');
    }

    /**
     * Target tenant relationship.
     */
    public function targetTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'target_tenant_id');
    }

    /**
     * Create a success log entry.
     */
    public static function logSuccess(
        string $groupId,
        string $operation,
        ?string $federatedUserId = null,
        ?string $sourceTenantId = null,
        ?string $targetTenantId = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?string $actorId = null,
        ?string $actorType = null
    ): self {
        return static::create([
            'federation_group_id' => $groupId,
            'federated_user_id' => $federatedUserId,
            'operation' => $operation,
            'source_tenant_id' => $sourceTenantId,
            'target_tenant_id' => $targetTenantId,
            'old_data' => $oldData,
            'new_data' => $newData,
            'status' => self::STATUS_SUCCESS,
            'actor_id' => $actorId,
            'actor_type' => $actorType ?? self::ACTOR_SYSTEM,
        ]);
    }

    /**
     * Create a failure log entry.
     */
    public static function logFailure(
        string $groupId,
        string $operation,
        string $errorMessage,
        ?string $federatedUserId = null,
        ?string $sourceTenantId = null,
        ?string $targetTenantId = null,
        ?array $oldData = null,
        ?array $newData = null
    ): self {
        return static::create([
            'federation_group_id' => $groupId,
            'federated_user_id' => $federatedUserId,
            'operation' => $operation,
            'source_tenant_id' => $sourceTenantId,
            'target_tenant_id' => $targetTenantId,
            'old_data' => $oldData,
            'new_data' => $newData,
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'actor_type' => self::ACTOR_SYSTEM,
        ]);
    }

    /**
     * Scope for successful operations.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope for failed operations.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for a specific operation type.
     */
    public function scopeOfOperation($query, string $operation)
    {
        return $query->where('operation', $operation);
    }

    /**
     * Scope for operations involving a specific user.
     */
    public function scopeForUser($query, string $federatedUserId)
    {
        return $query->where('federated_user_id', $federatedUserId);
    }

    /**
     * Scope for recent logs.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
