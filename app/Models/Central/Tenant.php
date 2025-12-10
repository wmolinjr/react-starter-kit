<?php

namespace App\Models\Central;

use App\Enums\AddonType;
use App\Enums\PlanLimit;
use App\Enums\TenantConfigKey;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;
use Laravel\Pennant\Concerns\HasFeatures;
use Stancl\Tenancy\Database\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasInternalKeys;
use Stancl\Tenancy\Database\Concerns\TenantRun;
use Stancl\Tenancy\Events;
use Stancl\VirtualColumn\VirtualColumn;

/**
 * Tenant Model
 *
 * Uses UUID for primary key (best practice for multi-tenant systems):
 * - Security: Doesn't expose tenant count or creation order
 * - Distributed: Works across multiple database servers
 * - Database naming: Clean names like `tenant_abc123` instead of `tenant_1`
 */
class Tenant extends Model implements TenantWithDatabase
{
    use CentralConnection, HasFactory, HasUuids, Billable, HasDatabase, HasFeatures, HasInternalKeys, TenantRun, VirtualColumn;

    /**
     * Bootstrap the model.
     *
     * In tests with TENANCY_TESTING_DATABASE, all tenants use the same
     * testing_tenant database instead of dynamic tenant_{id} databases.
     *
     * PARALLEL TESTING SUPPORT:
     * When TEST_TOKEN is set, uses testing_tenant_{token} for isolation.
     */
    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            // In tests, use fixed testing database for all tenants
            if ($testingDb = env('TENANCY_TESTING_DATABASE')) {
                // Support parallel testing: append TEST_TOKEN to database name
                $testToken = env('TEST_TOKEN');
                if ($testToken) {
                    $testingDb = "testing_tenant_{$testToken}";
                }
                $tenant->setInternal('db_name', $testingDb);
            }
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Database\Factories\TenantFactory
    {
        return \Database\Factories\TenantFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'data', // Stancl internal keys (tenancy_db_name, etc.)
        'settings',
        'plan_id',
        'plan_features_override',
        'plan_limits_override',
        'plan_enabled_permissions',
        'current_usage',
        'trial_ends_at',
        // Customer billing (new architecture)
        'customer_id',
        'payment_method_id',
    ];

