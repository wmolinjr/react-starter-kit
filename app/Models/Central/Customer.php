<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Stancl\Tenancy\Database\TenantCollection;
use Stancl\Tenancy\ResourceSyncing\SyncMaster;
use Stancl\Tenancy\ResourceSyncing\ResourceSyncing;

/**
 * Customer Model - Central Billing Entity
 *
 * ARCHITECTURE:
 * - One Customer = One Stripe Customer
 * - Customer can own multiple Tenants
 * - Implements SyncMaster for Resource Syncing with Tenant\User
 * - Synced attributes: global_id, name, email, password, locale
 *
 * AUTHENTICATION:
 * - Uses 'customer' guard for /account/* routes
 * - Separate from Central\User (platform admins) and Tenant\User (workspace users)
 *
 * @property string $id UUID primary key
 * @property string $global_id Resource Syncing identifier
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $password
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property \Carbon\Carbon|null $trial_ends_at
 * @property array|null $billing_address
 * @property string $locale
 * @property string $currency
 * @property array|null $tax_ids
 * @property \Carbon\Carbon|null $email_verified_at
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $deleted_at
 */
class Customer extends Authenticatable implements SyncMaster, MustVerifyEmail
{
    use Billable;
    use CentralConnection;
    use HasFactory;
    use HasUuids;
    use Notifiable;
    use ResourceSyncing;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    protected $table = 'customers';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Database\Factories\CustomerFactory
    {
        return \Database\Factories\CustomerFactory::new();
    }

    protected $fillable = [
        'global_id',
        'name',
        'email',
        'phone',
        'password',
        'billing_address',
        'locale',
        'currency',
        'tax_ids',
        'metadata',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'billing_address' => 'array',
            'tax_ids' => 'array',
            'metadata' => 'array',
            'trial_ends_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // =========================================================================
    // SyncMaster Implementation (Stancl Resource Syncing)
    // =========================================================================

    /**
     * The tenant model class that this resource syncs to.
     */
    public function getTenantModelName(): string
    {
        return \App\Models\Tenant\User::class;
    }

    /**
     * The central model class (this class).
     */
    public function getCentralModelName(): string
    {
        return static::class;
    }

    /**
     * Attributes to keep in sync between Customer and Tenant\User.
     *
     * When Customer is updated, these attributes propagate to all linked Tenant\Users.
     * When Tenant\User is updated, these attributes propagate back to Customer.
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
     * Additional attributes used only when creating the tenant resource.
     * Merged with getSyncedAttributeNames() during creation.
     */
    public function getCreationAttributes(): array
    {
        return [
            // Synced attributes
            'global_id',
            'name',
            'email',
            'password',
            'locale',
            // Default values for tenant-only fields
            'email_verified_at' => $this->email_verified_at,
        ];
    }

    /**
     * Conditional sync: only sync if customer has verified email.
     */
    public function shouldSync(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Tenants this customer can access (via Resource Syncing pivot).
     * Uses custom pivot table instead of default tenant_resources.
     *
     * Returns TenantCollection for Stancl Resource Syncing compatibility.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(
            Tenant::class,
            'customer_tenants',  // Custom pivot table
            'global_id',         // This model's key in pivot
            'tenant_id',         // Related model's key in pivot
            'global_id',         // This model's local key
            'id'                 // Related model's local key
        )->using(CustomerTenantPivot::class)
         ->withTimestamps();
    }

    /**
     * Get the tenants attribute as TenantCollection.
     * Required for Stancl Resource Syncing compatibility.
     */
    public function getTenantsAttribute(): TenantCollection
    {
        $tenants = $this->getRelationValue('tenants');

        if ($tenants === null) {
            return new TenantCollection();
        }

        if ($tenants instanceof TenantCollection) {
            return $tenants;
        }

        return new TenantCollection($tenants->all());
    }

    // =========================================================================
    // Tenant Ownership
    // =========================================================================

    /**
     * Tenants this customer owns (pays for).
     * Different from tenants() which includes access via pivot.
     */
    public function ownedTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'customer_id');
    }

    /**
     * Create a new tenant for this customer.
     */
    public function createTenant(array $data): Tenant
    {
        $tenant = Tenant::create([
            ...$data,
            'customer_id' => $this->id,
        ]);

        // Attach customer to tenant via pivot (triggers CreateTenantResource)
        $this->tenants()->attach($tenant);

        return $tenant;
    }

    // =========================================================================
    // Billing (Laravel Cashier)
    // =========================================================================

    /**
     * Get all subscriptions (across all owned tenants).
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'customer_id');
    }

    /**
     * Get active subscription for a specific tenant.
     */
    public function subscriptionForTenant(Tenant $tenant): ?Subscription
    {
        return $this->subscriptions()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->first();
    }

    /**
     * Get default payment method or tenant-specific override.
     */
    public function paymentMethodForTenant(Tenant $tenant): ?object
    {
        if ($tenant->payment_method_id) {
            return $this->findPaymentMethod($tenant->payment_method_id);
        }

        return $this->defaultPaymentMethod();
    }

    /**
     * Get Stripe customer name.
     */
    public function stripeName(): ?string
    {
        return $this->name;
    }

    /**
     * Get Stripe customer email.
     */
    public function stripeEmail(): ?string
    {
        return $this->email;
    }

    // =========================================================================
    // Addon Purchases & Subscriptions
    // =========================================================================

    /**
     * One-time addon purchases.
     */
    public function addonPurchases(): HasMany
    {
        return $this->hasMany(AddonPurchase::class, 'customer_id');
    }

    /**
     * Recurring addon subscriptions.
     */
    public function addonSubscriptions(): HasMany
    {
        return $this->hasMany(AddonSubscription::class, 'customer_id');
    }

    // =========================================================================
    // Tenant Transfers
    // =========================================================================

    /**
     * Transfers initiated by this customer.
     */
    public function initiatedTransfers(): HasMany
    {
        return $this->hasMany(TenantTransfer::class, 'from_customer_id');
    }

    /**
     * Transfers received by this customer.
     */
    public function receivedTransfers(): HasMany
    {
        return $this->hasMany(TenantTransfer::class, 'to_customer_id');
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Get total monthly billing across all tenants.
     */
    public function getTotalMonthlyBilling(): int
    {
        return $this->subscriptions()
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->get()
            ->sum(function ($subscription) {
                // Get the price from subscription items if available
                $item = $subscription->items()->first();

                return $item ? ($item->quantity ?? 1) * 100 : 0; // Placeholder - actual price from Stripe
            });
    }

    /**
     * Check if customer has any active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->exists();
    }

    /**
     * Route notifications for the mail channel.
     */
    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    /**
     * Get the preferred locale for notifications and UI.
     * Required by SetLocale middleware.
     */
    public function getPreferredLocale(): string
    {
        return $this->locale ?? config('app.locale', 'pt_BR');
    }

    /**
     * Send the email verification notification.
     * Uses customer-specific verification route.
     */
    public function sendEmailVerificationNotification(): void
    {
        // Create custom verification URL using customer route
        VerifyEmail::createUrlUsing(function ($notifiable) {
            return \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'customer.verification.verify',
                now()->addMinutes(60),
                ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
            );
        });

        $this->notify(new VerifyEmail);
    }

    /**
     * Send the password reset notification.
     * Uses customer-specific password reset route.
     */
    public function sendPasswordResetNotification($token): void
    {
        // Create custom reset URL using customer route
        ResetPassword::createUrlUsing(function ($notifiable, $token) {
            return url(route('customer.password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
        });

        $this->notify(new ResetPassword($token));
    }
}
