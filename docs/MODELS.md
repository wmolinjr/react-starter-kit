# Models Architecture

This document describes the Model layer architecture, namespaces, traits, and patterns used in this project.

## Overview

Models are organized into namespaces based on their database context, following the multi-database tenancy architecture.

## Directory Structure

```
app/Models/
├── Central/                    # Central Database Models (9 files)
│   ├── Addon.php               # Add-on catalog
│   ├── AddonBundle.php         # Add-on bundles
│   ├── AddonPurchase.php       # One-time addon purchases
│   ├── AddonSubscription.php   # Recurring addon subscriptions
│   ├── Domain.php              # Tenant domains
│   ├── Plan.php                # Subscription plans
│   ├── Tenant.php              # Tenant model (Stancl)
│   ├── TenantInvitation.php    # Team invitations
│   └── User.php                # Central admin users
│
├── Tenant/                     # Tenant Database Models (5 files)
│   ├── Activity.php            # Spatie Activity Log
│   ├── Media.php               # Spatie MediaLibrary
│   ├── Project.php             # Tenant projects
│   ├── TenantTranslationOverride.php # White-label translations
│   └── User.php                # Tenant users
│
└── Shared/                  # Works in Both Contexts (2 files)
    ├── Permission.php          # Spatie Permission
    └── Role.php                # Spatie Role
```

## Namespace Organization

### Central Models (`App\Models\Central\`)

Models stored in the central database (`laravel`). All use `CentralConnection` trait to ensure queries always go to central DB.

| Model | Purpose | Key Traits |
|-------|---------|------------|
| `Tenant` | Multi-tenant core | `HasDatabase`, `Billable`, `HasFeatures` |
| `User` | Central admins | `HasRoles`, `HasUuids` |
| `Plan` | Subscription tiers | `HasTranslations`, `HasUuids` |
| `Domain` | Tenant domains | `CentralConnection`, `HasUuids` |
| `Addon` | Add-on catalog | `HasTranslations`, `HasUuids` |
| `AddonBundle` | Add-on bundles | `HasTranslations`, `HasUuids` |
| `AddonSubscription` | Active subscriptions | `HasUuids` |
| `AddonPurchase` | Purchase history | `HasUuids` |
| `TenantInvitation` | Team invites | `HasUuids` |

### Tenant Models (`App\Models\Tenant\`)

Models stored in each tenant's database (`tenant_{id}`). No `CentralConnection` trait needed - they use the tenant connection automatically.

| Model | Purpose | Key Traits |
|-------|---------|------------|
| `User` | Tenant users | `HasRoles`, `HasUuids`, `LogsActivity` |
| `Project` | Tenant projects | `HasUuids`, `HasMedia` |
| `Activity` | Activity log | Spatie Activity Log |
| `Media` | Media files | Spatie MediaLibrary |
| `TenantTranslationOverride` | White-label translations | `HasUuids` |

### Shared Models (`App\Models\Shared\`)

Models that exist in both central and tenant databases with identical structure.

| Model | Purpose | Key Traits |
|-------|---------|------------|
| `Role` | User roles | `HasUuids`, `HasTenantTranslations` |
| `Permission` | User permissions | `HasUuids` |

---

## Core Traits

### HasUuids (Laravel)

All models use UUID primary keys for security and multi-database compatibility.

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MyModel extends Model
{
    use HasUuids;
}
```

### CentralConnection (Stancl)

Ensures model always uses central database connection, even when tenancy is initialized.

```php
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Plan extends Model
{
    use CentralConnection;
}
```

**When to use**: All Central/ models except Tenant (which uses its own logic).

### HasDatabase (Stancl)

Tenant-specific trait for database management.

```php
use Stancl\Tenancy\Database\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;

class Tenant extends Model implements TenantWithDatabase
{
    use HasDatabase;
}
```

### HasRoles (Spatie)

Enables role/permission management via Spatie Permission.

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

### HasTranslations (Spatie)

Enables translatable attributes for central models.

```php
use Spatie\Translatable\HasTranslations;

class Plan extends Model
{
    use HasTranslations;