    protected $casts = [
        'data' => 'array', // Stancl internal keys
        'settings' => 'array',
        'plan_features_override' => 'array',
        'plan_limits_override' => 'array',
        'current_usage' => 'array',
        'plan_enabled_permissions' => 'array',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Dispatch Stancl Tenancy events for tenant lifecycle.
     * This is required for the job pipeline (CreateDatabase, MigrateDatabase, etc.)
     */
    protected $dispatchesEvents = [
        'saving' => Events\SavingTenant::class,
        'saved' => Events\TenantSaved::class,
        'creating' => Events\CreatingTenant::class,
        'created' => Events\TenantCreated::class,
        'updating' => Events\UpdatingTenant::class,
        'updated' => Events\TenantUpdated::class,
        'deleting' => Events\DeletingTenant::class,
        'deleted' => Events\TenantDeleted::class,
    ];

    /**
     * Columns that actually exist in the database.
     * Other attributes are stored in the 'data' JSON column via VirtualColumn.
     * This includes Stancl's internal keys like 'tenancy_db_name'.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'customer_id',
            'payment_method_id',
            'name',
            'slug',
            'data',
            'settings',
            'plan_id',
            'plan_features_override',
            'plan_limits_override',
            'plan_enabled_permissions',
            'current_usage',
            'stripe_id',
            'pm_type',
            'pm_last_four',
            'trial_ends_at',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Get the name of the key used for identifying the tenant.
     * Required by Stancl\Tenancy\Contracts\Tenant
     */
    public function getTenantKeyName(): string
    {
        return 'id';
    }

    /**
     * Get the value of the key used for identifying the tenant.
     * Required by Stancl\Tenancy\Contracts\Tenant
     */
    public function getTenantKey(): string|int
    {
        return $this->getAttribute($this->getTenantKeyName());
    }

    /**
     * Tenant tem muitos domínios
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Tenant belongs to a Plan
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // =========================================================================
    // Customer Billing Relationship (New Architecture)
    // =========================================================================

    /**
     * The customer who pays for this tenant.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the billable entity for this tenant.
     * Delegates billing to the customer.
     */
    public function getBillable(): ?Customer
    {
        return $this->customer;
    }

    /**
     * Check if this tenant has an active subscription via customer.
     */
    public function hasActiveSubscriptionViaCustomer(): bool
    {
        if (!$this->customer) {
            return false;
        }

        return $this->customer->subscriptionForTenant($this)?->active() ?? false;
    }

    /**
     * Get the active subscription for this tenant via customer.
     */
    public function getSubscriptionViaCustomer(): ?\Laravel\Cashier\Subscription
    {
        return $this->customer?->subscriptionForTenant($this);
    }

    /**
     * Get payment method (tenant override or customer default).
     */
    public function getPaymentMethod(): ?object
    {
        return $this->customer?->paymentMethodForTenant($this);
    }

    /**
     * Federation groups this tenant belongs to.
     */
    public function federationGroups(): BelongsToMany
    {
        return $this->belongsToMany(FederationGroup::class, 'federation_group_tenants')
            ->withPivot(['id', 'sync_enabled', 'joined_at', 'left_at', 'settings'])
            ->withTimestamps();
    }

    /**
     * Get users from tenant database.
     *
     * OPTION C: TENANT-ONLY USERS
     * - Users exist ONLY in tenant databases
     * - No pivot table (tenant_user) in central database
     * - Must use tenancy()->run() to query tenant database
     *
     * @return \Illuminate\Database\Eloquent\Collection<TenantUser>
     */
    public function getUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->run(function () {
            return TenantUser::with('roles')->get();
        });
    }

    /**
     * Get users with a specific role in this tenant
     *
     * OPTION C: TENANT-ONLY USERS
     * - Queries users directly from tenant database
     * - Uses Spatie Permission for role filtering
     *
     * @param string $roleName Role name (owner, admin, member, guest)
     * @return \Illuminate\Support\Collection<TenantUser>
     */
    public function getUsersByRole(string $roleName): \Illuminate\Support\Collection
    {
        return $this->run(function () use ($roleName) {
            return TenantUser::role($roleName)->get();
        });
    }

    /**
     * Owners do tenant (users with 'owner' role)
     *
     * @return \Illuminate\Support\Collection<TenantUser>
     */
    public function owners(): \Illuminate\Support\Collection
    {
        return $this->getUsersByRole('owner');
    }

    /**
     * Admins do tenant (users with 'admin' role)
     *
     * @return \Illuminate\Support\Collection<TenantUser>
     */
    public function admins(): \Illuminate\Support\Collection
    {
        return $this->getUsersByRole('admin');
    }

    /**
     * Members do tenant (users with 'member' role)
     *
     * @return \Illuminate\Support\Collection<TenantUser>
     */
    public function members(): \Illuminate\Support\Collection
    {
        return $this->getUsersByRole('member');
    }

    /**
     * All active members (not soft deleted) regardless of role
     *
     * @return \Illuminate\Database\Eloquent\Collection<TenantUser>
     */
    public function activeMembers(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getUsers();
    }

    /**
     * Count users in this tenant
     *
     * @return int
     */
    public function getUserCount(): int
    {
        return $this->run(function () {
            return TenantUser::count();
        });
    }

    /**
     * Domínio primário do tenant
     */
    public function primaryDomain()
    {
        return $this->domains()->where('is_primary', true)->first();
    }

    /**
     * URL do tenant
     */
    public function url(): string
    {
        $domain = $this->primaryDomain();

        if (!$domain) {
            return config('app.url');
        }

        $protocol = config('app.env') === 'local' ? 'http://' : 'https://';

        return $protocol . $domain->domain;
    }

