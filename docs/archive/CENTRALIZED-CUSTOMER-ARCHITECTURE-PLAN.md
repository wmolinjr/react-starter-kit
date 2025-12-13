# Centralized Customer Architecture Plan

## Problem Statement

### Current Architecture
Today, billing information is tied to **Tenants**, not **Users**:

```
┌─────────────────────────────────────────────────────────────────┐
│                        CURRENT STATE                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   Central\User ─────────┬──────────────────────────────────────│
│   (Admin only)          │                                       │
│                         │ NO billing relationship               │
│                         │                                       │
│   Tenant\User ──────────┼──────────────────────────────────────│
│   (Workspace member)    │ NO billing relationship               │
│                         │                                       │
│   Tenant ───────────────┴──► Stripe Customer                   │
│   (Organization)             - stripe_id                        │
│                              - subscriptions                    │
│                              - addon_subscriptions              │
│                              - addon_purchases                  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Pain Points

1. **User owns multiple tenants**: A person who creates 5 tenants has 5 separate Stripe Customers with no unified view
2. **No cross-tenant billing visibility**: User cannot see all their subscriptions in one place
3. **Future product expansion**: If we launch a new product (e.g., standalone tool, mobile app), there's no way to link purchases to the same customer
4. **Payment method duplication**: User must add credit card to each tenant separately
5. **No customer loyalty tracking**: Cannot offer discounts based on total customer spend across tenants
6. **Invoice fragmentation**: User receives separate invoices per tenant

### Desired Outcome

A user should be able to:
- See all their tenants/products from a single dashboard
- Manage payment methods once, use across all tenants
- View unified billing history
- Get loyalty benefits based on total spend
- Purchase new products linked to their existing account

---

## Solution Options

### Option A: Central Billable User (Recommended)

**Concept**: Create a new `Central\Customer` entity that represents a real person/payer, separate from both `Central\User` (admins) and `Tenant\User` (workspace members).

```
┌─────────────────────────────────────────────────────────────────┐
│                        OPTION A                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   Central\Customer ─────────────► Stripe Customer              │
│   (Real person/payer)             - stripe_id                   │
│        │                          - pm_type, pm_last_four       │
│        │                          - default_payment_method      │
│        │                                                        │
│        ├──────► owns many Tenants                              │
│        │        (tenant.customer_id FK)                         │
│        │                                                        │
│        ├──────► has many Subscriptions                         │
│        │        (all products, all tenants)                     │
│        │                                                        │
│        └──────► has many Purchases                             │
│                 (standalone products)                           │
│                                                                 │
│   Tenant ───────► customer_id (FK to Central\Customer)         │
│                   billing delegated to customer                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

#### Database Changes

```sql
-- New table: customers (Central Database)
CREATE TABLE customers (
    id UUID PRIMARY KEY,

    -- Identity
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(50),

    -- Stripe Billable fields
    stripe_id VARCHAR(255) UNIQUE,
    pm_type VARCHAR(50),
    pm_last_four VARCHAR(4),
    trial_ends_at TIMESTAMP,

    -- Address (for invoices)
    billing_address JSONB,  -- {line1, line2, city, state, postal_code, country}

    -- Preferences
    locale VARCHAR(10) DEFAULT 'en',
    currency VARCHAR(3) DEFAULT 'usd',
    tax_ids JSONB,  -- [{type: 'br_cnpj', value: '...'}]

    -- Metadata
    metadata JSONB,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP  -- Soft delete
);

-- Modify tenants table
ALTER TABLE tenants
    ADD COLUMN customer_id UUID REFERENCES customers(id),
    ADD COLUMN billing_email VARCHAR(255);  -- Override for this tenant's invoices

-- New table: customer_products (purchases outside tenants)
CREATE TABLE customer_products (
    id UUID PRIMARY KEY,
    customer_id UUID NOT NULL REFERENCES customers(id),
    product_type VARCHAR(50) NOT NULL,  -- 'standalone_tool', 'mobile_app', etc.
    product_id VARCHAR(255),  -- External product identifier
    stripe_subscription_id VARCHAR(255),
    stripe_subscription_item_id VARCHAR(255),
    status VARCHAR(20) NOT NULL,  -- 'active', 'canceled', 'expired'
    metadata JSONB,
    started_at TIMESTAMP,
    expires_at TIMESTAMP,
    canceled_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Modify subscriptions table (Cashier)
ALTER TABLE subscriptions
    ADD COLUMN customer_id UUID REFERENCES customers(id);
    -- Keep tenant_id for backward compatibility during migration

-- Modify addon_subscriptions table
ALTER TABLE addon_subscriptions
    ADD COLUMN customer_id UUID REFERENCES customers(id);
    -- Keep tenant_id for existing addon logic

-- Modify addon_purchases table
ALTER TABLE addon_purchases
    ADD COLUMN customer_id UUID REFERENCES customers(id);
```

#### Model Structure

```php
// app/Models/Central/Customer.php
class Customer extends Model
{
    use Billable, HasUuids, SoftDeletes;

    protected $connection = 'central';

    // Relationships
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function addonSubscriptions(): HasMany
    {
        return $this->hasMany(AddonSubscription::class);
    }

    public function addonPurchases(): HasMany
    {
        return $this->hasMany(AddonPurchase::class);
    }

    public function standaloneProducts(): HasMany
    {
        return $this->hasMany(CustomerProduct::class);
    }

    // Billing helpers
    public function getAllSubscriptions(): Collection
    {
        return $this->subscriptions()
            ->with('tenant')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getTotalMonthlySpend(): int
    {
        // Sum all active subscriptions across all tenants
    }

    public function getUnifiedInvoices(): Collection
    {
        // All invoices from Stripe for this customer
    }
}
```

#### Tenant Model Changes

```php
// app/Models/Central/Tenant.php
class Tenant extends Model
{
    // Remove Billable trait from Tenant
    // use Billable; // REMOVED

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // Delegate billing to customer
    public function getBillable(): Customer
    {
        return $this->customer;
    }

    // Keep subscription relationship but through customer
    public function subscription(): HasOneThrough
    {
        return $this->hasOneThrough(
            Subscription::class,
            Customer::class,
            'id',           // customers.id
            'customer_id',  // subscriptions.customer_id
            'customer_id',  // tenants.customer_id
            'id'            // customers.id
        )->where('subscriptions.tenant_id', $this->id);
    }
}
```

