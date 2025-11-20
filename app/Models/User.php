<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Attributes that should never be mass assignable
     * SECURITY: Prevents privilege escalation attacks
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'is_super_admin', // CRITICAL: Prevent users from making themselves super admins
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
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
     *
     * NOTA: roles e permissions agora gerenciados via Spatie Permission
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->withPivot('invited_at', 'invitation_token', 'joined_at')
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
     *
     * NOTA: Usa Spatie Permission
     */
    public function currentTenantRole(): ?string
    {
        if (!tenancy()->initialized) {
            return null;
        }

        // Obter primeira role do usuário no tenant context atual
        return $this->roles()->first()?->name;
    }

    /**
     * Get user's role on a specific tenant
     *
     * NOTA: Usa Spatie Permission - requer tenant context
     */
    public function roleOn(Tenant|int $tenant): ?string
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        // Verificar se user pertence ao tenant
        if (!$this->tenants()->where('tenant_id', $tenantId)->exists()) {
            return null;
        }

        // Salvar contexto atual
        $currentTenantId = tenancy()->initialized ? tenant('id') : null;

        // Inicializar tenant context para obter role
        $tenantModel = $tenant instanceof Tenant ? $tenant : Tenant::find($tenantId);
        if (!$tenantModel) {
            return null;
        }

        tenancy()->initialize($tenantModel);

        // Obter primeira role do usuário (normalmente só terá uma)
        $roleName = $this->roles()->first()?->name;

        // Restaurar contexto original
        if ($currentTenantId) {
            tenancy()->initialize(Tenant::find($currentTenantId));
        } else {
            tenancy()->end();
        }

        return $roleName;
    }

    /**
     * Verificar se user é owner do tenant atual
     *
     * NOTA: Usa Spatie Permission via trait HasRoles
     */
    public function isOwner(): bool
    {
        return tenancy()->initialized && $this->hasRole('owner');
    }

    /**
     * Verificar se user é admin ou owner do tenant atual
     *
     * NOTA: Usa Spatie Permission via trait HasRoles
     */
    public function isAdminOrOwner(): bool
    {
        return tenancy()->initialized && $this->hasAnyRole(['owner', 'admin']);
    }

    /**
     * Verificar se user tem permissão específica no tenant atual
     *
     * NOTA: Usa Spatie Permission via trait HasRoles
     * Use hasPermissionTo() diretamente para checagens de permissions
     */
    public function hasPermissionInTenant(string $permission): bool
    {
        if (!tenancy()->initialized) {
            return false;
        }

        return $this->hasPermissionTo($permission);
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

    /**
     * Activity Log Options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