    /**
     * Obter setting específico
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Atualizar setting
     */
    public function updateSetting(string $key, mixed $value): bool
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);

        return $this->update(['settings' => $settings]);
    }

    // ==========================================
    // Config Methods (TenantConfigBootstrapper)
    // ==========================================

    /**
     * Get a config setting with fallback to Laravel default.
     *
     * Config settings are stored in settings['config'] and automatically
     * override Laravel config via TenantConfigBootstrapper.
     */
    public function getConfig(TenantConfigKey $key): mixed
    {
        $value = $this->getSetting($key->settingsPath());

        if ($value === null) {
            // Fallback to current Laravel config (which may be the default)
            $configKeys = $key->configKeys();

            return config($configKeys[0], $key->defaultValue());
        }

        return $value;
    }

    /**
     * Update a config setting.
     *
     * This updates the tenant settings which will be applied via
     * TenantConfigBootstrapper on the next request.
     */
    public function updateConfig(TenantConfigKey $key, mixed $value): bool
    {
        return $this->updateSetting($key->settingsPath(), $value);
    }

    /**
     * Get all config settings as array.
     *
     * @return array<string, mixed>
     */
    public function getAllConfig(): array
    {
        $config = [];

        foreach (TenantConfigKey::cases() as $key) {
            $config[$key->value] = $this->getConfig($key);
        }

        return $config;
    }

    /**
     * Check if tenant has a specific feature
     * Used by Pennant features
     */
    public function hasFeature(string $feature): bool
    {
        // Override primeiro
        if (isset($this->plan_features_override[$feature])) {
            return $this->plan_features_override[$feature];
        }

        // Trial gets all features
        if ($this->isOnTrial()) {
            return true;
        }

        // Fallback para plan default
        return $this->plan?->hasFeature($feature) ?? false;
    }

    /**
     * Get limit for a resource
     */
    public function getLimit(string $resource): int
    {
        // Override primeiro
        if (isset($this->plan_limits_override[$resource])) {
            return $this->plan_limits_override[$resource];
        }

        // Trial gets higher limits
        if ($this->isOnTrial()) {
            return $this->plan?->getLimit($resource) ?? -1;
        }

        // Fallback para plan default
        return $this->plan?->getLimit($resource) ?? 0;
    }

    /**
     * Check if unlimited
     */
    public function isUnlimited(string $resource): bool
    {
        return $this->getLimit($resource) === -1;
    }

    /**
     * Get current usage
     */
    public function getCurrentUsage(string $resource): int
    {
        return $this->current_usage[$resource] ?? 0;
    }

    /**
     * Check if limit reached
     */
    public function hasReachedLimit(string $resource): bool
    {
        $limit = $this->getLimit($resource);

        if ($limit === -1) {
            return false;
        }

        $usage = $this->getCurrentUsage($resource);

        return $usage >= $limit;
    }

    /**
     * Increment usage
     */
    public function incrementUsage(string $resource, int $amount = 1): void
    {
        $currentUsage = $this->current_usage ?? [];
        $currentUsage[$resource] = ($currentUsage[$resource] ?? 0) + $amount;

        $this->update(['current_usage' => $currentUsage]);
    }

    /**
     * Decrement usage
     */
    public function decrementUsage(string $resource, int $amount = 1): void
    {
        $currentUsage = $this->current_usage ?? [];
        $currentUsage[$resource] = max(0, ($currentUsage[$resource] ?? 0) - $amount);

        $this->update(['current_usage' => $currentUsage]);
    }

    /**
     * ⭐ Get permissions enabled by current plan
     */
    public function getPlanEnabledPermissions(): array
    {
        // Return cached if available
        if ($this->plan_enabled_permissions) {
            return $this->plan_enabled_permissions;
        }

        // Regenerate and cache
        return $this->regeneratePlanPermissions();
    }

    /**
     * ⭐ Regenerate permissions based on current plan
     *
     * MULTI-DATABASE TENANCY:
     * - Must initialize tenancy to query permissions from tenant database
     * - Plan is in central DB, permissions are in tenant DB
     * - Tenant model save always goes to central DB
     */
    public function regeneratePlanPermissions(): array
    {
        if (!$this->plan) {
            return [];
        }

        // Check if tenant database exists (skip during initial creation)
        $database = $this->database()->getName();
        if (!$this->database()->manager()->databaseExists($database)) {
            \Illuminate\Support\Facades\Log::info("Skipping regeneratePlanPermissions - tenant database {$database} does not exist yet");
            return [];
        }

        // Initialize tenancy to access permissions in tenant database
        $wasInitialized = tenancy()->initialized;
        if (!$wasInitialized) {
            tenancy()->initialize($this);
        }

        try {
            $permissions = $this->plan->getAllEnabledPermissions();
            $expanded = $this->plan->expandPermissions($permissions);

            // Cache it (use saveQuietly to avoid triggering observer loop)
            // Tenant model always saves to central DB regardless of tenancy context
            $this->forceFill(['plan_enabled_permissions' => $expanded])->saveQuietly();

            return $expanded;
        } finally {
            if (!$wasInitialized) {
                tenancy()->end();
            }
        }
    }

    /**
     * ⭐ Check if permission is enabled by plan
     */
    public function isPlanPermissionEnabled(string $permission): bool
    {
        return in_array($permission, $this->getPlanEnabledPermissions());
    }

    /**
     * Trial methods
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function hasTrialEnded(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Verificar se atingiu limite de usuários
     *
     * OPTION C: TENANT-ONLY USERS
     * - Counts users directly from tenant database
     */
    public function hasReachedUserLimit(): bool
    {
        $maxUsers = $this->getLimit('users');

        if ($maxUsers === -1) {
            return false; // Unlimited
        }

        $currentUsers = $this->getUserCount();

        return $currentUsers >= $maxUsers;
    }

    // ==========================================
    // Add-on Relationships & Methods
    // ==========================================

    /**
     * All add-on subscriptions for this tenant
     */
    public function addons(): HasMany
    {
        return $this->hasMany(AddonSubscription::class);
    }

    /**
     * Active add-on subscriptions only
     */
    public function activeAddons(): HasMany
    {
        return $this->addons()->active();
    }

    /**
     * All add-on purchases
     */
    public function addonPurchases(): HasMany
    {
        return $this->hasMany(AddonPurchase::class);
    }

    /**
     * Get effective limits combining plan + active addons + overrides
     */
    public function getEffectiveLimits(): array
    {
        // Initialize all limits from PlanLimit enum
        $baseLimits = [];
        foreach (PlanLimit::cases() as $limit) {
            $baseLimits[$limit->value] = $this->plan?->getLimit($limit->value) ?? $limit->defaultValue();
        }

        // Add addon bonuses (limit_key stored in addon table)
        foreach ($this->activeAddons()->get() as $tenantAddon) {
            $addon = Addon::where('slug', $tenantAddon->addon_slug)->first();
            $limitKey = $addon?->limit_key;

            if ($limitKey && isset($baseLimits[$limitKey])) {
                $unitValue = $addon->unit_value ?? 0;
                $baseLimits[$limitKey] += ($unitValue * $tenantAddon->quantity);
            }
        }

        // Merge with manual overrides (highest priority)
        return array_merge($baseLimits, $this->plan_limits_override ?? []);
    }

    /**
     * Check if tenant has an active addon
     */
    public function hasActiveAddon(string $addonSlug): bool
    {
        return $this->activeAddons()
            ->where('addon_slug', $addonSlug)
            ->exists();
    }

    /**
     * Get total quantity of a specific addon
     */
    public function getAddonQuantity(string $addonSlug): int
    {
        return $this->activeAddons()
            ->where('addon_slug', $addonSlug)
            ->sum('quantity');
    }

    /**
     * Stripe customer name para Cashier
     */
    public function stripeCustomerName(): string
    {
        return $this->name;
    }

    /**
     * Stripe customer email para Cashier
     */
    public function stripeEmail(): string
    {
        $owner = $this->owners()->first();

        return $owner?->email ?? 'noreply@example.com';
    }
}
