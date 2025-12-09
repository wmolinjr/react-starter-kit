<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central User - Administrative users in the central database.
 *
 * Used ONLY for:
 * - Super admins who manage tenants (role: super-admin)
 * - Central admins for admin panel access (role: central-admin)
 * - Technical support who accesses tenants via impersonation (role: support-admin)
 *
 * Uses Spatie Permission with guard 'central'.
 * Uses Sanctum for API token authentication.
 * NOT tenant users - tenant users are in App\Models\Tenant\User.
 *
 * @property string $id UUID primary key
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $locale
 * @property \Carbon\Carbon|null $email_verified_at
 * @property \Carbon\Carbon|null $two_factor_confirmed_at
 */
class User extends Authenticatable
{
    use CentralConnection;
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The guard name for Spatie Permission.
     * Must match the guard in config/auth.php that uses 'central_users' provider.
     */
    protected string $guard_name = 'central';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Database\Factories\CentralUserFactory
    {
        return \Database\Factories\CentralUserFactory::new();
    }

    // Uses Laravel convention: 'users' table in central database
    // In production: Central and tenant have separate databases, no conflict
    // In testing: TestCase runs central-only or tenant-only migrations

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
    ];

    /**
     * Attributes that should never be mass assignable.
     * SECURITY: Prevents privilege escalation attacks.
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Check if the admin can access a specific tenant.
     * Admins with 'tenants:impersonate' permission can access any tenant.
     */
    public function canAccessTenant(Tenant $tenant): bool
    {
        return $this->can('tenants:impersonate');
    }

    /**
     * Scope for super admins only (those with super-admin role).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSuperAdmins($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->where('name', 'super-admin');
        });
    }

    /**
     * Check if the admin has the super-admin role.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * Get the admin's role name for display.
     */
    public function getRoleName(): ?string
    {
        return $this->roles->first()?->name;
    }

    /**
     * Get the admin's role display name (translated).
     */
    public function getRoleDisplayName(): ?string
    {
        $role = $this->roles->first();
        return $role?->display_name ?? $role?->name;
    }

    /**
     * Get the admin's preferred locale with fallback.
     */
    public function getPreferredLocale(): string
    {
        if ($this->locale && in_array($this->locale, config('app.locales', []))) {
            return $this->locale;
        }

        return config('app.locale', 'en');
    }
}
