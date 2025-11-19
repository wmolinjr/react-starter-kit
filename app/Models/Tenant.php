<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasInternalKeys;
use Stancl\Tenancy\Database\Concerns\TenantRun;

class Tenant extends Model implements TenantWithDatabase
{
    use HasFactory, Billable, HasDatabase, HasInternalKeys, TenantRun;

    protected $fillable = [
        'name',
        'slug',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
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
     * Tenant tem muitos usuários (N:N via pivot)
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'permissions', 'invited_at', 'invitation_token', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Owners do tenant
     */
    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'owner');
    }

    /**
     * Admins do tenant
     */
    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'admin');
    }

    /**
     * Members do tenant
     */
    public function members(): BelongsToMany
    {
        return $this->users()->whereIn('role', ['owner', 'admin', 'member']);
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
     * Verificar se tenant tem feature habilitada
     */
    public function hasFeature(string $feature): bool
    {
        return (bool) $this->getSetting("features.{$feature}", false);
    }

    /**
     * Verificar limite
     */
    public function hasReachedLimit(string $limit, int $current): bool
    {
        $max = $this->getSetting("limits.{$limit}");

        if ($max === null) {
            return false; // Sem limite
        }

        return $current >= $max;
    }

    /**
     * Verificar se atingiu limite de usuários
     */
    public function hasReachedUserLimit(): bool
    {
        $maxUsers = $this->max_users;

        if ($maxUsers === null) {
            return false; // Sem limite
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
