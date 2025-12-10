# Customer Billing Implementation Guide

> **Solution**: Stancl Resource Syncing + Laravel Cashier
> **Status**: Implementation Ready
> **Date**: December 2024

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Database Schema](#database-schema)
4. [Models](#models)
5. [Event Configuration](#event-configuration)
6. [Services](#services)
7. [Routes & Controllers](#routes--controllers)
8. [Frontend Pages](#frontend-pages)
9. [Implementation Phases](#implementation-phases)
10. [Testing Strategy](#testing-strategy)
11. [Migration Guide](#migration-guide)

---

## Overview

### Problem Statement

Current architecture ties Stripe billing to `Tenant`, not to the real person (Customer):

- User with 5 tenants = 5 separate Stripe Customers
- No unified billing view across tenants
- Payment methods duplicated per tenant
- Future non-tenant products have no billing entity

### Solution: Central Customer + Resource Syncing

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         BILLING ARCHITECTURE                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   Central\Customer (SyncMaster + Billable)                                 │
│   ├── stripe_id, pm_type, pm_last_four                                     │
│   ├── billing_address, tax_ids                                             │
│   └── tenants() → owns multiple tenants                                    │
│            │                                                                │
│            │ Resource Syncing (automatic)                                   │
│            │ Synced: global_id, name, email, password, locale              │
│            ▼                                                                │
│   Tenant\User (Syncable)                                                   │
│   ├── global_id → links to Customer                                        │
│   ├── role, permissions (NOT synced)                                       │
│   └── department, employee_id (tenant-specific)                            │
│                                                                             │
│   Tenant                                                                    │
│   ├── customer_id (FK) → who pays                                          │
│   ├── payment_method_id → override (optional)                              │
│   └── subscription → via Customer                                          │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Key Benefits

| Benefit | Description |
|---------|-------------|
| **Unified Billing** | One Stripe Customer per person, not per tenant |
| **Auto Sync** | Stancl handles bidirectional sync automatically |
| **Cascade Delete** | Deleting Customer removes linked Tenant\Users |
| **Queue Support** | Sync operations can be queued in production |
| **Soft Delete Support** | Restore cascades to tenant resources |
| **Less Code** | Built-in listeners, no custom sync logic |

---

## Architecture

### Entity Relationships

```
┌──────────────────┐     owns      ┌──────────────────┐
│ Central\Customer │──────────────►│      Tenant      │
│   (SyncMaster)   │    1:N        │                  │
│   + Billable     │               │  customer_id FK  │
└────────┬─────────┘               └──────────────────┘
         │                                  │
         │ Resource Syncing                 │ has users
         │ via global_id                    │
         ▼                                  ▼
┌──────────────────┐               ┌──────────────────┐
│   Tenant\User    │◄──────────────│   Tenant\User    │
│   (Syncable)     │   same DB     │   (members)      │
│   global_id set  │               │   global_id null │
└──────────────────┘               └──────────────────┘
      Owner                              Members
```

### Authentication Guards

| Guard | Model | Domain | Purpose |
|-------|-------|--------|---------|
| `central` | Central\User | app.test/admin | Platform admins |
| `customer` | Central\Customer | app.test/account | Billing portal |
| `tenant` | Tenant\User | {tenant}.test | Workspace access |

### Data Flow

```
1. Customer registers at /account/register
   └── Creates Central\Customer (global_id generated)
       └── Stripe Customer created (Cashier)

2. Customer creates tenant
   └── Tenant created with customer_id
       └── Pivot: customer_tenants (global_id ↔ tenant_id)
           └── Event: CentralResourceAttachedToTenant
               └── Listener: CreateTenantResource
                   └── Tenant\User created with same global_id

3. Customer updates profile at /account/profile
   └── Customer.email = 'new@email.com'
       └── Event: SyncedResourceSaved
           └── Listener: UpdateOrCreateSyncedResource
               └── All linked Tenant\Users updated

4. Tenant user updates profile at {tenant}.test/settings
   └── Tenant\User.name = 'New Name'
       └── Event: SyncedResourceSaved
           └── Listener: UpdateOrCreateSyncedResource
               └── Central\Customer updated
```

---

## Database Schema

### Phase 1: Central Database Migrations

#### 1.1 Create Customers Table

```php
<?php
// database/migrations/2024_12_10_000001_create_customers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Resource Syncing identifier
            $table->string('global_id')->unique();

            // Identity
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->rememberToken();

            // Stripe Billable (Laravel Cashier)
            $table->string('stripe_id')->nullable()->unique();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            // Billing Information
            $table->json('billing_address')->nullable();
            $table->string('locale', 10)->default('pt_BR');
            $table->string('currency', 3)->default('brl');
            $table->json('tax_ids')->nullable();

            // Authentication
            $table->timestamp('email_verified_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('email');
            $table->index('stripe_id');
            $table->index('global_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
```

#### 1.2 Create Customer-Tenant Pivot Table

```php
<?php
// database/migrations/2024_12_10_000002_create_customer_tenants_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This is the pivot table for Resource Syncing
        // Maps Customer.global_id to Tenant.id
        Schema::create('customer_tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('global_id');  // Customer's global_id
            $table->string('tenant_id');  // Tenant's id
            $table->timestamps();

            $table->unique(['global_id', 'tenant_id']);
            $table->index('global_id');
            $table->index('tenant_id');

            // Foreign keys
            $table->foreign('global_id')
                ->references('global_id')
                ->on('customers')
                ->cascadeOnDelete();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tenants');
    }
};
```

#### 1.3 Add Customer FK to Tenants

```php
<?php
// database/migrations/2024_12_10_000003_add_customer_id_to_tenants_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Who pays for this tenant
            $table->foreignUuid('customer_id')
                ->nullable()
                ->after('id')
                ->constrained('customers')
                ->nullOnDelete();

            // Optional: Override payment method for this tenant
            $table->string('payment_method_id')->nullable()->after('customer_id');

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'payment_method_id']);
        });
    }
};
```

#### 1.4 Modify Subscriptions Table

```php
<?php
// database/migrations/2024_12_10_000004_add_customer_id_to_subscriptions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add customer_id (subscriptions now belong to Customer)
            $table->foreignUuid('customer_id')
                ->nullable()
                ->after('id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Make tenant_id nullable (for backward compatibility)
            $table->uuid('tenant_id')->nullable()->change();

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
```

#### 1.5 Create Customer Password Reset Tokens

```php
<?php
// database/migrations/2024_12_10_000005_create_customer_password_reset_tokens_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_password_reset_tokens');
    }
};
```

#### 1.6 Create Tenant Transfers Table

```php
<?php
// database/migrations/2024_12_10_000006_create_tenant_transfers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('from_customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Target (can be existing customer or new email)
            $table->foreignUuid('to_customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();
            $table->string('to_email');

            // Transfer details
            $table->decimal('transfer_fee', 10, 2)->default(0);
            $table->string('transfer_fee_currency', 3)->default('brl');
            $table->decimal('remaining_subscription_value', 10, 2)->default(0);

            // Security
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');

            // Status
            $table->enum('status', [
                'pending',      // Waiting for recipient to accept
                'accepted',     // Recipient accepted
                'completed',    // Transfer finalized
                'cancelled',    // Cancelled by initiator
                'expired',      // Token expired
                'rejected',     // Rejected by recipient
            ])->default('pending');

            $table->text('notes')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('token');
            $table->index('status');
            $table->index('to_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_transfers');
    }
};
```

### Phase 2: Tenant Database Migrations

#### 2.1 Add Global ID to Users

```php
<?php
// database/migrations/tenant/2024_12_10_000001_add_global_id_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Link to Central\Customer for Resource Syncing
            // Only set for owners (users who are also Customers)
            $table->string('global_id')
                ->nullable()
                ->unique()
                ->after('id');

            $table->index('global_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('global_id');
        });
    }
};
```

---

## Models

### Central\Customer (SyncMaster + Billable)

```php
<?php
// app/Models/Central/Customer.php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Stancl\Tenancy\Contracts\SyncMaster;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Stancl\Tenancy\Database\Concerns\ResourceSyncing;
use Stancl\Tenancy\Database\TenantPivot;
use Stancl\Tenancy\ResourceSyncing\Contracts\CascadeDeletes;

class Customer extends Authenticatable implements SyncMaster, CascadeDeletes
{
    use Billable;
    use CentralConnection;
    use HasUuids;
    use Notifiable;
    use ResourceSyncing;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    protected $table = 'customers';

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

    // ─────────────────────────────────────────────────────────────────────────
    // SyncMaster Implementation (Stancl Resource Syncing)
    // ─────────────────────────────────────────────────────────────────────────

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
        )->using(TenantPivot::class)
         ->withTimestamps();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tenant Ownership
    // ─────────────────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────────────────
    // Billing (Laravel Cashier)
    // ─────────────────────────────────────────────────────────────────────────

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
            ->active()
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

    // ─────────────────────────────────────────────────────────────────────────
    // Addon Purchases & Subscriptions
    // ─────────────────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────────────────
    // Tenant Transfers
    // ─────────────────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────────────────
    // Helper Methods
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get total monthly billing across all tenants.
     */
    public function getTotalMonthlyBilling(): int
    {
        return $this->subscriptions()
            ->active()
            ->get()
            ->sum(fn ($sub) => $sub->recurringPrice());
    }

    /**
     * Check if customer has any active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()->active()->exists();
    }

    /**
     * Route notifications for the mail channel.
     */
    public function routeNotificationForMail(): string
    {
        return $this->email;
    }
}
```

### Tenant\User (Syncable - Updated)

```php
<?php
// app/Models/Tenant/User.php

namespace App\Models\Tenant;

use App\Models\Central\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Contracts\Syncable;
use Stancl\Tenancy\Database\Concerns\ResourceSyncing;

class User extends Authenticatable implements Syncable
{
    use HasRoles;
    use HasUuids;
    use Notifiable;
    use ResourceSyncing;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        // Synced with Central\Customer
        'global_id',
        'name',
        'email',
        'password',
        'locale',

        // Tenant-specific (NOT synced)
        'department',
        'employee_id',
        'phone',
        'avatar',
        'settings',
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
            'settings' => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Syncable Implementation (Stancl Resource Syncing)
    // ─────────────────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────────────────
    // Customer Relationship
    // ─────────────────────────────────────────────────────────────────────────

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
     * Check if this user is the tenant owner.
     */
    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    /**
     * Check if this user can access billing.
     * Only owners linked to a customer can manage billing.
     */
    public function canAccessBilling(): bool
    {
        return $this->isOwner() && $this->isLinkedToCustomer();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Existing Methods (preserved)
    // ─────────────────────────────────────────────────────────────────────────

    // ... keep all existing methods from current User model
}
```

### Tenant Model (Updated)

```php
<?php
// app/Models/Central/Tenant.php (additions only)

// Add these methods to the existing Tenant model:

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
 * Check if this tenant has an active subscription.
 */
public function hasActiveSubscription(): bool
{
    if (!$this->customer) {
        return false;
    }

    return $this->customer->subscriptionForTenant($this)?->active() ?? false;
}

/**
 * Get the active subscription for this tenant.
 */
public function subscription(): ?Subscription
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
```

### TenantTransfer Model

```php
<?php
// app/Models/Central/TenantTransfer.php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantTransfer extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'from_customer_id',
        'to_customer_id',
        'to_email',
        'transfer_fee',
        'transfer_fee_currency',
        'remaining_subscription_value',
        'token',
        'expires_at',
        'status',
        'notes',
        'accepted_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'transfer_fee' => 'decimal:2',
            'remaining_subscription_value' => 'decimal:2',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function fromCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'from_customer_id');
    }

    public function toCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'to_customer_id');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Status Helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
               ($this->isPending() && $this->expires_at->isPast());
    }

    public function canBeAccepted(): bool
    {
        return $this->isPending() && !$this->isExpired();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Boot
    // ─────────────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $transfer) {
            $transfer->token = $transfer->token ?? Str::random(64);
            $transfer->expires_at = $transfer->expires_at ?? now()->addDays(7);
        });
    }
}
```

---

## Event Configuration

### TenancyServiceProvider Events

```php
<?php
// app/Providers/TenancyServiceProvider.php

namespace App\Providers;

use Stancl\Tenancy\ResourceSyncing;

class TenancyServiceProvider extends ServiceProvider
{
    public function events(): array
    {
        return [
            // ─────────────────────────────────────────────────────────────────
            // Existing Tenancy Events
            // ─────────────────────────────────────────────────────────────────

            \Stancl\Tenancy\Events\TenantCreated::class => [
                \Stancl\Tenancy\Listeners\CreateDatabase::class,
                \Stancl\Tenancy\Listeners\MigrateDatabase::class,
                \App\Jobs\Central\SeedTenantDatabase::class,
            ],

            \Stancl\Tenancy\Events\TenantDeleted::class => [
                \Stancl\Tenancy\Listeners\DeleteDatabase::class,
            ],

            // ─────────────────────────────────────────────────────────────────
            // Resource Syncing Events (Customer ↔ Tenant\User)
            // ─────────────────────────────────────────────────────────────────

            // When a synced resource (Customer or Tenant\User) is saved
            ResourceSyncing\Events\SyncedResourceSaved::class => [
                ResourceSyncing\Listeners\UpdateOrCreateSyncedResource::class,
            ],

            // When a SyncMaster (Customer) is deleted
            ResourceSyncing\Events\SyncMasterDeleted::class => [
                ResourceSyncing\Listeners\DeleteResourcesInTenants::class,
            ],

            // When a SyncMaster (Customer) is restored from soft delete
            ResourceSyncing\Events\SyncMasterRestored::class => [
                ResourceSyncing\Listeners\RestoreResourcesInTenants::class,
            ],

            // When a Customer is attached to a Tenant (via pivot)
            ResourceSyncing\Events\CentralResourceAttachedToTenant::class => [
                ResourceSyncing\Listeners\CreateTenantResource::class,
            ],

            // When a Customer is detached from a Tenant (via pivot)
            ResourceSyncing\Events\CentralResourceDetachedFromTenant::class => [
                ResourceSyncing\Listeners\DeleteResourceInTenants::class,
            ],

            // Fired when sync happens in foreign database (for custom logic)
            ResourceSyncing\Events\SyncedResourceSavedInForeignDatabase::class => [
                // Add custom listeners if needed
            ],
        ];
    }

    public function register(): void
    {
        // ... existing code
    }

    public function boot(): void
    {
        parent::boot();

        // ─────────────────────────────────────────────────────────────────────
        // Production: Queue resource syncing operations
        // ─────────────────────────────────────────────────────────────────────

        if (app()->environment('production')) {
            ResourceSyncing\Listeners\UpdateOrCreateSyncedResource::$shouldQueue = true;
        }

        // ─────────────────────────────────────────────────────────────────────
        // Include soft-deleted records in sync queries
        // ─────────────────────────────────────────────────────────────────────

        ResourceSyncing\Listeners\UpdateOrCreateSyncedResource::$scopeGetModelQuery = function ($query) {
            if ($query->hasMacro('withTrashed')) {
                $query->withTrashed();
            }
        };

        // ... existing boot code
    }
}
```

---

## Services

### CustomerService

```php
<?php
// app/Services/Central/CustomerService.php

namespace App\Services\Central;

use App\Models\Central\Customer;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerService
{
    /**
     * Register a new customer.
     */
    public function register(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::create([
                'global_id' => Str::uuid()->toString(),
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'locale' => $data['locale'] ?? config('app.locale'),
                'currency' => $data['currency'] ?? 'brl',
            ]);

            // Create Stripe customer immediately
            $customer->createAsStripeCustomer([
                'name' => $customer->name,
                'email' => $customer->email,
                'metadata' => [
                    'customer_id' => $customer->id,
                ],
            ]);

            return $customer;
        });
    }

    /**
     * Create a tenant for a customer.
     */
    public function createTenant(Customer $customer, array $data): Tenant
    {
        return DB::transaction(function () use ($customer, $data) {
            // Create tenant with customer ownership
            $tenant = Tenant::create([
                'customer_id' => $customer->id,
                'data' => $data['data'] ?? [],
            ]);

            // Create domain
            if (isset($data['domain'])) {
                $tenant->domains()->create([
                    'domain' => $data['domain'],
                ]);
            }

            // Attach customer to tenant (triggers CreateTenantResource)
            // This will create Tenant\User with owner role
            $customer->tenants()->attach($tenant);

            return $tenant->fresh(['domains', 'customer']);
        });
    }

    /**
     * Update customer profile.
     */
    public function updateProfile(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        // Sync to Stripe if billing info changed
        if (isset($data['name']) || isset($data['email'])) {
            $customer->syncStripeCustomerDetails();
        }

        return $customer->fresh();
    }

    /**
     * Update billing address.
     */
    public function updateBillingAddress(Customer $customer, array $address): Customer
    {
        $customer->update(['billing_address' => $address]);

        // Sync to Stripe
        $customer->updateStripeCustomer([
            'address' => [
                'line1' => $address['line1'] ?? null,
                'line2' => $address['line2'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'country' => $address['country'] ?? null,
            ],
        ]);

        return $customer;
    }

    /**
     * Add tax ID.
     */
    public function addTaxId(Customer $customer, string $type, string $value): Customer
    {
        $taxIds = $customer->tax_ids ?? [];
        $taxIds[] = ['type' => $type, 'value' => $value];

        $customer->update(['tax_ids' => $taxIds]);

        // Add to Stripe
        $customer->createTaxId($type, $value);

        return $customer;
    }
}
```

### TenantTransferService

```php
<?php
// app/Services/Central/TenantTransferService.php

namespace App\Services\Central;

use App\Models\Central\Customer;
use App\Models\Central\Tenant;
use App\Models\Central\TenantTransfer;
use App\Notifications\TenantTransferInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TenantTransferService
{
    /**
     * Transfer fee percentage (5%).
     */
    public const TRANSFER_FEE_PERCENTAGE = 0.05;

    /**
     * Initiate a tenant transfer.
     */
    public function initiateTransfer(
        Customer $fromCustomer,
        Tenant $tenant,
        string $toEmail,
        ?string $notes = null
    ): TenantTransfer {
        // Validate ownership
        if ($tenant->customer_id !== $fromCustomer->id) {
            throw new \InvalidArgumentException('Customer does not own this tenant.');
        }

        // Calculate remaining subscription value
        $remainingValue = $this->calculateRemainingSubscriptionValue($tenant);
        $transferFee = $remainingValue * self::TRANSFER_FEE_PERCENTAGE;

        // Check if recipient is existing customer
        $toCustomer = Customer::where('email', $toEmail)->first();

        $transfer = TenantTransfer::create([
            'tenant_id' => $tenant->id,
            'from_customer_id' => $fromCustomer->id,
            'to_customer_id' => $toCustomer?->id,
            'to_email' => $toEmail,
            'transfer_fee' => $transferFee,
            'remaining_subscription_value' => $remainingValue,
            'notes' => $notes,
        ]);

        // Send invitation email
        if ($toCustomer) {
            $toCustomer->notify(new TenantTransferInvitation($transfer));
        } else {
            Notification::route('mail', $toEmail)
                ->notify(new TenantTransferInvitation($transfer));
        }

        return $transfer;
    }

    /**
     * Accept a tenant transfer.
     */
    public function acceptTransfer(TenantTransfer $transfer, Customer $customer): TenantTransfer
    {
        if (!$transfer->canBeAccepted()) {
            throw new \InvalidArgumentException('Transfer cannot be accepted.');
        }

        if ($transfer->to_email !== $customer->email) {
            throw new \InvalidArgumentException('Transfer is for a different email.');
        }

        return DB::transaction(function () use ($transfer, $customer) {
            $transfer->update([
                'status' => 'accepted',
                'to_customer_id' => $customer->id,
                'accepted_at' => now(),
            ]);

            // Charge transfer fee if applicable
            if ($transfer->transfer_fee > 0) {
                $customer->charge(
                    (int) ($transfer->transfer_fee * 100), // Convert to cents
                    $customer->defaultPaymentMethod()?->id,
                    [
                        'description' => "Transfer fee for tenant: {$transfer->tenant->name}",
                        'metadata' => [
                            'transfer_id' => $transfer->id,
                            'tenant_id' => $transfer->tenant_id,
                        ],
                    ]
                );
            }

            // Complete the transfer
            return $this->completeTransfer($transfer);
        });
    }

    /**
     * Complete the transfer (change ownership).
     */
    protected function completeTransfer(TenantTransfer $transfer): TenantTransfer
    {
        $tenant = $transfer->tenant;
        $fromCustomer = $transfer->fromCustomer;
        $toCustomer = $transfer->toCustomer;

        // Detach from old customer
        $fromCustomer->tenants()->detach($tenant);

        // Update tenant ownership
        $tenant->update(['customer_id' => $toCustomer->id]);

        // Attach to new customer (creates new owner user)
        $toCustomer->tenants()->attach($tenant);

        // Transfer subscription if active
        $subscription = $fromCustomer->subscriptionForTenant($tenant);
        if ($subscription) {
            $subscription->update(['customer_id' => $toCustomer->id]);
        }

        $transfer->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $transfer;
    }

    /**
     * Cancel a pending transfer.
     */
    public function cancelTransfer(TenantTransfer $transfer, Customer $customer): TenantTransfer
    {
        if ($transfer->from_customer_id !== $customer->id) {
            throw new \InvalidArgumentException('Only the initiator can cancel.');
        }

        if (!$transfer->isPending()) {
            throw new \InvalidArgumentException('Only pending transfers can be cancelled.');
        }

        $transfer->update(['status' => 'cancelled']);

        return $transfer;
    }

    /**
     * Reject a transfer invitation.
     */
    public function rejectTransfer(TenantTransfer $transfer, Customer $customer): TenantTransfer
    {
        if ($transfer->to_email !== $customer->email) {
            throw new \InvalidArgumentException('Transfer is for a different email.');
        }

        if (!$transfer->canBeAccepted()) {
            throw new \InvalidArgumentException('Transfer cannot be rejected.');
        }

        $transfer->update(['status' => 'rejected']);

        return $transfer;
    }

    /**
     * Calculate remaining subscription value.
     */
    protected function calculateRemainingSubscriptionValue(Tenant $tenant): float
    {
        $subscription = $tenant->subscription();

        if (!$subscription || !$subscription->active()) {
            return 0;
        }

        $endDate = $subscription->ends_at ?? $subscription->current_period_end;

        if (!$endDate || $endDate->isPast()) {
            return 0;
        }

        $daysRemaining = now()->diffInDays($endDate);
        $dailyRate = $subscription->recurringPrice() / 30;

        return round($dailyRate * $daysRemaining, 2);
    }
}
```

---

## Routes & Controllers

### Routes Configuration

```php
<?php
// routes/customer.php

use App\Http\Controllers\Customer\Auth\LoginController;
use App\Http\Controllers\Customer\Auth\LogoutController;
use App\Http\Controllers\Customer\Auth\RegisterController;
use App\Http\Controllers\Customer\Auth\ForgotPasswordController;
use App\Http\Controllers\Customer\Auth\ResetPasswordController;
use App\Http\Controllers\Customer\Auth\VerifyEmailController;
use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\TenantController;
use App\Http\Controllers\Customer\PaymentMethodController;
use App\Http\Controllers\Customer\InvoiceController;
use App\Http\Controllers\Customer\TransferController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer Portal Routes
|--------------------------------------------------------------------------
|
| Routes for the Customer Billing Portal at /account/*
| Uses 'customer' guard for authentication
|
*/

Route::prefix('account')->name('customer.')->group(function () {

    // ─────────────────────────────────────────────────────────────────────
    // Guest Routes
    // ─────────────────────────────────────────────────────────────────────

    Route::middleware('guest:customer')->group(function () {
        // Registration
        Route::get('register', [RegisterController::class, 'create'])->name('register');
        Route::post('register', [RegisterController::class, 'store']);

        // Login
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store']);

        // Password Reset
        Route::get('forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
        Route::get('reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [ResetPasswordController::class, 'store'])->name('password.update');

        // Transfer acceptance (can be accepted before login)
        Route::get('transfers/{token}/accept', [TransferController::class, 'showAccept'])->name('transfers.accept');
    });

    // ─────────────────────────────────────────────────────────────────────
    // Authenticated Routes
    // ─────────────────────────────────────────────────────────────────────

    Route::middleware('auth:customer')->group(function () {
        // Logout
        Route::post('logout', LogoutController::class)->name('logout');

        // Email Verification
        Route::get('verify-email', [VerifyEmailController::class, 'notice'])->name('verification.notice');
        Route::get('verify-email/{id}/{hash}', [VerifyEmailController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
        Route::post('email/verification-notification', [VerifyEmailController::class, 'send'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        // Verified routes
        Route::middleware('verified')->group(function () {
            // Dashboard
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

            // Profile
            Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

            // Tenants (Workspaces)
            Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
            Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
            Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
            Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
            Route::get('tenants/{tenant}/billing', [TenantController::class, 'billing'])->name('tenants.billing');

            // Tenant Transfers
            Route::get('tenants/{tenant}/transfer', [TransferController::class, 'create'])->name('transfers.create');
            Route::post('tenants/{tenant}/transfer', [TransferController::class, 'store'])->name('transfers.store');
            Route::post('transfers/{token}/confirm', [TransferController::class, 'confirm'])->name('transfers.confirm');
            Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');
            Route::post('transfers/{transfer}/reject', [TransferController::class, 'reject'])->name('transfers.reject');

            // Payment Methods
            Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
            Route::get('payment-methods/create', [PaymentMethodController::class, 'create'])->name('payment-methods.create');
            Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
            Route::delete('payment-methods/{id}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
            Route::post('payment-methods/{id}/default', [PaymentMethodController::class, 'setDefault'])->name('payment-methods.default');

            // Invoices
            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
            Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
        });
    });
});
```

### Auth Guard Configuration

```php
<?php
// config/auth.php (additions)

return [
    'guards' => [
        // ... existing guards

        'customer' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],
    ],

    'providers' => [
        // ... existing providers

        'customers' => [
            'driver' => 'eloquent',
            'model' => App\Models\Central\Customer::class,
        ],
    ],

    'passwords' => [
        // ... existing password brokers

        'customers' => [
            'provider' => 'customers',
            'table' => 'customer_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
];
```

### Sample Controller: DashboardController

```php
<?php
// app/Http/Controllers/Customer/DashboardController.php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\CustomerDashboardResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $customer = $request->user('customer');

        $customer->load([
            'ownedTenants.domains',
            'ownedTenants.subscription',
            'subscriptions' => fn ($q) => $q->active(),
        ]);

        return Inertia::render('customer/dashboard', [
            'customer' => new CustomerDashboardResource($customer),
            'totalMonthlyBilling' => $customer->getTotalMonthlyBilling(),
            'tenantCount' => $customer->ownedTenants->count(),
            'pendingTransfers' => $customer->receivedTransfers()
                ->where('status', 'pending')
                ->count(),
        ]);
    }
}
```

---

## Frontend Pages

### Page Structure

```
resources/js/pages/customer/
├── auth/
│   ├── login.tsx
│   ├── register.tsx
│   ├── forgot-password.tsx
│   ├── reset-password.tsx
│   └── verify-email.tsx
├── dashboard.tsx
├── profile/
│   ├── edit.tsx
│   └── partials/
│       ├── profile-information.tsx
│       ├── billing-address.tsx
│       ├── tax-ids.tsx
│       └── delete-account.tsx
├── tenants/
│   ├── index.tsx
│   ├── create.tsx
│   ├── show.tsx
│   ├── billing.tsx
│   └── transfer.tsx
├── payment-methods/
│   ├── index.tsx
│   └── create.tsx
├── invoices/
│   ├── index.tsx
│   └── show.tsx
└── transfers/
    └── accept.tsx
```

### Sample Page: Dashboard

```tsx
// resources/js/pages/customer/dashboard.tsx

import { Head } from '@inertiajs/react';
import { CustomerLayout } from '@/layouts/customer-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Building2, CreditCard, FileText, ArrowRight } from 'lucide-react';
import { Link } from '@inertiajs/react';
import type { CustomerDashboardResource } from '@/types';

interface Props {
    customer: CustomerDashboardResource;
    totalMonthlyBilling: number;
    tenantCount: number;
    pendingTransfers: number;
}

export default function Dashboard({
    customer,
    totalMonthlyBilling,
    tenantCount,
    pendingTransfers
}: Props) {
    return (
        <CustomerLayout>
            <Head title="Customer Dashboard" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold">Welcome, {customer.name}</h1>
                    <p className="text-muted-foreground">
                        Manage your workspaces, billing, and account settings.
                    </p>
                </div>

                {/* Stats */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Workspaces</CardTitle>
                            <Building2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{tenantCount}</div>
                            <p className="text-xs text-muted-foreground">
                                Active workspaces
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Monthly Billing</CardTitle>
                            <CreditCard className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {new Intl.NumberFormat('pt-BR', {
                                    style: 'currency',
                                    currency: customer.currency,
                                }).format(totalMonthlyBilling / 100)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Across all workspaces
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Pending Transfers</CardTitle>
                            <FileText className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{pendingTransfers}</div>
                            <p className="text-xs text-muted-foreground">
                                Awaiting your action
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Workspaces List */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Your Workspaces</CardTitle>
                                <CardDescription>
                                    Manage billing for each workspace
                                </CardDescription>
                            </div>
                            <Button asChild>
                                <Link href={route('customer.tenants.create')}>
                                    Create Workspace
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {customer.owned_tenants.map((tenant) => (
                                <div
                                    key={tenant.id}
                                    className="flex items-center justify-between rounded-lg border p-4"
                                >
                                    <div>
                                        <h3 className="font-semibold">{tenant.name}</h3>
                                        <p className="text-sm text-muted-foreground">
                                            {tenant.domains[0]?.domain}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className={`text-sm ${
                                            tenant.subscription?.active
                                                ? 'text-green-600'
                                                : 'text-yellow-600'
                                        }`}>
                                            {tenant.subscription?.active ? 'Active' : 'No Plan'}
                                        </span>
                                        <Button variant="ghost" size="sm" asChild>
                                            <Link href={route('customer.tenants.billing', tenant.id)}>
                                                <ArrowRight className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </CustomerLayout>
    );
}
```

---

## Implementation Phases

### Phase 1: Database & Models (Week 1)

| Task | Files | Status |
|------|-------|--------|
| Create customers migration | `database/migrations/2024_12_10_000001_create_customers_table.php` | Pending |
| Create customer_tenants pivot | `database/migrations/2024_12_10_000002_create_customer_tenants_table.php` | Pending |
| Add customer_id to tenants | `database/migrations/2024_12_10_000003_add_customer_id_to_tenants_table.php` | Pending |
| Modify subscriptions table | `database/migrations/2024_12_10_000004_add_customer_id_to_subscriptions_table.php` | Pending |
| Create password reset tokens | `database/migrations/2024_12_10_000005_create_customer_password_reset_tokens_table.php` | Pending |
| Create tenant_transfers | `database/migrations/2024_12_10_000006_create_tenant_transfers_table.php` | Pending |
| Add global_id to tenant users | `database/migrations/tenant/2024_12_10_000001_add_global_id_to_users_table.php` | Pending |
| Create Customer model | `app/Models/Central/Customer.php` | Pending |
| Create TenantTransfer model | `app/Models/Central/TenantTransfer.php` | Pending |
| Update Tenant model | `app/Models/Central/Tenant.php` | Pending |
| Update Tenant\User model | `app/Models/Tenant/User.php` | Pending |

### Phase 2: Event Configuration (Week 1)

| Task | Files | Status |
|------|-------|--------|
| Configure Resource Syncing events | `app/Providers/TenancyServiceProvider.php` | Pending |
| Configure customer guard | `config/auth.php` | Pending |
| Add Cashier configuration | `config/cashier.php` | Pending |

### Phase 3: Services (Week 2)

| Task | Files | Status |
|------|-------|--------|
| Create CustomerService | `app/Services/Central/CustomerService.php` | Pending |
| Create TenantTransferService | `app/Services/Central/TenantTransferService.php` | Pending |
| Update CheckoutService | `app/Services/Central/CheckoutService.php` | Pending |
| Update AddonService | `app/Services/Central/AddonService.php` | Pending |

### Phase 4: Routes & Controllers (Week 2-3)

| Task | Files | Status |
|------|-------|--------|
| Create customer routes | `routes/customer.php` | Pending |
| Register routes in RouteServiceProvider | `app/Providers/RouteServiceProvider.php` | Pending |
| Create Auth controllers | `app/Http/Controllers/Customer/Auth/*` | Pending |
| Create DashboardController | `app/Http/Controllers/Customer/DashboardController.php` | Pending |
| Create ProfileController | `app/Http/Controllers/Customer/ProfileController.php` | Pending |
| Create TenantController | `app/Http/Controllers/Customer/TenantController.php` | Pending |
| Create PaymentMethodController | `app/Http/Controllers/Customer/PaymentMethodController.php` | Pending |
| Create InvoiceController | `app/Http/Controllers/Customer/InvoiceController.php` | Pending |
| Create TransferController | `app/Http/Controllers/Customer/TransferController.php` | Pending |

### Phase 5: Frontend (Week 3-4)

| Task | Files | Status |
|------|-------|--------|
| Create CustomerLayout | `resources/js/layouts/customer-layout.tsx` | Pending |
| Create auth pages | `resources/js/pages/customer/auth/*` | Pending |
| Create dashboard | `resources/js/pages/customer/dashboard.tsx` | Pending |
| Create profile pages | `resources/js/pages/customer/profile/*` | Pending |
| Create tenant pages | `resources/js/pages/customer/tenants/*` | Pending |
| Create payment method pages | `resources/js/pages/customer/payment-methods/*` | Pending |
| Create invoice pages | `resources/js/pages/customer/invoices/*` | Pending |
| Create transfer pages | `resources/js/pages/customer/transfers/*` | Pending |

### Phase 6: Testing & Seeding (Week 4)

| Task | Files | Status |
|------|-------|--------|
| Create CustomerSeeder | `database/seeders/CustomerSeeder.php` | Pending |
| Update TenantSeeder | `database/seeders/TenantSeeder.php` | Pending |
| Create CustomerTest | `tests/Feature/CustomerTest.php` | Pending |
| Create ResourceSyncingTest | `tests/Feature/ResourceSyncingTest.php` | Pending |
| Create TenantTransferTest | `tests/Feature/TenantTransferTest.php` | Pending |
| Create CustomerBillingTest | `tests/Feature/CustomerBillingTest.php` | Pending |

---

## Testing Strategy

### Unit Tests

```php
<?php
// tests/Feature/ResourceSyncingTest.php

namespace Tests\Feature;

use App\Models\Central\Customer;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceSyncingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_syncs_to_tenant_user_on_create(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $tenant = $customer->createTenant([
            'domain' => 'john.test',
        ]);

        // Check tenant user was created via Resource Syncing
        $tenantUser = $tenant->run(fn () => User::where('global_id', $customer->global_id)->first());

        $this->assertNotNull($tenantUser);
        $this->assertEquals('John Doe', $tenantUser->name);
        $this->assertEquals('john@example.com', $tenantUser->email);
    }

    public function test_customer_update_syncs_to_tenant_users(): void
    {
        $customer = Customer::factory()->create();
        $tenant = $customer->createTenant(['domain' => 'test.test']);

        // Update customer
        $customer->update(['name' => 'Jane Doe']);

        // Check tenant user was updated
        $tenantUser = $tenant->run(fn () => User::where('global_id', $customer->global_id)->first());

        $this->assertEquals('Jane Doe', $tenantUser->name);
    }

    public function test_tenant_user_update_syncs_to_customer(): void
    {
        $customer = Customer::factory()->create();
        $tenant = $customer->createTenant(['domain' => 'test.test']);

        // Update tenant user
        $tenant->run(function () use ($customer) {
            $user = User::where('global_id', $customer->global_id)->first();
            $user->update(['name' => 'Updated Name']);
        });

        // Check customer was updated
        $this->assertEquals('Updated Name', $customer->fresh()->name);
    }

    public function test_unsynced_attributes_remain_independent(): void
    {
        $customer = Customer::factory()->create();
        $tenant = $customer->createTenant(['domain' => 'test.test']);

        // Update tenant-specific field
        $tenant->run(function () use ($customer) {
            $user = User::where('global_id', $customer->global_id)->first();
            $user->update(['department' => 'Engineering']);
        });

        // Customer should not have department field
        $this->assertNull($customer->fresh()->department ?? null);
    }

    public function test_customer_delete_cascades_to_tenant_users(): void
    {
        $customer = Customer::factory()->create();
        $tenant = $customer->createTenant(['domain' => 'test.test']);

        $globalId = $customer->global_id;

        // Delete customer
        $customer->delete();

        // Tenant user should be soft deleted
        $tenantUser = $tenant->run(fn () => User::withTrashed()->where('global_id', $globalId)->first());

        $this->assertTrue($tenantUser->trashed());
    }
}
```

### Test Users (Seeder)

| Email | Password | Role | Tenants |
|-------|----------|------|---------|
| `john@example.com` | `password` | Customer | 2 tenants (acme, startup) |
| `jane@example.com` | `password` | Customer | 1 tenant (design-co) |
| `billing@enterprise.com` | `password` | Customer | 1 tenant (enterprise) |

---

## Migration Guide

### Existing Data Migration

```php
<?php
// database/migrations/2024_12_10_100000_migrate_existing_tenants_to_customers.php

use App\Models\Central\Customer;
use App\Models\Central\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if no existing tenants
        if (Tenant::count() === 0) {
            return;
        }

        // For each existing tenant, create a Customer from the owner
        Tenant::with('users')->each(function (Tenant $tenant) {
            // Find owner in tenant database
            $owner = $tenant->run(fn () => \App\Models\Tenant\User::role('owner')->first());

            if (!$owner) {
                return;
            }

            // Create or find customer
            $customer = Customer::firstOrCreate(
                ['email' => $owner->email],
                [
                    'global_id' => Str::uuid()->toString(),
                    'name' => $owner->name,
                    'password' => $owner->password,
                    'email_verified_at' => $owner->email_verified_at,
                    'locale' => $owner->locale ?? 'pt_BR',
                    // Transfer Stripe data from tenant
                    'stripe_id' => $tenant->stripe_id,
                    'pm_type' => $tenant->pm_type,
                    'pm_last_four' => $tenant->pm_last_four,
                    'trial_ends_at' => $tenant->trial_ends_at,
                ]
            );

            // Link tenant to customer
            $tenant->update(['customer_id' => $customer->id]);

            // Create pivot
            $customer->tenants()->syncWithoutDetaching($tenant);

            // Update tenant user with global_id
            $tenant->run(function () use ($owner, $customer) {
                $owner->update(['global_id' => $customer->global_id]);
            });

            // Update subscriptions to point to customer
            $tenant->subscriptions()->update(['customer_id' => $customer->id]);
        });
    }

    public function down(): void
    {
        // This migration cannot be safely reversed
        // Manual intervention required
    }
};
```

---

## References

- [Stancl Tenancy v4 - Resource Syncing](https://v4.tenancyforlaravel.com/resource-syncing)
- [Laravel Cashier Stripe](https://laravel.com/docs/billing)
- [Laravel Authentication Guards](https://laravel.com/docs/authentication)

---

## Changelog

| Date | Version | Changes |
|------|---------|---------|
| 2024-12-10 | 1.0.0 | Initial document created |
