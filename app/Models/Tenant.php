<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;
use Laravel\Pennant\Concerns\HasFeatures;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasInternalKeys;
use Stancl\Tenancy\Database\Concerns\TenantRun;

class Tenant extends Model implements TenantWithDatabase
{
    use HasFactory, Billable, HasDatabase, HasFeatures, HasInternalKeys, TenantRun;

    protected $fillable = [
        'name',
        'slug',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'plan_features_override' => 'array',
        'plan_limits_override' => 'array',
        'current_usage' => 'array',
        'plan_enabled_permissions' => 'array',
        'trial_ends_at' => 'datetime',
    ];

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
    public function getTenantKey()
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

    /**
     * Tenant tem muitos usuários (N:N via pivot)
     *
     * NOTA: Roles agora gerenciados via Spatie Permission (não mais pivot)
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('invited_at', 'invitation_token', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get users with a specific role in this tenant
     *
     * @param string $roleName Role name (owner, admin, member, guest)
     * @return \Illuminate\Support\Collection<User>
     */
    public function getUsersByRole(string $roleName): \Illuminate\Support\Collection
    {
        // Initialize tenant context
        tenancy()->initialize($this);
        setPermissionsTeamId($this->id);

        // Get all active users (joined_at not null)
        $users = $this->users()
            ->whereNotNull('tenant_user.joined_at')
            ->get()
            ->filter(function ($user) use ($roleName) {
                // Filter by role using Spatie Permission
                return $user->hasRole($roleName);
            });

        // End tenant context if it wasn't active before
        tenancy()->end();

        return $users;
    }

    /**
     * Owners do tenant (users with 'owner' role)
     *
     * @return \Illuminate\Support\Collection<User>
     */
    public function owners(): \Illuminate\Support\Collection
    {
        return $this->getUsersByRole('owner');
    }

    /**
     * Admins do tenant (users with 'admin' role)
     *
     * @return \Illuminate\Support\Collection<User>
     */
    public function admins(): \Illuminate\Support\Collection
    {
        return $this->getUsersByRole('admin');
    }

    /**
     * Members do tenant (users with 'member' role)
     *
     * @return \Illuminate\Support\Collection<User>
     */
    public function members(): \Illuminate\Support\Collection
    {
        return $this->getUsersByRole('member');
    }

    /**
     * All active members (joined_at not null) regardless of role
     *
     * @return \Illuminate\Database\Eloquent\Collection<User>
     */
    public function activeMembers(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->users()->whereNotNull('tenant_user.joined_at')->get();
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
     */
    public function regeneratePlanPermissions(): array
    {
        if (!$this->plan) {
            return [];
        }

        $permissions = $this->plan->getAllEnabledPermissions();
        $expanded = $this->plan->expandPermissions($permissions);

        // Cache it
        $this->update(['plan_enabled_permissions' => $expanded]);

        return $expanded;
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
     */
    public function hasReachedUserLimit(): bool
    {
        $maxUsers = $this->getLimit('users');

        if ($maxUsers === -1) {
            return false; // Unlimited
        }

        $currentUsers = $this->users()
            ->whereNotNull('tenant_user.joined_at')
            ->count();

        return $currentUsers >= $maxUsers;
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
