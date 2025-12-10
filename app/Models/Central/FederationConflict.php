<?php

namespace App\Models\Central;

use App\Enums\FederationConflictStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * FederationConflict - Tracks unresolved data conflicts between tenants.
 *
 * When sync_strategy is 'manual_review', conflicts are stored here
 * for manual resolution by administrators.
 *
 * @property string $id
 * @property string $federated_user_id
 * @property string $field
 * @property array $values
 * @property string $status
 * @property string|null $resolved_by
 * @property string|null $resolution
 * @property string|null $resolution_notes
 * @property \Carbon\Carbon|null $resolved_at
 */
class FederationConflict extends Model
{
    use CentralConnection, HasUuids, LogsActivity;

    protected $fillable = [
        'federated_user_id',
        'field',
        'values',
        'status',
        'resolved_by',
        'resolution',
        'resolution_notes',
        'resolved_at',
    ];

    protected $casts = [
        'status' => FederationConflictStatus::class,
        'values' => 'array',
        'resolved_at' => 'datetime',
    ];

    /**
     * Activity log configuration.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'resolution', 'resolved_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Federation conflict {$eventName}");
    }

    /**
     * Resolution constants.
     */
    public const RESOLUTION_MASTER_VALUE = 'master_value';

    public const RESOLUTION_MANUAL = 'manual';

    public const RESOLUTION_DISMISSED = 'dismissed';

    /**
     * Federated user relationship.
     */
    public function federatedUser(): BelongsTo
    {
        return $this->belongsTo(FederatedUser::class);
    }

    /**
     * Resolver relationship (central user who resolved).
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get conflict values for a specific tenant.
     */
    public function getValueForTenant(string $tenantId): mixed
    {
        return data_get($this->values, "{$tenantId}.value");
    }

    /**
     * Get when a tenant's value was updated.
     */
    public function getUpdatedAtForTenant(string $tenantId): ?string
    {
        return data_get($this->values, "{$tenantId}.updated_at");
    }

    /**
     * Get all tenant IDs involved in the conflict.
     */
    public function getInvolvedTenantIds(): array
    {
        return array_keys($this->values ?? []);
    }

    /**
     * Add a conflicting value from a tenant.
     */
    public function addConflictingValue(string $tenantId, mixed $value): bool
    {
        $values = $this->values ?? [];
        $values[$tenantId] = [
            'value' => $value,
            'updated_at' => now()->toIso8601String(),
        ];

        return $this->update(['values' => $values]);
    }

    /**
     * Resolve the conflict with a specific value.
     */
    public function resolve(mixed $resolvedValue, string $resolverId, string $resolution = self::RESOLUTION_MANUAL, ?string $notes = null): bool
    {
        return $this->update([
            'status' => FederationConflictStatus::RESOLVED,
            'resolved_by' => $resolverId,
            'resolution' => $resolution,
            'resolution_notes' => $notes,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Dismiss the conflict without resolving.
     */
    public function dismiss(string $resolverId, ?string $notes = null): bool
    {
        return $this->update([
            'status' => FederationConflictStatus::DISMISSED,
            'resolved_by' => $resolverId,
            'resolution' => self::RESOLUTION_DISMISSED,
            'resolution_notes' => $notes,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Check if pending.
     */
    public function isPending(): bool
    {
        return $this->status === FederationConflictStatus::PENDING;
    }

    /**
     * Check if resolved.
     */
    public function isResolved(): bool
    {
        return $this->status === FederationConflictStatus::RESOLVED;
    }

    /**
     * Scope for pending conflicts.
     */
    public function scopePending($query)
    {
        return $query->where('status', FederationConflictStatus::PENDING);
    }

    /**
     * Scope for resolved conflicts.
     */
    public function scopeResolved($query)
    {
        return $query->where('status', FederationConflictStatus::RESOLVED);
    }

    /**
     * Scope for conflicts of a specific field.
     */
    public function scopeForField($query, string $field)
    {
        return $query->where('field', $field);
    }

    /**
     * Scope for conflicts of a specific user.
     */
    public function scopeForUser($query, string $federatedUserId)
    {
        return $query->where('federated_user_id', $federatedUserId);
    }

    /**
     * Find or create a pending conflict for a user and field.
     */
    public static function findOrCreatePending(string $federatedUserId, string $field): self
    {
        return static::firstOrCreate(
            [
                'federated_user_id' => $federatedUserId,
                'field' => $field,
                'status' => FederationConflictStatus::PENDING,
            ],
            [
                'values' => [],
            ]
        );
    }
}