#### Customer Portal (New Feature)

```
┌─────────────────────────────────────────────────────────────────┐
│                   CUSTOMER PORTAL                               │
│                   (New central area)                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   /account                                                      │
│   ├── /profile          - Name, email, address                 │
│   ├── /payment-methods  - Add/remove cards (shared)            │
│   ├── /subscriptions    - All tenants + products               │
│   ├── /invoices         - Unified invoice history              │
│   └── /tenants          - List of owned tenants                │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

#### Pros
- Clean separation: Customer = payer, Tenant = workspace
- Single Stripe Customer per real person
- Unified billing, invoices, payment methods
- Easy to add new products
- Matches Stripe's recommended model
- Customer can share payment methods across tenants

#### Cons
- Significant migration effort (existing tenants → customers)
- Need to handle edge cases (tenant transferred to different customer)
- Requires new authentication flow for customer portal
- More complex permission model

#### Migration Strategy

```php
// Phase 1: Create customers from existing tenant owners
foreach (Tenant::with('owner')->get() as $tenant) {
    $owner = $tenant->owner; // Get owner from tenant database

    $customer = Customer::firstOrCreate(
        ['email' => $owner->email],
        [
            'name' => $owner->name,
            'stripe_id' => $tenant->stripe_id, // Transfer Stripe customer
            'pm_type' => $tenant->pm_type,
            'pm_last_four' => $tenant->pm_last_four,
        ]
    );

    $tenant->update(['customer_id' => $customer->id]);
}

// Phase 2: Update subscriptions
Subscription::whereNotNull('tenant_id')->each(function ($sub) {
    $sub->update(['customer_id' => $sub->tenant->customer_id]);
});
```

---

### Option B: Federated User as Billable

**Concept**: Use the existing `federated_user_id` concept to link Tenant\Users across databases, then make a Central\FederatedUser the billable entity.

```
┌─────────────────────────────────────────────────────────────────┐
│                        OPTION B                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   Central\FederatedUser ────────► Stripe Customer              │
│   (Cross-tenant identity)         - stripe_id                   │
│        │                          - billing info                │
│        │                                                        │
│        ├──────► linked to Tenant\User (via federated_user_id)  │
│        │        in multiple tenant databases                    │
│        │                                                        │
│        └──────► owns Tenants where user is owner               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

#### Database Changes

```sql
-- New table: federated_users (Central Database)
CREATE TABLE federated_users (
    id UUID PRIMARY KEY,

    -- Identity (canonical version)
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255),

    -- Stripe Billable
    stripe_id VARCHAR(255) UNIQUE,
    pm_type VARCHAR(50),
    pm_last_four VARCHAR(4),

    -- Sync metadata
    sync_strategy VARCHAR(20) DEFAULT 'master_wins',
    last_synced_at TIMESTAMP,

    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Modify tenants table
ALTER TABLE tenants
    ADD COLUMN owner_federated_user_id UUID REFERENCES federated_users(id);
```

#### Pros
- Builds on existing federation concept
- User identity already spans tenants
- Less new infrastructure

#### Cons
- Conflates user sync with billing
- Federation is for multi-branch companies, not multi-product billing
- More complex: federated user ≠ payer in all cases
- Edge case: company owner delegates billing to finance person

---

### Option C: Tenant Remains Billable + Customer Metadata

**Concept**: Keep Tenant as billable but add a `billing_customer_email` field to group tenants by payer.

```
┌─────────────────────────────────────────────────────────────────┐
│                        OPTION C                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   Tenant 1 ──────► Stripe Customer (cus_A)                     │
│   billing_email: john@example.com                               │
│                                                                 │
│   Tenant 2 ──────► Stripe Customer (cus_B)                     │
│   billing_email: john@example.com                               │
│                                                                 │
│   (Grouped by email in reporting, not in Stripe)               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

#### Database Changes

```sql
-- Minimal changes
ALTER TABLE tenants
    ADD COLUMN billing_customer_email VARCHAR(255),
    ADD INDEX idx_billing_email (billing_customer_email);
```

#### Customer Dashboard (Virtual Grouping)

```php
class CustomerDashboardService
{
    public function getTenantsForCustomer(string $email): Collection
    {
        return Tenant::where('billing_customer_email', $email)->get();
    }

    public function getTotalSpend(string $email): int
    {
        return Tenant::where('billing_customer_email', $email)
            ->with('subscription')
            ->get()
            ->sum(fn ($t) => $t->subscription?->price ?? 0);
    }
}
```

#### Pros
- Minimal changes to existing architecture
- Quick to implement
- No migration risk

#### Cons
- Multiple Stripe Customers per person (payment methods not shared)
- No real unified billing in Stripe
- Reporting only, not true integration
- Doesn't solve future product expansion
- "Band-aid" solution

---

### Option D: Stripe Connect (Platform Model)

**Concept**: Use Stripe Connect where your platform is the "super account" and each tenant is a Connected Account.

```
┌─────────────────────────────────────────────────────────────────┐
│                        OPTION D                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   Platform (Your Stripe Account)                               │
│        │                                                        │
│        ├──────► Customer (real person)                         │
│        │        - stripe_id                                     │
│        │        - subscriptions to YOUR products               │
│        │                                                        │
│        └──────► Connected Account (tenant)                     │
│                 - For tenants that SELL to their customers     │
│                 - NOT for your billing to them                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Note**: Stripe Connect is designed for marketplaces where your tenants SELL products, not for billing your tenants. This option is **NOT recommended** for your use case unless tenants will have their own customers.

#### When Connect Makes Sense
- Tenant is a business that sells to end customers
- You take a platform fee from their sales
- Example: Shopify, Uber, Airbnb

#### When Connect Doesn't Make Sense
- You're billing tenants for using YOUR software
- Tenants don't have their own customers in the payment flow
- Your use case: SaaS subscription billing

---

## Recommendation

