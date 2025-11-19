<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * User pertence a muitos tenants (N:N via pivot)
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->withPivot('role', 'permissions', 'invited_at', 'invitation_token', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Obter tenant atual do contexto
     */
    public function currentTenant(): ?Tenant
    {
        if (!tenancy()->initialized) {
            return null;
        }

        return Tenant::find(tenant('id'));
    }

    /**
     * Role do usuário no tenant atual
     */
    public function currentTenantRole(): ?string
    {
        if (!tenancy()->initialized) {
            return null;
        }

        return $this->tenants()
            ->where('tenant_id', tenant('id'))
            ->first()
            ?->pivot
            ->role;
    }

    /**
     * Get user's role on a specific tenant
     */
    public function roleOn(Tenant|int $tenant): ?string
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->tenants()
            ->where('tenant_id', $tenantId)
            ->first()
            ?->pivot
            ->role;
    }

    /**
     * Verificar se user tem role específico no tenant atual
     */
    public function hasRole(string $role): bool
    {
        return $this->currentTenantRole() === $role;
    }

    /**
     * Verificar se user tem um dos roles no tenant atual
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->currentTenantRole(), $roles);
    }

    /**
     * Verificar se user é owner do tenant atual
     */
    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    /**
     * Verificar se user é admin ou owner do tenant atual
     */
    public function isAdminOrOwner(): bool
    {
        return $this->hasAnyRole(['owner', 'admin']);
    }

    /**
     * Verificar se user tem permissão específica no tenant atual
     */
    public function hasPermissionInTenant(string $permission): bool
    {
        $role = $this->currentTenantRole();

        return match($role) {
            'owner', 'admin' => true,
            'member' => in_array($permission, ['read', 'create', 'update']),
            'guest' => in_array($permission, ['read']),
            default => false,
        };
    }

    /**
     * Verificar se user pertence ao tenant atual
     */
    public function belongsToCurrentTenant(): bool
    {
        if (!tenancy()->initialized) {
            return false;
        }

        return $this->tenants()
            ->where('tenant_id', tenant('id'))
            ->exists();
    }

    /**
     * Obter todos os tenants onde user é owner
     */
    public function ownedTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivot('role', 'owner');
    }

    /**
     * Switch para outro tenant
     */
    public function switchToTenant(Tenant $tenant): bool
    {
        if (!$this->tenants()->where('tenant_id', $tenant->id)->exists()) {
            return false;
        }

        tenancy()->initialize($tenant);

        return true;
    }
}