    public $translatable = ['name', 'description'];
}
```

### HasTenantTranslations (Custom)

Extension of HasTranslations with tenant override support.

```php
use App\Traits\HasTenantTranslations;

class Role extends SpatieRole
{
    use HasTenantTranslations;

    public array $translatable = ['display_name', 'description'];
}
```

---

## MorphMap Configuration

All polymorphic relations use an enforced MorphMap for consistency and security.

**Location**: `app/Providers/AppServiceProvider.php`

```php
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Central\User as CentralUser;
use App\Models\Tenant\User;
use App\Models\Central\Tenant;
use App\Models\Tenant\Project;
use App\Models\Central\AddonSubscription;
use App\Models\Central\AddonPurchase;

Relation::enforceMorphMap([
    'user' => User::class,           // Tenant users
    'admin' => CentralUser::class,   // Central admins
    'tenant' => Tenant::class,
    'project' => Project::class,
    'addon_subscription' => AddonSubscription::class,
    'addon_purchase' => AddonPurchase::class,
]);
```

**Why enforce MorphMap**:
- Works correctly with UUID primary keys
- Consistent type names in database
- Prevents class name changes from breaking polymorphic relations
- Security: doesn't expose internal class names

---

## Central Models Details

### Tenant Model

The core multi-tenant model implementing Stancl Tenancy.

```php
namespace App\Models\Central;

class Tenant extends Model implements TenantWithDatabase
{
    use CentralConnection, HasDatabase, HasUuids, Billable, HasFeatures;

    // Stancl lifecycle events
    protected $dispatchesEvents = [
        'created' => Events\TenantCreated::class,
        // ... other events
    ];

    // VirtualColumn for internal keys
    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'slug', 'data', 'plan_id', ...];
    }
}
```

**Key Methods**:
```php
$tenant->hasFeature('customRoles');     // Check Pennant feature
$tenant->getLimit('users');             // Get plan limit
$tenant->hasReachedLimit('projects');   // Check usage
$tenant->isPlanPermissionEnabled($perm); // Check plan permission
$tenant->getUsers();                    // Get users (tenant DB)
$tenant->getUsersByRole('admin');       // Get users by role
```

### Central User Model

Administrators who manage the platform (not tenant users).

```php
namespace App\Models\Central;

class User extends Authenticatable
{
    use CentralConnection, HasRoles, HasUuids;

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Super Admin');
    }
}
```

### Plan Model

Subscription plans with features, limits, and Stripe integration.

```php
namespace App\Models\Central;

class Plan extends Model
{
    use CentralConnection, HasTranslations, HasUuids;

    public $translatable = ['name', 'description'];

    protected $casts = [
        'features' => 'array',    // ['customRoles' => true, ...]
        'limits' => 'array',      // ['users' => 10, 'projects' => 50]
        'permission_map' => 'array',
    ];
}
```

---

## Tenant Models Details

### Tenant User Model

Users who belong to a specific tenant.

```php
namespace App\Models\Tenant;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasRoles, HasUuids, LogsActivity, CausesActivity;

    // No CentralConnection - uses tenant database

    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    public function isAdminOrOwner(): bool
    {
        return $this->hasAnyRole(['owner', 'admin']);
    }

    public function currentTenantRole(): ?string
    {
        return $this->roles->first()?->name;
    }
}
```

**Option C Architecture**:
- Users exist ONLY in tenant databases
- No pivot table in central database
- Complete isolation between tenants
- Roles/permissions are local to each tenant

### Project Model

Example tenant resource with media support.

```php
namespace App\Models\Tenant;

class Project extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia, LogsActivity;

    protected $fillable = ['name', 'description', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }
}
```

---

## Shared Models Details

### Role Model

Extends Spatie Role with UUID and translations.

```php
namespace App\Models\Shared;

class Role extends SpatieRole
{
    use HasTenantTranslations, HasUuids;