### Primary Recommendation: Option A (Central Billable Customer)

**Reasons:**

1. **Clean Architecture**: Separates the concepts of:
   - `Central\User`: Platform administrators
   - `Central\Customer`: People who pay for things
   - `Tenant`: Workspaces/organizations
   - `Tenant\User`: Workspace members

2. **Stripe Best Practices**: Matches Stripe's model where one `Customer` = one real person/entity with multiple subscriptions

3. **Future-Proof**: Easy to add new products:
   ```php
   $customer->subscriptions(); // All products
   $customer->tenants();       // SaaS tenants
   $customer->standaloneProducts(); // Other products
   ```

4. **Unified Experience**:
   - Single payment method management
   - Consolidated invoices
   - Cross-product discounts/loyalty

5. **Migration Path**: Can be done incrementally with backward compatibility

### Implementation Phases

#### Phase 1: Foundation (2-3 weeks)
- Create `customers` table and `Customer` model
- Add `customer_id` to `tenants` table (nullable)
- Create `CustomerService` for CRUD operations
- Migration script: create customers from existing tenant owners

#### Phase 2: Billing Migration (2-3 weeks)
- Move `Billable` trait from `Tenant` to `Customer`
- Update `subscriptions` table to reference `customer_id`
- Update checkout flows to use Customer as billable
- Update webhook handlers

#### Phase 3: Customer Portal (2-3 weeks)
- New routes: `/account/*`
- Customer authentication (magic link or password)
- Dashboard: tenants list, subscriptions, invoices
- Payment method management

#### Phase 4: New Product Support (1-2 weeks)
- `customer_products` table for non-tenant purchases
- Generic product checkout flow
- Unified subscription management

---

## Option E: Stancl Resource Syncing (Recommended - Simplified)

**Descoberta**: O Stancl Tenancy v4 já possui um sistema de **Resource Syncing** que sincroniza recursos entre banco central e tenant automaticamente. Podemos usar isso para criar um `CentralCustomer` que sincroniza com o `Tenant\User` (owner).

### Conceito

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    OPTION E: RESOURCE SYNCING                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   Central\Customer (SyncMaster) ◄────────────► Tenant\User (Syncable)      │
│   - implements SyncMaster                       - implements Syncable       │
│   - use ResourceSyncing, CentralConnection      - use ResourceSyncing      │
│   - use Billable (Cashier)                      - linked via global_id     │
│                                                                             │
│   Synced Attributes:                                                        │
│   - global_id, name, email, password, locale                               │
│                                                                             │
│   NOT Synced (local to tenant):                                            │
│   - role, permissions, department, employee_id                             │
│                                                                             │
│   Billing stays on Central\Customer:                                       │
│   - stripe_id, pm_type, pm_last_four                                       │
│   - subscriptions, addon_subscriptions, purchases                          │
│                                                                             │
│   Tenant links to Customer:                                                │
│   - tenant.customer_id (FK) = who pays                                     │
│   - tenant_resources pivot table (Stancl built-in)                         │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Como Funciona o Resource Syncing

1. **CentralCustomer** (SyncMaster) - Banco Central
   - Entidade billable com Stripe
   - Possui relacionamento `tenants()` via pivot `tenant_resources`
   - Quando atualizado, sincroniza para todos os Tenant\Users vinculados

2. **Tenant\User** (Syncable) - Banco do Tenant
   - Usuário normal do workspace
   - Quando é owner, tem `global_id` que vincula ao CentralCustomer
   - Quando atualizado, sincroniza de volta para CentralCustomer

3. **Pivot Table** (`tenant_resources`) - Banco Central
   - Mapeia CentralCustomer ↔ Tenants
   - Stancl fornece migration pronta

### Vantagens sobre Option A

| Aspecto | Option A (Manual) | Option E (Resource Syncing) |
|---------|-------------------|----------------------------|
| Sincronização | Manual, custom code | Automática via eventos |
| Infraestrutura | Nova tabela `customers` isolada | Usa sistema existente do Stancl |
| Manutenção | Código custom para sync | Listeners built-in |
| Integração | Precisa criar tudo | Já integrado com tenancy |
| Complexidade | Alta | Média |

### Fluxo de Criação de Tenant

```
1. Usuário se registra no portal central
   → Cria Central\Customer (com Billable)
   → Stripe Customer criado

2. Customer cria um tenant
   → Tenant criado com customer_id
   → Pivot tenant_resources criado
   → Job cria Tenant\User (owner) no banco do tenant
   → Tenant\User tem global_id = Customer.global_id

3. Sincronização automática
   → Customer atualiza email no portal
   → Evento SyncedResourceSaved disparado
   → Listener UpdateOrCreateSyncedResource
   → Tenant\User atualizado em todos os tenants do customer
```

### Estrutura de Models

```php
// app/Models/Central/Customer.php
namespace App\Models\Central;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Cashier\Billable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Stancl\Tenancy\Database\Concerns\ResourceSyncing;
use Stancl\Tenancy\Contracts\SyncMaster;

class Customer extends Authenticatable implements SyncMaster
{
    use Billable, CentralConnection, ResourceSyncing, HasUuids, SoftDeletes;

    protected $table = 'customers';

    protected $fillable = [
        'global_id', 'name', 'email', 'password', 'phone',
        'billing_address', 'locale', 'currency', 'tax_ids',
    ];

    // ─────────────────────────────────────────────────────────────
    // SyncMaster Implementation
    // ─────────────────────────────────────────────────────────────

    public function getTenantModelName(): string
    {
        return \App\Models\Tenant\User::class;
    }

    public function getCentralModelName(): string
    {
        return static::class;
    }

    public function getSyncedAttributeNames(): array
    {
        return [
            'global_id',
            'name',
            'email',
            'password',
            'locale',
        ];
        // NOT synced: stripe_id, billing_address, tax_ids (central only)
        // NOT synced: role, permissions (tenant only)
    }

    /**
     * Tenants this customer has access to (via pivot)
     * Uses Stancl's built-in tenant_resources table
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(
            Tenant::class,
            'tenant_resources',    // Stancl's pivot table
            'global_id',           // Customer's global_id
            'tenant_id',           // Tenant's id
            'global_id',           // Local key
            'id'                   // Related key
        )->using(\Stancl\Tenancy\Database\TenantPivot::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Owned Tenants (where customer is the payer)
    // ─────────────────────────────────────────────────────────────

    public function ownedTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'customer_id');
    }

    // ─────────────────────────────────────────────────────────────
    // Billing (Cashier)
    // ─────────────────────────────────────────────────────────────

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // ... resto dos métodos de billing
}
```

