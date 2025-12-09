<?php

namespace App\Models\Central;

use App\Enums\FederationSyncStrategy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * FederationGroup - Groups of tenants that synchronize users.
 *
 * USER SYNC FEDERATION:
 * - Allows multiple tenants to share user credentials
 * - Master tenant is the "source of truth" for conflict resolution
 * - Synced: email, password, name, avatar, 2FA settings
 * - NOT synced: roles, permissions (remain local per tenant)
 *
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $master_tenant_id
 * @property string $sync_strategy
 * @property array|null $settings
 * @property bool $is_active
 */
class FederationGroup extends Model
{
    use CentralConnection, HasUuids, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'master_tenant_id',
        'sync_strategy',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'sync_strategy' => FederationSyncStrategy::class,
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Activity log configuration.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'master_tenant_id', 'sync_strategy', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Federation group {$eventName}");
    }

    /**
     * Default synced fields.
     */
    public const DEFAULT_SYNC_FIELDS = [
        'name',
        'email',
        'password',
        'locale',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * Master tenant relationship.
     */
    public function masterTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'master_tenant_id');
    }

    /**
     * All tenants in this group (via pivot).
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'federation_group_tenants')
            ->using(FederationGroupTenant::class)
            ->withPivot(['id', 'sync_enabled', 'joined_at', 'left_at', 'settings'])
            ->withTimestamps();
    }

    /**
     * Active tenants (joined and not left).
     */
    public function activeTenants(): BelongsToMany
    {
        return $this->tenants()
            ->wherePivot('sync_enabled', true)
            ->wherePivotNull('left_at');
    }

    /**
     * Federated users in this group.
     */
    public function federatedUsers(): HasMany
    {
        return $this->hasMany(FederatedUser::class);
    }

    /**
     * Active federated users.
     */
    public function activeFederatedUsers(): HasMany
    {
        return $this->federatedUsers()->where('status', 'active');
    }

    /**
     * Get setting value with default.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Get fields that should be synced.
     */
    public function getSyncFields(): array
    {
        return $this->getSetting('sync_fields', self::DEFAULT_SYNC_FIELDS);
    }

    /**
     * Check if auto-create on login is enabled.
     */
    public function shouldAutoCreateOnLogin(): bool
    {
        return $this->getSetting('auto_create_on_login', true);
    }

    /**
     * Check if new users should be automatically federated.
     */
    public function shouldAutoFederateNewUsers(): bool
    {
        return $this->getSetting('auto_federate_new_users', false);
    }

    /**
     * Check if a tenant is the master.
     */
    public function isMaster(Tenant|string $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        return $this->master_tenant_id === $tenantId;
    }

    /**
     * Check if a tenant is a member of this group.
     */
    public function hasTenant(Tenant|string $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        return $this->activeTenants()->where('tenants.id', $tenantId)->exists();
    }

    /**
     * Get other tenants (excluding the given one).
     */
    public function getOtherTenants(Tenant|string $excludeTenant): \Illuminate\Database\Eloquent\Collection
    {
        $tenantId = $excludeTenant instanceof Tenant ? $excludeTenant->id : $excludeTenant;
        return $this->activeTenants()->where('tenants.id', '!=', $tenantId)->get();
    }

    /**
     * Scope for active groups.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for groups with master_wins strategy.
     */
    public function scopeMasterWins($query)
    {
        return $query->where('sync_strategy', FederationSyncStrategy::MASTER_WINS);
    }
}
