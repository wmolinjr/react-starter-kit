<?php

namespace App\Models\Tenant;

use App\Models\Central\Customer;
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
use Stancl\Tenancy\ResourceSyncing\Syncable;
use Stancl\Tenancy\ResourceSyncing\ResourceSyncing;

/**
 * User - Tenant users stored in tenant database.
 *
 * TENANT-ONLY ARCHITECTURE (Option C):
 * - Each tenant has its own users table with complete isolation
 * - No connection to central database
 * - Roles and permissions are local (same database = no workarounds needed)
 *
 * RESOURCE SYNCING (Customer Billing):
 * - Implements Syncable for bidirectional sync with Central\Customer
 * - global_id links tenant user to customer (for owners only)
 * - Synced attributes: global_id, name, email, password, locale
 * - Regular team members (no global_id) are NOT synced
 *
 * Uses UUID for primary key (best practice for multi-tenant SaaS):
 * - Security: Doesn't expose user count or creation order
 * - Cross-database: Works better across tenant databases
 * - API-safe: Secure in URLs and API responses
 *
 * @property string $id UUID primary key
 * @property string|null $global_id Resource Syncing link to Central\Customer
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
class User extends Authenticatable implements MustVerifyEmail, Syncable
{
    use CausesActivity;
    use HasApiTokens;
    use HasFactory;
    use HasFederation;
    use HasRoles;
    use HasUuids;
    use LogsActivity;
    use Notifiable;
    use ResourceSyncing;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    // NOTE: No CentralConnection trait - User is tenant-only
    // ResourceSyncing trait handles bidirectional sync with Central\Customer

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            event(new \App\Events\Tenant\UserCreated($user));
        });
    }

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
        // Resource Syncing (synced with Central\Customer)
        'global_id',
        'name',
        'email',
        'password',
        'locale',
        'email_verified_at',
        // Tenant-specific fields (optional, NOT synced)
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
     * 2. App's current locale (set by TenantConfigBootstrapper from tenant settings)
     */
    public function getPreferredLocale(): string
    {
        // User's explicit preference takes priority
        if ($this->locale && in_array($this->locale, config('app.locales', []))) {
            return $this->locale;
        }

        // Fallback to app locale (already set by TenantConfigBootstrapper from tenant config.locale)
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

    // =========================================================================
    // Syncable Implementation (Stancl Resource Syncing)
    // =========================================================================

    /**
     * The central model class this resource syncs with.
     */
    public function getCentralModelName(): string
    {
        return Customer::class;
    }

    /**
     * Attributes to keep in sync with Central\Customer.
     */
    public function getSyncedAttributeNames(): array
    {
        return [
            'global_id',
            'name',
            'email',
            'password',
            'locale',
        ];
    }

    /**
     * Only sync if this user has a global_id (is linked to a Customer).
     * Regular team members (invited users) don't sync.
     */
    public function shouldSync(): bool
    {
        return $this->global_id !== null;
    }

    // =========================================================================
    // Customer Relationship (for owners linked to customers)
    // =========================================================================

    /**
     * Get the central customer this user is linked to.
     * Returns null for regular team members (non-owners).
     */
    public function getCentralCustomer(): ?Customer
    {
        if (!$this->global_id) {
            return null;
        }

        return tenancy()->central(function () {
            return Customer::where('global_id', $this->global_id)->first();
        });
    }

    /**
     * Check if this user is linked to a central customer.
     * Linked users are typically owners who created the tenant.
     */
    public function isLinkedToCustomer(): bool
    {
        return $this->global_id !== null;
    }

    /**
     * Check if this user can access billing.
     * Only owners linked to a customer can manage billing.
     */
    public function canAccessBilling(): bool
    {
        return $this->isOwner() && $this->isLinkedToCustomer();
    }
}