```php
// app/Models/Tenant/User.php (atualizado)
namespace App\Models\Tenant;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Stancl\Tenancy\Database\Concerns\ResourceSyncing;
use Stancl\Tenancy\Contracts\Syncable;

class User extends Authenticatable implements Syncable
{
    use ResourceSyncing, HasUuids, SoftDeletes;

    protected $fillable = [
        'global_id',  // Link to Central\Customer
        'name',
        'email',
        'password',
        'locale',
        // Tenant-specific (NOT synced)
        'department',
        'employee_id',
        'custom_settings',
    ];

    // ─────────────────────────────────────────────────────────────
    // Syncable Implementation
    // ─────────────────────────────────────────────────────────────

    public function getCentralModelName(): string
    {
        return \App\Models\Central\Customer::class;
    }

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
     * Get the central customer for this user (if owner)
     */
    public function getCentralCustomer(): ?Customer
    {
        if (!$this->global_id) {
            return null;
        }

        return \App\Models\Central\Customer::where('global_id', $this->global_id)->first();
    }

    /**
     * Check if this user is linked to a central customer (owner)
     */
    public function isLinkedToCustomer(): bool
    {
        return $this->global_id !== null;
    }

    // ... resto dos métodos existentes
}
```

### Database Changes (Simplificado)

```php
// database/migrations/xxxx_create_customers_table.php
Schema::create('customers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('global_id')->unique();  // Para Resource Syncing

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

    // Billing
    $table->json('billing_address')->nullable();
    $table->string('locale', 10)->default('en');
    $table->string('currency', 3)->default('brl');
    $table->json('tax_ids')->nullable();

    // Auth
    $table->timestamp('email_verified_at')->nullable();
    $table->text('two_factor_secret')->nullable();
    $table->text('two_factor_recovery_codes')->nullable();
    $table->timestamp('two_factor_confirmed_at')->nullable();

    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

// database/migrations/xxxx_add_customer_id_to_tenants_table.php
Schema::table('tenants', function (Blueprint $table) {
    $table->foreignUuid('customer_id')
        ->nullable()
        ->constrained('customers')
        ->nullOnDelete();

    $table->string('payment_method_id')->nullable();  // Override
});

// database/migrations/xxxx_add_global_id_to_tenant_users_table.php (tenant migration)
Schema::table('users', function (Blueprint $table) {
    $table->string('global_id')->nullable()->unique()->after('id');
});

// Publish Stancl's tenant_resources migration
// php artisan vendor:publish --tag=resource-syncing-migrations
```

### Event Listeners (TenancyServiceProvider)

```php
// app/Providers/TenancyServiceProvider.php
public function events(): array
{
    return [
        // ... existing events ...

        // Resource Syncing Events (Stancl built-in)
        \Stancl\Tenancy\ResourceSyncing\Events\SyncedResourceSaved::class => [
            \Stancl\Tenancy\ResourceSyncing\Listeners\UpdateOrCreateSyncedResource::class,
        ],
        \Stancl\Tenancy\ResourceSyncing\Events\SyncMasterDeleted::class => [
            \Stancl\Tenancy\ResourceSyncing\Listeners\DeleteResourcesInTenants::class,
        ],
        \Stancl\Tenancy\ResourceSyncing\Events\CentralResourceAttachedToTenant::class => [
            \Stancl\Tenancy\ResourceSyncing\Listeners\CreateTenantResource::class,
        ],
        \Stancl\Tenancy\ResourceSyncing\Events\CentralResourceDetachedFromTenant::class => [
            \Stancl\Tenancy\ResourceSyncing\Listeners\DeleteResourceInTenants::class,
        ],
    ];
}
```

### Fluxo de Registro/Login

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         REGISTRATION FLOW                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. User visits /account/register                                          │
│     └─► Creates Central\Customer (with global_id)                          │
│         └─► Stripe Customer created (via Cashier)                          │
│                                                                             │
│  2. Customer creates first tenant                                          │
│     └─► Tenant created with customer_id                                    │
│         └─► Pivot: tenant_resources (global_id ↔ tenant_id)               │
│             └─► Job: CreateTenantOwner                                     │
│                 └─► Tenant\User created with same global_id                │
│                     └─► User gets 'owner' role                             │
│                                                                             │
│  3. Customer can now:                                                       │
│     - Login at /account (Customer Portal)                                  │
│     - Login at tenant.domain (as Tenant\User owner)                        │
│     - Both accounts stay in sync via Resource Syncing                      │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Comparação Final

| Feature | Option A | Option E (Stancl) |
|---------|----------|-------------------|
| Nova tabela customers | ✅ | ✅ |
| Sync manual customer↔user | ✅ Custom jobs | ✅ Built-in events |
| Pivot table | Custom | tenant_resources (Stancl) |
| Eventos de sync | Custom | Built-in listeners |
| Soft delete cascade | Manual | Built-in |
| Restore cascade | Manual | Built-in |
| Conditional sync | Manual | shouldSync() method |
| Queue support | Manual | Built-in |
| Testado/Mantido | Por nós | Stancl community |

### Recomendação Final

**Option E (Resource Syncing)** é superior porque:
1. Usa infraestrutura testada do Stancl
2. Sincronização automática bidirecional
3. Menos código custom para manter
4. Eventos built-in para todos os casos
5. Suporte a queue para produção
6. Cascade delete/restore automático

---

## Final Design Decisions

| Decision | Choice |
|----------|--------|
| **Solution** | **Option E: Stancl Resource Syncing** |
| **Customer Authentication** | Separate login (email + password or magic link) |
| **Payment Method Sharing** | Selectable per tenant (default + overrides) |
| **Invoice Grouping** | One invoice per tenant (current behavior) |
| **Tenant Transfer** | Full self-service transfer (with transfer fee consideration) |
| **Customer = Owner** | Yes, owner = payer always |
| **Migration Strategy** | Fresh implementation, update seeders, `migrate:fresh --seed` |