    public array $translatable = ['display_name', 'description'];

    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'display_name',
        'is_protected',
    ];

    public function isProtected(): bool
    {
        return $this->is_protected ||
               in_array($this->name, ['owner', 'admin', 'member', 'Super Admin', 'Central Admin']);
    }
}
```

### Permission Model

Extends Spatie Permission with UUID.

```php
namespace App\Models\Shared;

class Permission extends SpatiePermission
{
    use HasUuids;
}
```

---

## Cross-Database Relationships

### Central → Tenant (via TenantRun)

Access tenant data from central context:

```php
// In Tenant model
public function getUsers(): Collection
{
    return $this->run(function () {
        return TenantUser::all();
    });
}

public function getUsersByRole(string $role): Collection
{
    return $this->run(function () use ($role) {
        return TenantUser::role($role)->get();
    });
}
```

### Tenant → Central (via CentralConnection)

Access central data from tenant context:

```php
// In tenant context, Plan model uses CentralConnection
$tenant = tenant();
$plan = $tenant->plan; // Works because Plan uses CentralConnection
```

---

## Migration Patterns

### Central Migrations

```php
// database/migrations/2024_01_01_create_plans_table.php
Schema::create('plans', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->json('name');           // Translatable
    $table->json('features')->nullable();
    $table->json('limits')->nullable();
    $table->integer('price');
    $table->timestamps();
});
```

### Tenant Migrations

```php
// database/migrations/tenant/2024_01_01_create_projects_table.php
Schema::create('projects', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->foreignUuid('created_by')->constrained('users');
    $table->timestamps();
    $table->softDeletes();
});
```

### Shared Tables

Created in both central and tenant migrations:

```php
// Both central and tenant have roles table
Schema::create('roles', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('guard_name');
    $table->json('display_name')->nullable();
    $table->json('description')->nullable();
    $table->boolean('is_protected')->default(false);
    $table->timestamps();
});
```

---

## Creating New Models

### 1. Determine Context

- Central operations → `App\Models\Central\`
- Tenant operations → `App\Models\Tenant\`
- Works in both → `App\Models\Shared\`

### 2. Create Model

**Central Model**:
```php
<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Invoice extends Model
{
    use CentralConnection, HasUuids;

    protected $fillable = ['tenant_id', 'amount', 'status'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

**Tenant Model**:
```php
<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasUuids;

    // No CentralConnection - uses tenant database automatically

    protected $fillable = ['name', 'project_id', 'user_id'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
```

### 3. Add to MorphMap (if polymorphic)

```php
// app/Providers/AppServiceProvider.php
Relation::enforceMorphMap([
    // ... existing entries
    'task' => \App\Models\Tenant\Task::class,
]);
```

### 4. Create Migration

**Central**:
```bash
sail artisan make:migration create_invoices_table
```

**Tenant**:
```bash
sail artisan make:migration create_tasks_table --path=database/migrations/tenant
```

---

## Observers

Model observers are registered in AppServiceProvider:

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    Tenant::observe(TenantObserver::class);
    AddonSubscription::observe(AddonSubscriptionObserver::class);
    User::observe(UserObserver::class);      // Tenant User
    Project::observe(ProjectObserver::class);
    Domain::observe(DomainObserver::class);
}
```

---

## Testing Models

```php
use App\Models\Tenant\User;
use App\Models\Central\Plan;

test('tenant user has roles', function () {
    $this->initializeTenancy();

    $user = User::factory()->create();
    $user->assignRole('admin');

    expect($user->hasRole('admin'))->toBeTrue();
    expect($user->isAdminOrOwner())->toBeTrue();
});

test('plan has features', function () {
    $plan = Plan::factory()->create([
        'features' => ['customRoles' => true, 'auditLog' => false],
    ]);

    expect($plan->hasFeature('customRoles'))->toBeTrue();
    expect($plan->hasFeature('auditLog'))->toBeFalse();
});
```

---

## Related Documentation

- [SERVICES.md](SERVICES.md) - Service layer architecture
- [CONTROLLERS.md](CONTROLLERS.md) - Controller patterns
- [DATABASE-IDS.md](DATABASE-IDS.md) - UUID architecture decision
- [PERMISSIONS.md](PERMISSIONS.md) - Permission system and enums
