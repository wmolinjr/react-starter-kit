<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central User - Administrative users in the central database.
 *
 * Used ONLY for:
 * - Super admins who manage tenants
 * - Technical support who accesses tenants via impersonation
 * - Billing and plan operations
 *
 * NOT tenant users. Do not have tenant roles/permissions.
 *
 * @property string $id UUID primary key
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_super_admin
 * @property string $locale
 * @property \Carbon\Carbon|null $email_verified_at
 * @property \Carbon\Carbon|null $two_factor_confirmed_at
 */
class User extends Authenticatable
{
    use CentralConnection;
    use HasFactory;
    use HasUuids;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Database\Factories\AdminFactory
    {
        return \Database\Factories\AdminFactory::new();
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'admins';

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
            'is_super_admin' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Check if the admin can access a specific tenant.
     * Super admins can access any tenant.
     */
    public function canAccessTenant(Tenant $tenant): bool
    {
        return $this->is_super_admin;
    }

    /**
     * Scope for super admins only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSuperAdmins($query)
    {
        return $query->where('is_super_admin', true);
    }

    /**
     * Check if the admin is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
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