---

## Implementation Plan

### Architecture Overview (Final)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           FINAL ARCHITECTURE                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   Central\Customer ──────────────────► Stripe Customer                     │
│   (Billable entity)                    - One per real person               │
│        │                               - stripe_id                          │
│        │                               - payment_methods[]                  │
│        │                                                                    │
│        ├──► tenants() ──────────────► Tenant 1 (workspace)                 │
│        │    (HasMany)                  - customer_id (FK)                   │
│        │                               - payment_method_id (override)       │
│        │                               - plan_id                            │
│        │                                                                    │
│        │                           ► Tenant 2 (workspace)                  │
│        │                               - customer_id (FK)                   │
│        │                               - payment_method_id (override)       │
│        │                               - plan_id                            │
│        │                                                                    │
│        ├──► subscriptions() ────────► Per-tenant subscriptions             │
│        │    (HasMany)                  - customer_id + tenant_id            │
│        │                                                                    │
│        ├──► paymentMethods() ───────► Stripe Payment Methods               │
│        │    (via Cashier)              - Shared across tenants             │
│        │                               - Default + per-tenant override      │
│        │                                                                    │
│        └──► standaloneProducts() ───► Future products (no tenant)          │
│             (HasMany)                                                       │
│                                                                             │
│   Authentication: Separate guard 'customer'                                │
│   Portal: /account/* routes                                                │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Database Schema

#### Migration: Create Customers Table

```php
// database/migrations/xxxx_create_customers_table.php
Schema::create('customers', function (Blueprint $table) {
    $table->uuid('id')->primary();

    // Identity
    $table->string('name');
    $table->string('email')->unique();
    $table->string('phone')->nullable();
    $table->string('password');  // For separate login
    $table->rememberToken();

    // Stripe Billable (Laravel Cashier)
    $table->string('stripe_id')->nullable()->unique();
    $table->string('pm_type')->nullable();
    $table->string('pm_last_four', 4)->nullable();
    $table->timestamp('trial_ends_at')->nullable();

    // Billing Address
    $table->json('billing_address')->nullable();
    // Structure: {line1, line2, city, state, postal_code, country}

    // Preferences
    $table->string('locale', 10)->default('en');
    $table->string('currency', 3)->default('brl');
    $table->json('tax_ids')->nullable();
    // Structure: [{type: 'br_cnpj', value: '12.345.678/0001-90'}]

    // Email verification & 2FA
    $table->timestamp('email_verified_at')->nullable();
    $table->text('two_factor_secret')->nullable();
    $table->text('two_factor_recovery_codes')->nullable();
    $table->timestamp('two_factor_confirmed_at')->nullable();

    // Metadata
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

#### Migration: Modify Tenants Table

```php
// database/migrations/xxxx_add_customer_id_to_tenants_table.php
Schema::table('tenants', function (Blueprint $table) {
    // Customer relationship
    $table->foreignUuid('customer_id')
        ->nullable()
        ->constrained('customers')
        ->nullOnDelete();

    // Payment method override (Stripe Payment Method ID)
    // If null, uses customer's default payment method
    $table->string('payment_method_id')->nullable();

    // Remove Billable columns from Tenant (move to Customer)
    // Keep these for now, remove in cleanup phase:
    // $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
});
```

#### Migration: Modify Subscriptions Table

```php
// database/migrations/xxxx_add_customer_id_to_subscriptions_table.php
Schema::table('subscriptions', function (Blueprint $table) {
    $table->foreignUuid('customer_id')
        ->nullable()
        ->constrained('customers')
        ->cascadeOnDelete();

    // tenant_id remains for identifying which tenant the subscription is for
    // Now: customer_id = who pays, tenant_id = what workspace
});
```

#### Migration: Create Customer Products Table

```php
// database/migrations/xxxx_create_customer_products_table.php
Schema::create('customer_products', function (Blueprint $table) {
    $table->uuid('id')->primary();

    $table->foreignUuid('customer_id')
        ->constrained('customers')
        ->cascadeOnDelete();

    $table->string('product_type');  // 'mobile_app', 'standalone_tool', etc.
    $table->string('product_slug');  // 'voting-app-pro', 'analytics-dashboard'

    // Stripe references
    $table->string('stripe_subscription_id')->nullable();
    $table->string('stripe_subscription_item_id')->nullable();
    $table->string('stripe_price_id')->nullable();

    // Status
    $table->string('status');  // 'active', 'canceled', 'expired', 'trialing'
    $table->integer('quantity')->default(1);

    // Dates
    $table->timestamp('started_at')->nullable();
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('canceled_at')->nullable();

    // Metadata
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['customer_id', 'product_type']);
    $table->index(['customer_id', 'status']);
});
```

### Models

#### Customer Model

```php
<?php
// app/Models/Central/Customer.php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class Customer extends Authenticatable
{
    use Billable, HasUuids, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $connection = 'central';

    protected $fillable = [
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

    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'billing_address' => 'array',
        'tax_ids' => 'array',
        'metadata' => 'array',
        'password' => 'hashed',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function addonSubscriptions(): HasMany
    {
        return $this->hasMany(AddonSubscription::class);
    }

    public function addonPurchases(): HasMany
    {
        return $this->hasMany(AddonPurchase::class);
    }

    public function standaloneProducts(): HasMany
    {
        return $this->hasMany(CustomerProduct::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Tenant Management
    // ─────────────────────────────────────────────────────────────

    public function ownsTenant(Tenant $tenant): bool
    {
        return $this->tenants()->where('id', $tenant->id)->exists();
    }

    public function getActiveTenants(): Collection
    {
        return $this->tenants()
            ->with(['plan', 'subscription'])
            ->orderBy('name')
            ->get();
    }

    // ─────────────────────────────────────────────────────────────
    // Billing Helpers
    // ─────────────────────────────────────────────────────────────

    public function getTotalMonthlySpend(): int
    {
        $tenantSpend = $this->subscriptions()
            ->where('stripe_status', 'active')
            ->with('items')
            ->get()
            ->sum(function ($subscription) {
                return $subscription->items->sum('quantity') *
                    ($subscription->items->first()?->price ?? 0);
            });

        $productSpend = $this->standaloneProducts()
            ->where('status', 'active')
            ->sum('price');

        return $tenantSpend + $productSpend;
    }

    public function getAllActiveSubscriptions(): Collection
    {
        return $this->subscriptions()
            ->where('stripe_status', 'active')
            ->with('tenant')
            ->get();
    }

    // ─────────────────────────────────────────────────────────────
    // Payment Methods
    // ─────────────────────────────────────────────────────────────

    public function getPaymentMethodForTenant(Tenant $tenant): ?PaymentMethod
    {
        // If tenant has override, use that
        if ($tenant->payment_method_id) {
            return $this->findPaymentMethod($tenant->payment_method_id);
        }

        // Otherwise use customer's default
        return $this->defaultPaymentMethod();
    }

    public function setPaymentMethodForTenant(Tenant $tenant, string $paymentMethodId): void
    {
        $tenant->update(['payment_method_id' => $paymentMethodId]);
    }

    public function clearPaymentMethodForTenant(Tenant $tenant): void
    {
        $tenant->update(['payment_method_id' => null]);
    }

    // ─────────────────────────────────────────────────────────────
    // Stripe Customization
    // ─────────────────────────────────────────────────────────────

    public function stripeCustomerName(): string
    {
        return $this->name;
    }

    public function stripeEmail(): string
    {
        return $this->email;
    }

    public function stripeAddress(): array
    {
        $address = $this->billing_address ?? [];

        return [
            'line1' => $address['line1'] ?? '',
            'line2' => $address['line2'] ?? null,
            'city' => $address['city'] ?? '',
            'state' => $address['state'] ?? '',
            'postal_code' => $address['postal_code'] ?? '',
            'country' => $address['country'] ?? 'BR',
        ];
    }

    public function taxIds(): array
    {
        return collect($this->tax_ids ?? [])
            ->map(fn ($tax) => [
                'type' => $tax['type'],
                'value' => $tax['value'],
            ])
            ->toArray();
    }
}
```

#### Updated Tenant Model

```php
<?php
// app/Models/Central/Tenant.php (relevant changes only)

class Tenant extends Model
{
    // REMOVE: use Billable;  // No longer billable directly

    protected $fillable = [
        // ... existing fields ...
        'customer_id',
        'payment_method_id',
    ];

    // ─────────────────────────────────────────────────────────────
    // Customer Relationship
    // ─────────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the billable entity for this tenant (the customer)
     */
    public function getBillable(): Customer
    {
        return $this->customer;
    }

    /**
     * Get the active subscription for this tenant
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('type', 'default')
            ->latest();
    }

    /**
     * Check if tenant has active subscription
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription?->active() ?? false;
    }

    // ─────────────────────────────────────────────────────────────
    // Payment Method (with override support)
    // ─────────────────────────────────────────────────────────────

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->customer?->getPaymentMethodForTenant($this);
    }

    public function hasPaymentMethodOverride(): bool
    {
        return $this->payment_method_id !== null;
    }
}
```

### Authentication Setup

#### Guard Configuration

```php
// config/auth.php
'guards' => [
    // ... existing guards ...

    'customer' => [
        'driver' => 'session',
        'provider' => 'customers',
    ],
],

'providers' => [
    // ... existing providers ...

    'customers' => [
        'driver' => 'eloquent',
        'model' => App\Models\Central\Customer::class,
    ],
],

'passwords' => [
    // ... existing ...

    'customers' => [
        'provider' => 'customers',
        'table' => 'customer_password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],
],
```

#### Password Reset Tokens Migration

```php
// database/migrations/xxxx_create_customer_password_reset_tokens_table.php
Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
    $table->string('email')->primary();
    $table->string('token');
    $table->timestamp('created_at')->nullable();
});
```

### Routes Structure

```php
// routes/customer.php (new file)

use Illuminate\Support\Facades\Route;

Route::domain(config('app.central_domain'))->group(function () {

    // Guest routes
    Route::middleware('guest:customer')->prefix('account')->group(function () {
        Route::get('login', [CustomerAuthController::class, 'showLogin'])
            ->name('customer.login');
        Route::post('login', [CustomerAuthController::class, 'login']);

        Route::get('register', [CustomerAuthController::class, 'showRegister'])
            ->name('customer.register');
        Route::post('register', [CustomerAuthController::class, 'register']);

        Route::get('forgot-password', [CustomerForgotPasswordController::class, 'show'])
            ->name('customer.password.request');
        Route::post('forgot-password', [CustomerForgotPasswordController::class, 'send']);

        Route::get('reset-password/{token}', [CustomerResetPasswordController::class, 'show'])
            ->name('customer.password.reset');
        Route::post('reset-password', [CustomerResetPasswordController::class, 'reset']);

        // Magic link authentication
        Route::get('magic-link', [CustomerMagicLinkController::class, 'show'])
            ->name('customer.magic-link');
        Route::post('magic-link', [CustomerMagicLinkController::class, 'send']);
        Route::get('magic-link/{token}', [CustomerMagicLinkController::class, 'verify'])
            ->name('customer.magic-link.verify');
    });

    // Authenticated routes
    Route::middleware(['auth:customer', 'verified:customer'])->prefix('account')->group(function () {

        // Dashboard
        Route::get('/', [CustomerDashboardController::class, 'index'])
            ->name('customer.dashboard');

        // Profile
        Route::get('profile', [CustomerProfileController::class, 'edit'])
            ->name('customer.profile');
        Route::put('profile', [CustomerProfileController::class, 'update']);

        // Tenants (workspaces)
        Route::get('tenants', [CustomerTenantsController::class, 'index'])
            ->name('customer.tenants');
        Route::get('tenants/create', [CustomerTenantsController::class, 'create'])
            ->name('customer.tenants.create');
        Route::post('tenants', [CustomerTenantsController::class, 'store']);
        Route::get('tenants/{tenant}', [CustomerTenantsController::class, 'show'])
            ->name('customer.tenants.show');

        // Tenant Transfer
        Route::get('tenants/{tenant}/transfer', [TenantTransferController::class, 'show'])
            ->name('customer.tenants.transfer');
        Route::post('tenants/{tenant}/transfer', [TenantTransferController::class, 'initiate']);
        Route::post('tenants/{tenant}/transfer/confirm', [TenantTransferController::class, 'confirm']);

        // Payment Methods
        Route::get('payment-methods', [CustomerPaymentMethodsController::class, 'index'])
            ->name('customer.payment-methods');
        Route::post('payment-methods', [CustomerPaymentMethodsController::class, 'store']);
        Route::delete('payment-methods/{paymentMethod}', [CustomerPaymentMethodsController::class, 'destroy']);
        Route::post('payment-methods/{paymentMethod}/default', [CustomerPaymentMethodsController::class, 'setDefault']);

        // Per-tenant payment method override
        Route::put('tenants/{tenant}/payment-method', [CustomerPaymentMethodsController::class, 'setForTenant']);
        Route::delete('tenants/{tenant}/payment-method', [CustomerPaymentMethodsController::class, 'clearForTenant']);

        // Subscriptions
        Route::get('subscriptions', [CustomerSubscriptionsController::class, 'index'])
            ->name('customer.subscriptions');

        // Invoices
        Route::get('invoices', [CustomerInvoicesController::class, 'index'])
            ->name('customer.invoices');
        Route::get('invoices/{invoice}/download', [CustomerInvoicesController::class, 'download'])
            ->name('customer.invoices.download');

        // Logout
        Route::post('logout', [CustomerAuthController::class, 'logout'])
            ->name('customer.logout');
    });
});
```

### Tenant Transfer Feature

```php
<?php
// app/Services/Central/TenantTransferService.php

namespace App\Services\Central;

use App\Models\Central\Customer;
use App\Models\Central\Tenant;
use App\Models\Central\TenantTransfer;
use App\Notifications\TenantTransferInvitation;
use App\Notifications\TenantTransferCompleted;
use Illuminate\Support\Str;

class TenantTransferService
{
    /**
     * Transfer fee percentage (e.g., 5% of remaining subscription value)
     */
    public const TRANSFER_FEE_PERCENT = 5;

    /**
     * Initiate a tenant transfer to a new owner
     */
    public function initiateTransfer(
        Tenant $tenant,
        Customer $fromCustomer,
        string $toEmail
    ): TenantTransfer {
        // Validate ownership
        if ($tenant->customer_id !== $fromCustomer->id) {
            throw new \Exception('You do not own this tenant.');
        }

        // Calculate transfer fee
        $transferFee = $this->calculateTransferFee($tenant);

        // Create transfer record
        $transfer = TenantTransfer::create([
            'tenant_id' => $tenant->id,
            'from_customer_id' => $fromCustomer->id,
            'to_email' => $toEmail,
            'transfer_fee' => $transferFee,
            'token' => Str::random(64),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        // Send invitation to recipient
        // They must create/login to customer account to accept
        Notification::route('mail', $toEmail)
            ->notify(new TenantTransferInvitation($transfer));

        return $transfer;
    }

    /**
     * Accept a tenant transfer
     */
    public function acceptTransfer(
        TenantTransfer $transfer,
        Customer $toCustomer
    ): Tenant {
        if ($transfer->status !== 'pending') {
            throw new \Exception('Transfer is no longer pending.');
        }

        if ($transfer->isExpired()) {
            throw new \Exception('Transfer invitation has expired.');
        }

        if ($transfer->to_email !== $toCustomer->email) {
            throw new \Exception('Transfer was sent to a different email.');
        }

        DB::transaction(function () use ($transfer, $toCustomer) {
            $tenant = $transfer->tenant;
            $fromCustomer = $transfer->fromCustomer;

            // 1. Charge transfer fee to original owner (if applicable)
            if ($transfer->transfer_fee > 0) {
                $fromCustomer->charge(
                    $transfer->transfer_fee,
                    $fromCustomer->defaultPaymentMethod()?->id,
                    [
                        'description' => "Transfer fee for {$tenant->name}",
                        'metadata' => [
                            'tenant_id' => $tenant->id,
                            'transfer_id' => $transfer->id,
                        ],
                    ]
                );
            }

            // 2. Cancel existing subscription (new owner will need to subscribe)
            if ($subscription = $tenant->subscription) {
                $subscription->cancelNow();
            }

            // 3. Transfer tenant ownership
            $tenant->update([
                'customer_id' => $toCustomer->id,
                'payment_method_id' => null, // Reset payment method override
            ]);

            // 4. Update transfer record
            $transfer->update([
                'to_customer_id' => $toCustomer->id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // 5. Notify both parties
            $fromCustomer->notify(new TenantTransferCompleted($transfer, 'sender'));
            $toCustomer->notify(new TenantTransferCompleted($transfer, 'recipient'));
        });

        return $transfer->tenant->fresh();
    }

    /**
     * Calculate transfer fee based on remaining subscription value
     */
    protected function calculateTransferFee(Tenant $tenant): int
    {
        $subscription = $tenant->subscription;

        if (!$subscription || !$subscription->active()) {
            return 0;
        }

        // Get remaining value in current billing period
        $currentPeriodEnd = $subscription->asStripeSubscription()->current_period_end;
        $now = now()->timestamp;
        $periodLength = $subscription->asStripeSubscription()->current_period_end -
            $subscription->asStripeSubscription()->current_period_start;

        $remainingDays = max(0, $currentPeriodEnd - $now) / 86400;
        $totalDays = $periodLength / 86400;

        $remainingValue = ($remainingDays / $totalDays) *
            $subscription->items->sum(fn ($item) => $item->quantity * 100); // Assuming price in cents

        return (int) ($remainingValue * (self::TRANSFER_FEE_PERCENT / 100));
    }
}
```

### Tenant Transfer Migration

```php
// database/migrations/xxxx_create_tenant_transfers_table.php
Schema::create('tenant_transfers', function (Blueprint $table) {
    $table->uuid('id')->primary();

    $table->foreignUuid('tenant_id')
        ->constrained('tenants')
        ->cascadeOnDelete();

    $table->foreignUuid('from_customer_id')
        ->constrained('customers');

    $table->string('to_email');
    $table->foreignUuid('to_customer_id')
        ->nullable()
        ->constrained('customers');

    $table->integer('transfer_fee')->default(0);
    $table->string('token', 64)->unique();
    $table->string('status');  // 'pending', 'completed', 'canceled', 'expired'

    $table->timestamp('expires_at');
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();

    $table->index(['to_email', 'status']);
});
```

### Seeders Update

```php
<?php
// database/seeders/CustomerSeeder.php

namespace Database\Seeders;

use App\Models\Central\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Primary test customer (owns multiple tenants)
        $john = Customer::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'phone' => '+55 11 99999-9999',
            'locale' => 'en',
            'currency' => 'brl',
            'billing_address' => [
                'line1' => '123 Main St',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postal_code' => '01310-100',
                'country' => 'BR',
            ],
            'email_verified_at' => now(),
        ]);

        // Secondary test customer
        $jane = Customer::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => Hash::make('password'),
            'locale' => 'pt_BR',
            'currency' => 'brl',
            'email_verified_at' => now(),
        ]);

        // Enterprise customer
        $enterprise = Customer::create([
            'name' => 'Enterprise Corp',
            'email' => 'billing@enterprise.com',
            'password' => Hash::make('password'),
            'locale' => 'en',
            'currency' => 'usd',
            'tax_ids' => [
                ['type' => 'br_cnpj', 'value' => '12.345.678/0001-90'],
            ],
            'email_verified_at' => now(),
        ]);

        $this->command->info('Customers created:');
        $this->command->info('  - john@example.com (password)');
        $this->command->info('  - jane@example.com (password)');
        $this->command->info('  - billing@enterprise.com (password)');
    }
}
```

```php
<?php
// database/seeders/TenantSeeder.php (updated)

