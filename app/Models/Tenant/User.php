<?php

namespace App\Models\Tenant;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Tenant\Traits\HasFederation;

/**
 * User - Tenant users stored in tenant database.
 *
 * TENANT-ONLY ARCHITECTURE (Option C):
 * - Each tenant has its own users table with complete isolation
 * - No connection to central database
 * - Roles and permissions are local (same database = no workarounds needed)
 *
 * Uses UUID for primary key (best practice for multi-tenant SaaS):
 * - Security: Doesn't expose user count or creation order
 * - Cross-database: Works better across tenant databases
 * - API-safe: Secure in URLs and API responses
 *
 * @property string $id UUID primary key
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $locale
 * @property string|null $department
 * @property string|null $employee_id
 * @property array|null $custom_settings
 * @property string|null $federated_user_id Federation link (null = local user only)
 * @property \Carbon\Carbon|null $email_verified_at
 * @property \Carbon\Carbon|null $two_factor_confirmed_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use CausesActivity;
    use HasApiTokens;
    use HasFactory;
    use HasFederation;
    use HasRoles;
    use HasUuids;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    // NOTE: No CentralConnection trait - User is tenant-only

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Database\Factories\UserFactory
    {
        return \Database\Factories\UserFactory::new();
    }

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
        'email_verified_at',
        // Tenant-specific fields (optional)
        'department',
        'employee_id',
        'custom_settings',
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
            'custom_settings' => 'array',
        ];
    }

    /**
     * Activity log configuration.
     * Works natively now (same database).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Check if the user is an owner of the tenant.
     */
    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    /**
     * Check if the user is an admin of the tenant.
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['owner', 'admin']);
    }

    /**
     * Check if the user is an admin or owner.
     */
    public function isAdminOrOwner(): bool
    {
        return $this->hasAnyRole(['owner', 'admin']);
    }

    /**
     * Get the user's current role name in this tenant.
     *
     * OPTION C: Users exist only in their tenant database,
     * so roles are always in the current context.
     */
    public function currentTenantRole(): ?string
    {
        return $this->roles->first()?->name;
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermissionInTenant(string $permission): bool
    {
        return $this->hasPermissionTo($permission);
    }

    /**
     * User's projects (same database = native JOIN).
     *
     * @return HasMany
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    /**
     * Get user's preferred locale with fallback chain:
     * 1. User's explicit locale preference
     * 2. Tenant's default language (if available)
     * 3. App's default locale
     */
    public function getPreferredLocale(): string
    {
        // User's explicit preference takes priority
        if ($this->locale && in_array($this->locale, config('app.locales', []))) {
            return $this->locale;
        }

        // Fallback to tenant's default language (via tenant settings if available)
        if (tenancy()->initialized) {
            $tenantLocale = tenant()?->getSetting('language.default');
            if ($tenantLocale && in_array($tenantLocale, config('app.locales', []))) {
                return $tenantLocale;
            }
        }

        // Final fallback to app default
        return config('app.locale', 'en');
    }

    /**
     * Send the email verification notification.
     *
     * Custom implementation to use tenant-specific route.
     */
    public function sendEmailVerificationNotification(): void
    {
        // Configure the verification URL to use tenant route
        VerifyEmail::createUrlUsing(function ($notifiable) {
            return URL::temporarySignedRoute(
                'tenant.admin.auth.verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
        });

        $this->notify(new VerifyEmail);
    }
}