namespace Database\Seeders;

use App\Models\Central\Customer;
use App\Models\Central\Tenant;
use App\Models\Central\Plan;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $john = Customer::where('email', 'john@example.com')->first();
        $jane = Customer::where('email', 'jane@example.com')->first();
        $enterprise = Customer::where('email', 'billing@enterprise.com')->first();

        $starterPlan = Plan::where('slug', 'starter')->first();
        $professionalPlan = Plan::where('slug', 'professional')->first();
        $enterprisePlan = Plan::where('slug', 'enterprise')->first();

        // John owns 2 tenants
        Tenant::create([
            'id' => 'tenant1',
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'customer_id' => $john->id,
            'plan_id' => $professionalPlan->id,
        ]);

        Tenant::create([
            'id' => 'tenant2',
            'name' => 'Side Project',
            'slug' => 'side-project',
            'customer_id' => $john->id,
            'plan_id' => $starterPlan->id,
        ]);

        // Jane owns 1 tenant
        Tenant::create([
            'id' => 'tenant3',
            'name' => 'Startup Inc',
            'slug' => 'startup',
            'customer_id' => $jane->id,
            'plan_id' => $starterPlan->id,
        ]);

        // Enterprise owns 1 tenant
        Tenant::create([
            'id' => 'tenant4',
            'name' => 'Enterprise Workspace',
            'slug' => 'enterprise-ws',
            'customer_id' => $enterprise->id,
            'plan_id' => $enterprisePlan->id,
        ]);

        $this->command->info('Tenants created and linked to customers');
    }
}
```

### Customer Portal Pages (Inertia)

```
resources/js/pages/customer/
├── auth/
│   ├── login.tsx
│   ├── register.tsx
│   ├── forgot-password.tsx
│   ├── reset-password.tsx
│   └── magic-link.tsx
├── dashboard.tsx
├── profile.tsx
├── tenants/
│   ├── index.tsx
│   ├── create.tsx
│   ├── show.tsx
│   └── transfer.tsx
├── payment-methods/
│   └── index.tsx
├── subscriptions/
│   └── index.tsx
└── invoices/
    └── index.tsx
```

---

## Test Users (After Seeding)

| Role | Email | Password | Access |
|------|-------|----------|--------|
| Customer (multi-tenant) | `john@example.com` | `password` | /account (2 tenants) |
| Customer | `jane@example.com` | `password` | /account (1 tenant) |
| Customer (enterprise) | `billing@enterprise.com` | `password` | /account (1 tenant) |
| Central Admin | `admin@setor3.app` | `password` | /admin |
| Tenant User | `john@acme.com` | `password` | tenant1.test |

---

## Appendix: Current Data Flow

### Current Checkout Flow (Tenant-Based)
```
User clicks "Subscribe"
    → TenantBillingController::checkout()
    → Tenant uses Billable trait
    → Stripe creates Customer (if not exists) linked to Tenant
    → Stripe creates Subscription
    → Subscription stored with tenant_id
```

### Proposed Checkout Flow (Customer-Based)
```
User clicks "Subscribe"
    → CustomerBillingController::checkout()
    → Get or create Customer for this user
    → Customer uses Billable trait
    → Stripe Customer already exists or is created
    → Stripe creates Subscription
    → Subscription stored with customer_id + tenant_id
```

### Current Webhook Flow
```
Stripe webhook received
    → Find Tenant by stripe_id
    → Update Tenant subscription status
```

### Proposed Webhook Flow
```
Stripe webhook received
    → Find Customer by stripe_id
    → Update subscription (with tenant_id context if applicable)
    → Notify affected tenant(s)
```

---

## Related Documentation

- [SYSTEM-ARCHITECTURE.md](../SYSTEM-ARCHITECTURE.md) - Plans, features, limits
- [USER-SYNC-FEDERATION.md](../USER-SYNC-FEDERATION.md) - Federated user concept
- [ADDONS.md](../ADDONS.md) - Add-on system architecture
- [Laravel Cashier Stripe](https://laravel.com/docs/billing) - Billing integration
- [Stripe Customer Portal](https://stripe.com/docs/billing/subscriptions/customer-portal) - Self-service billing
