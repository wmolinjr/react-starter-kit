# Hybrid Plans Architecture - Implementation Plan

## Overview

This implementation plan details the step-by-step process to implement the **Hybrid Plans Architecture** (Database + Laravel Pennant + Spatie Permission integration) for the Laravel 12 + React 19 + Inertia.js SaaS starter kit.

**Architecture**: Database-driven plans with Laravel Pennant feature flags and automatic permission synchronization via Spatie Permission.

**Estimated Timeline**: 3-4 weeks for complete MVP implementation.

---

## Prerequisites

### System Requirements
- ✅ Laravel 12 installed
- ✅ PostgreSQL 18 running via Laravel Sail
- ✅ Redis configured (DB 0: sessions, DB 1: cache, DB 2: queue)
- ✅ Stancl Tenancy working (multi-tenant isolation)
- ✅ Spatie Permission installed and configured
- ✅ Laravel Cashier installed (Stripe integration ready)
- ✅ React 19 + TypeScript + Inertia.js configured

### Current State Verified
- ✅ Multi-tenancy working (`InitializeTenancyByDomainExceptTests` middleware)
- ✅ Permissions system working (22 base permissions, 3 roles)
- ✅ MediaLibrary integrated with tenant isolation
- ✅ `SyncPermissions` command functional
- ✅ TypeScript types auto-generated for permissions
- ✅ `usePermissions()` hook ready in frontend

---

## Phase 1: Database Schema Setup (Days 1-2)

### Objective
Create database tables for plans and update tenants table with plan-related fields.

### Files to Create

#### 1.1 Migration: `create_plans_table.php`

**Location**: `database/migrations/YYYY_MM_DD_HHMMSS_create_plans_table.php`

**Instructions**:
1. Create migration:
   ```bash
   sail artisan make:migration create_plans_table
   ```

2. Implement schema:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            // Basic info
            $table->string('name'); // "Starter", "Professional", "Enterprise"
            $table->string('slug')->unique(); // "starter", "professional", "enterprise"
            $table->text('description')->nullable();

            // Pricing
            $table->integer('price')->default(0); // In cents (2900 = $29.00)
            $table->string('currency', 3)->default('USD');
            $table->enum('billing_period', ['monthly', 'yearly'])->default('monthly');

            // Stripe/Paddle Integration
            $table->string('stripe_price_id')->nullable()->unique();
            $table->string('paddle_price_id')->nullable()->unique();

            // Features (JSON)
            // { "customRoles": true, "apiAccess": true, "advancedReports": false }
            $table->json('features')->nullable();

            // Limits (JSON)
            // { "users": 50, "projects": -1, "storage": 10240, "apiCalls": 10000 }
            // -1 = unlimited
            $table->json('limits')->nullable();

            // ⭐ Permission Mapping (JSON)
            // Maps features to permissions that should be enabled
            // {
            //   "customRoles": ["tenant.roles:*"],
            //   "apiAccess": ["tenant.apiTokens:*"],
            //   "advancedReports": ["tenant.reports:export", "tenant.reports:schedule"]
            // }
            $table->json('permission_map')->nullable();

            // Meta
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
```

3. Test migration:
   ```bash
   sail artisan migrate
   ```

#### 1.2 Migration: `add_plan_to_tenants_table.php`

**Location**: `database/migrations/YYYY_MM_DD_HHMMSS_add_plan_to_tenants_table.php`

**Instructions**:
1. Create migration:
   ```bash
   sail artisan make:migration add_plan_to_tenants_table
   ```

2. Implement schema:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('plan_id')
                ->nullable()
                ->after('id')
                ->constrained('plans')
                ->nullOnDelete();

            // Custom overrides for this tenant (optional)
            // Overrides plan defaults when non-null
            $table->json('plan_features_override')->nullable();
            $table->json('plan_limits_override')->nullable();

            // Trial
            $table->timestamp('trial_ends_at')->nullable();

            // Usage tracking (for quotas)
            $table->json('current_usage')->nullable();
            // { "users": 5, "projects": 23, "storage": 2048, "apiCalls": 1523 }

            // ⭐ Cache of permissions enabled by plan
            // Regenerated when plan changes
            $table->json('plan_enabled_permissions')->nullable();

            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn([
                'plan_id',
                'plan_features_override',
                'plan_limits_override',
                'trial_ends_at',
                'current_usage',
                'plan_enabled_permissions',
            ]);
        });
    }
};
```

3. Test migration:
   ```bash
   sail artisan migrate
   ```

### Validation
- [ ] `plans` table created with all columns
- [ ] `tenants` table updated with plan fields
- [ ] Foreign key constraint working: `tenants.plan_id → plans.id`
- [ ] Can rollback migrations without errors:
   ```bash
   sail artisan migrate:rollback --step=2
   sail artisan migrate
   ```

**Dependencies**: None (Phase 1 is foundational)

---

## Phase 2: Models Implementation (Days 2-3)

### Objective
Create Plan model and update Tenant model with plan-related methods.

### Files to Create/Modify

#### 2.1 Create: `app/Models/Plan.php`

**Instructions**:
1. Create model:
   ```bash
   sail artisan make:model Plan --no-migration
   ```

2. Implement complete Plan model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_period',
        'stripe_price_id',
        'paddle_price_id',
        'features',
        'limits',
        'permission_map',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'features' => 'array',
        'limits' => 'array',
        'permission_map' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Check if plan has a specific feature
     */
    public function hasFeature(string $feature): bool
    {
        return $this->features[$feature] ?? false;
    }

    /**
     * Get limit for a resource (-1 = unlimited)
     */
    public function getLimit(string $resource): int
    {
        return $this->limits[$resource] ?? 0;
    }

    /**
     * Check if limit is unlimited
     */
    public function isUnlimited(string $resource): bool
    {
        return $this->getLimit($resource) === -1;
    }

    /**
     * ⭐ Get permissions that should be enabled for a feature
     */
    public function getPermissionsForFeature(string $feature): array
    {
        return $this->permission_map[$feature] ?? [];
    }

    /**
     * ⭐ Get all permissions enabled by this plan
     */
    public function getAllEnabledPermissions(): array
    {
        $permissions = [];

        foreach ($this->features ?? [] as $feature => $enabled) {
            if ($enabled) {
                $featurePermissions = $this->getPermissionsForFeature($feature);
                $permissions = array_merge($permissions, $featurePermissions);
            }
        }

        return array_unique($permissions);
    }

    /**
     * ⭐ Expand wildcard permissions
     * "tenant.roles:*" → all roles permissions
     */
    public function expandPermissions(array $permissions): array
    {
        $expanded = [];

        foreach ($permissions as $permission) {
            if (str_ends_with($permission, ':*')) {
                // Wildcard: get all permissions for this category
                $category = str_replace(':*', '', $permission);
                $categoryPermissions = \App\Models\Permission::where('name', 'like', "{$category}:%")
                    ->pluck('name')
                    ->toArray();
                $expanded = array_merge($expanded, $categoryPermissions);
            } else {
                $expanded[] = $permission;
            }
        }

        return array_unique($expanded);
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->price === 0) {
            return 'Custom';
        }

        return '$' . number_format($this->price / 100, 2);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
```

#### 2.2 Update: `app/Models/Tenant.php`

**Instructions**:
1. Add Laravel Pennant trait at the top of the class:
   ```php
   use Laravel\Pennant\Concerns\HasFeatures;

   class Tenant extends BaseTenant implements TenantWithDatabase
   {
       use HasDatabase, HasDomains, HasFeatures;
   ```

2. Update `$casts` array to include new fields:
   ```php
   protected $casts = [
       'settings' => 'array',
       'plan_features_override' => 'array',
       'plan_limits_override' => 'array',
       'current_usage' => 'array',
       'plan_enabled_permissions' => 'array',
       'trial_ends_at' => 'datetime',
   ];
   ```

3. Add relationship:
   ```php
   public function plan(): BelongsTo
   {
       return $this->belongsTo(Plan::class);
   }
   ```

4. Add plan-related methods:

```php
/**
 * Check if tenant has a specific feature
 * Used by Pennant features
 */
public function hasFeature(string $feature): bool
{
    // Override primeiro
    if (isset($this->plan_features_override[$feature])) {
        return $this->plan_features_override[$feature];
    }

    // Trial gets all features
    if ($this->isOnTrial()) {
        return true;
    }

    // Fallback para plan default
    return $this->plan?->hasFeature($feature) ?? false;
}

/**
 * Get limit for a resource
 */
public function getLimit(string $resource): int
{
    // Override primeiro
    if (isset($this->plan_limits_override[$resource])) {
        return $this->plan_limits_override[$resource];
    }

    // Trial gets higher limits
    if ($this->isOnTrial()) {
        return $this->plan?->getLimit($resource) ?? -1;
    }

    // Fallback para plan default
    return $this->plan?->getLimit($resource) ?? 0;
}

/**
 * Check if unlimited
 */
public function isUnlimited(string $resource): bool
{
    return $this->getLimit($resource) === -1;
}

/**
 * Get current usage
 */
public function getCurrentUsage(string $resource): int
{
    return $this->current_usage[$resource] ?? 0;
}

/**
 * Check if limit reached
 */
public function hasReachedLimit(string $resource): bool
{
    $limit = $this->getLimit($resource);

    if ($limit === -1) {
        return false;
    }

    $usage = $this->getCurrentUsage($resource);

    return $usage >= $limit;
}

/**
 * Increment usage
 */
public function incrementUsage(string $resource, int $amount = 1): void
{
    $currentUsage = $this->current_usage ?? [];
    $currentUsage[$resource] = ($currentUsage[$resource] ?? 0) + $amount;

    $this->update(['current_usage' => $currentUsage]);
}

/**
 * Decrement usage
 */
public function decrementUsage(string $resource, int $amount = 1): void
{
    $currentUsage = $this->current_usage ?? [];
    $currentUsage[$resource] = max(0, ($currentUsage[$resource] ?? 0) - $amount);

    $this->update(['current_usage' => $currentUsage]);
}

/**
 * ⭐ Get permissions enabled by current plan
 */
public function getPlanEnabledPermissions(): array
{
    // Return cached if available
    if ($this->plan_enabled_permissions) {
        return $this->plan_enabled_permissions;
    }

    // Regenerate and cache
    return $this->regeneratePlanPermissions();
}

/**
 * ⭐ Regenerate permissions based on current plan
 */
public function regeneratePlanPermissions(): array
{
    if (!$this->plan) {
        return [];
    }

    $permissions = $this->plan->getAllEnabledPermissions();
    $expanded = $this->plan->expandPermissions($permissions);

    // Cache it
    $this->update(['plan_enabled_permissions' => $expanded]);

    return $expanded;
}

/**
 * ⭐ Check if permission is enabled by plan
 */
public function isPlanPermissionEnabled(string $permission): bool
{
    return in_array($permission, $this->getPlanEnabledPermissions());
}

/**
 * Trial methods
 */
public function isOnTrial(): bool
{
    return $this->trial_ends_at && $this->trial_ends_at->isFuture();
}

public function hasTrialEnded(): bool
{
    return $this->trial_ends_at && $this->trial_ends_at->isPast();
}
```

### Validation
- [ ] `Plan` model created with all methods
- [ ] `Tenant` model updated with `HasFeatures` trait
- [ ] Test in Tinker:
   ```php
   $plan = Plan::factory()->create(['features' => ['customRoles' => true]]);
   $tenant = Tenant::first();
   $tenant->update(['plan_id' => $plan->id]);

   // Test methods
   $tenant->hasFeature('customRoles'); // true
   $tenant->getLimit('users'); // from plan
   $tenant->regeneratePlanPermissions(); // returns array
   ```

**Dependencies**: Phase 1 (database schema)

---

## Phase 3: Laravel Pennant Installation (Days 3-4)

### Objective
Install Laravel Pennant, configure it, and create feature classes.

### Installation Steps

#### 3.1 Install Pennant

**Instructions**:
```bash
sail composer require laravel/pennant
sail artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"
sail artisan migrate
```

#### 3.2 Configure: `config/pennant.php`

**Instructions**:
1. Edit config file:
   ```php
   return [
       'default' => 'database',

       'stores' => [
           'database' => [
               'driver' => 'database',
               'connection' => null,
           ],
       ],

       // ⭐ Set default scope to Tenant
       'scope' => \App\Models\Tenant::class,
   ];
   ```

2. Verify migration created `features` table

### Files to Create

#### 3.3 Feature Classes

Create directory `app/Features/` and create these 8 feature classes:

**3.3.1 `app/Features/CustomRoles.php`**
```php
<?php

namespace App\Features;

use App\Models\Tenant;

class CustomRoles
{
    /**
     * Resolve the feature's initial value.
     */
    public function resolve(Tenant $tenant): bool
    {
        return $tenant->hasFeature('customRoles');
    }
}
```

**3.3.2 `app/Features/ApiAccess.php`**
```php
<?php

namespace App\Features;

use App\Models\Tenant;

class ApiAccess
{
    public function resolve(Tenant $tenant): bool
    {
        return $tenant->hasFeature('apiAccess');
    }
}
```

**3.3.3 `app/Features/MaxUsers.php`** (Rich Value)
```php
<?php

namespace App\Features;

use App\Models\Tenant;

class MaxUsers
{
    /**
     * Resolve the feature's initial value.
     *
     * Returns the user limit as an integer (rich value)
     */
    public function resolve(Tenant $tenant): int
    {
        return $tenant->getLimit('users');
    }
}
```

**3.3.4 `app/Features/MaxProjects.php`** (Rich Value)
```php
<?php

namespace App\Features;

use App\Models\Tenant;

class MaxProjects
{
    public function resolve(Tenant $tenant): int
    {
        return $tenant->getLimit('projects');
    }
}
```

**3.3.5 `app/Features/StorageLimit.php`** (Rich Value)
```php
<?php

namespace App\Features;

use App\Models\Tenant;

class StorageLimit
{
    /**
     * Returns storage limit in MB
     */
    public function resolve(Tenant $tenant): int
    {
        return $tenant->getLimit('storage');
    }
}
```

**3.3.6 `app/Features/AdvancedReports.php`**
```php
<?php

namespace App\Features;

use App\Models\Tenant;

class AdvancedReports
{
    public function resolve(Tenant $tenant): bool
    {
        return $tenant->hasFeature('advancedReports');
    }
}
```

**3.3.7 `app/Features/Sso.php`**
```php
<?php

namespace App\Features;

use App\Models\Tenant;

class Sso
{
    public function resolve(Tenant $tenant): bool
    {
        return $tenant->hasFeature('sso');
    }
}
```

**3.3.8 `app/Features/WhiteLabel.php`**
```php
<?php

namespace App\Features;

use App\Models\Tenant;

class WhiteLabel
{
    public function resolve(Tenant $tenant): bool
    {
        return $tenant->hasFeature('whiteLabel');
    }
}
```

#### 3.4 Register Features: `app/Providers/AppServiceProvider.php`

**Instructions**:
1. Import at top:
   ```php
   use Laravel\Pennant\Feature;
   ```

2. Add to `boot()` method:
   ```php
   public function boot(): void
   {
       // Existing Gate::before() for Super Admin...

       // ⭐ Register Pennant features
       Feature::discover();

       // Existing macros...
   }
   ```

### Validation
- [ ] Pennant installed and migrated
- [ ] 8 feature classes created in `app/Features/`
- [ ] Features registered in `AppServiceProvider`
- [ ] Test in Tinker:
   ```php
   use Laravel\Pennant\Feature;

   $tenant = Tenant::first();
   $tenant->update(['plan_id' => 1]); // Assume plan exists

   Feature::for($tenant)->active('customRoles'); // bool
   Feature::for($tenant)->value('maxUsers'); // int
   Feature::for($tenant)->values(['customRoles', 'apiAccess']); // array
   ```

**Dependencies**: Phase 2 (models with `hasFeature()` methods)

---

## Phase 4: Permission Sync System (Days 4-6)

### Objective
Create automatic permission synchronization when plans change, using observers and commands.

### Files to Create/Modify

#### 4.1 Update: `app/Console/Commands/SyncPermissions.php`

**Instructions**:
1. Add Enterprise permissions to existing `$permissions` array:
   ```php
   protected array $permissions = [
       // ... existing 22 permissions ...

       // Custom Roles (Pro+)
       ['name' => 'tenant.roles:view', 'description' => 'View custom roles', 'category' => 'roles'],
       ['name' => 'tenant.roles:create', 'description' => 'Create custom roles', 'category' => 'roles'],
       ['name' => 'tenant.roles:edit', 'description' => 'Edit custom roles', 'category' => 'roles'],
       ['name' => 'tenant.roles:delete', 'description' => 'Delete custom roles', 'category' => 'roles'],

       // Advanced Reports (Enterprise)
       ['name' => 'tenant.reports:view', 'description' => 'View reports', 'category' => 'reports'],
       ['name' => 'tenant.reports:export', 'description' => 'Export reports', 'category' => 'reports'],
       ['name' => 'tenant.reports:schedule', 'description' => 'Schedule reports', 'category' => 'reports'],
       ['name' => 'tenant.reports:customize', 'description' => 'Customize reports', 'category' => 'reports'],

       // SSO (Enterprise)
       ['name' => 'tenant.sso:configure', 'description' => 'Configure SSO', 'category' => 'sso'],
       ['name' => 'tenant.sso:manage', 'description' => 'Manage SSO providers', 'category' => 'sso'],
       ['name' => 'tenant.sso:testConnection', 'description' => 'Test SSO connection', 'category' => 'sso'],

       // White Label (Enterprise)
       ['name' => 'tenant.branding:view', 'description' => 'View branding', 'category' => 'branding'],
       ['name' => 'tenant.branding:edit', 'description' => 'Edit branding', 'category' => 'branding'],
       ['name' => 'tenant.branding:preview', 'description' => 'Preview branding', 'category' => 'branding'],
       ['name' => 'tenant.branding:publish', 'description' => 'Publish branding', 'category' => 'branding'],
   ];
   ```

2. Run sync:
   ```bash
   sail artisan permissions:sync
   ```

#### 4.2 Create: `app/Console/Commands/SyncPlanPermissions.php`

**Instructions**:
1. Create command:
   ```bash
   sail artisan make:command SyncPlanPermissions
   ```

2. Implement complete command:

```php
<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Permission;
use Illuminate\Console\Command;

class SyncPlanPermissions extends Command
{
    protected $signature = 'plans:sync-permissions {--fresh}';
    protected $description = 'Sync permissions enabled by each plan';

    /**
     * Permission definitions grouped by feature
     */
    protected array $featurePermissions = [
        // Base features (all plans)
        'projects' => [
            'tenant.projects:view',
            'tenant.projects:create',
            'tenant.projects:editOwn',
            'tenant.projects:download',
        ],

        // Professional+
        'customRoles' => [
            'tenant.roles:view',
            'tenant.roles:create',
            'tenant.roles:edit',
            'tenant.roles:delete',
        ],

        'apiAccess' => [
            'tenant.apiTokens:view',
            'tenant.apiTokens:create',
            'tenant.apiTokens:delete',
        ],

        // Enterprise only
        'advancedReports' => [
            'tenant.reports:view',
            'tenant.reports:export',
            'tenant.reports:schedule',
            'tenant.reports:customize',
        ],

        'sso' => [
            'tenant.sso:configure',
            'tenant.sso:manage',
            'tenant.sso:testConnection',
        ],

        'whiteLabel' => [
            'tenant.branding:view',
            'tenant.branding:edit',
            'tenant.branding:preview',
            'tenant.branding:publish',
        ],
    ];

    public function handle(): int
    {
        $this->info('🔄 Syncing Plan Permissions...');

        if ($this->option('fresh')) {
            $this->warn('⚠️  Fresh mode: Regenerating all permission maps...');
        }

        $plans = Plan::all();

        foreach ($plans as $plan) {
            $this->syncPlan($plan);
        }

        $this->info('✅ Plan permissions synced successfully!');

        return self::SUCCESS;
    }

    protected function syncPlan(Plan $plan): void
    {
        $this->line("Processing plan: {$plan->name}");

        $permissionMap = [];

        // Build permission map based on plan features
        foreach ($plan->features ?? [] as $feature => $enabled) {
            if ($enabled && isset($this->featurePermissions[$feature])) {
                $permissionMap[$feature] = $this->featurePermissions[$feature];
            }
        }

        // Update plan
        $plan->update(['permission_map' => $permissionMap]);

        $totalPermissions = count(array_merge(...array_values($permissionMap)));
        $this->info("  ✓ {$plan->name}: {$totalPermissions} permissions mapped");
    }
}
```

#### 4.3 Create: `app/Observers/TenantObserver.php`

**Instructions**:
1. Create observer:
   ```bash
   sail artisan make:observer TenantObserver --model=Tenant
   ```

2. Implement complete observer:

```php
<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

class TenantObserver
{
    /**
     * Handle the Tenant "updated" event.
     */
    public function updated(Tenant $tenant): void
    {
        // If the plan changed, sync permissions
        if ($tenant->wasChanged('plan_id')) {
            $this->syncPermissionsForPlanChange($tenant);
        }
    }

    /**
     * ⭐ Sync permissions when plan changes
     */
    protected function syncPermissionsForPlanChange(Tenant $tenant): void
    {
        Log::info("Syncing permissions for tenant {$tenant->id} after plan change");

        // Initialize tenancy context
        tenancy()->initialize($tenant);
        setPermissionsTeamId($tenant->id);

        try {
            // 1. Regenerate plan permissions cache
            $enabledPermissions = $tenant->regeneratePlanPermissions();

            // 2. Clear Pennant cache for this tenant
            Feature::for($tenant)->flushCache();

            // 3. For each role in this tenant, sync their permissions
            // based on what's enabled by the plan
            $this->syncRolePermissions($tenant, $enabledPermissions);

            // 4. Log activity
            activity()
                ->performedOn($tenant)
                ->withProperties([
                    'plan' => $tenant->plan->name,
                    'enabled_permissions' => $enabledPermissions,
                ])
                ->log('Plan permissions synchronized');

        } catch (\Exception $e) {
            Log::error("Failed to sync permissions for tenant {$tenant->id}: {$e->getMessage()}");
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Sync role permissions based on plan
     */
    protected function syncRolePermissions(Tenant $tenant, array $enabledPermissions): void
    {
        $roles = Role::where('tenant_id', $tenant->id)->get();

        foreach ($roles as $role) {
            // Get current role permissions
            $currentPermissions = $role->permissions->pluck('name')->toArray();

            // Filter: only keep permissions that are enabled by plan
            $allowedPermissions = array_intersect($currentPermissions, $enabledPermissions);

            // Get removed permissions
            $removedPermissions = array_diff($currentPermissions, $allowedPermissions);

            if (!empty($removedPermissions)) {
                Log::info("Removing " . count($removedPermissions) . " permissions from role {$role->name}");

                // Sync to allowed only
                $role->syncPermissions(
                    Permission::whereIn('name', $allowedPermissions)->get()
                );
            }
        }
    }
}
```

#### 4.4 Register Observer: `app/Providers/AppServiceProvider.php`

**Instructions**:
1. Import:
   ```php
   use App\Models\Tenant;
   use App\Observers\TenantObserver;
   ```

2. Add to `boot()`:
   ```php
   public function boot(): void
   {
       // ⭐ Register Tenant observer
       Tenant::observe(TenantObserver::class);

       // Existing code...
   }
   ```

#### 4.5 Update Gate: `app/Providers/AppServiceProvider.php`

**Instructions**:
1. Update `Gate::before()` to check plan permissions:
   ```php
   Gate::before(function ($user, $ability) {
       // 1. Super Admin bypass (existing)
       $currentTeamId = getPermissionsTeamId();
       setPermissionsTeamId(null);
       $isSuperAdmin = $user->hasRole('Super Admin');
       setPermissionsTeamId($currentTeamId);

       if ($isSuperAdmin) {
           return true;
       }

       // 2. ⭐ NEW: Check plan-enabled permissions
       $tenant = tenant();
       if ($tenant && str_starts_with($ability, 'tenant.')) {
           // If permission not enabled by plan, deny
           if (!$tenant->isPlanPermissionEnabled($ability)) {
               return false;
           }
       }

       // 3. Continue to normal permission check
       return null;
   });
   ```

### Validation
- [ ] `SyncPermissions` command updated with 15 new permissions (37 total)
- [ ] `SyncPlanPermissions` command created
- [ ] `TenantObserver` created and registered
- [ ] Gate updated with plan permission check
- [ ] Test observer:
   ```php
   $tenant = Tenant::first();
   $starterPlan = Plan::where('slug', 'starter')->first();
   $proPlan = Plan::where('slug', 'professional')->first();

   $tenant->update(['plan_id' => $starterPlan->id]);
   // Check plan_enabled_permissions cache updated

   $tenant->update(['plan_id' => $proPlan->id]);
   // Check plan_enabled_permissions cache updated with more permissions
   ```

**Dependencies**: Phase 3 (Pennant features)

---

## Phase 5: Seeders & Factories (Days 6-7)

### Objective
Create seeders for plans and update DatabaseSeeder.

### Files to Create

#### 5.1 Create: `database/seeders/PlanSeeder.php`

**Instructions**:
1. Create seeder:
   ```bash
   sail artisan make:seeder PlanSeeder
   ```

2. Copy complete implementation from `docs/PLANS-SEEDERS.md` lines 10-259

3. The seeder should include:
   - **Starter**: $29/mo, 1 user, 50 projects, 8 permissions
   - **Professional**: $99/mo, 50 users, unlimited projects, 27 permissions
   - **Enterprise**: Custom pricing, unlimited, 40+ permissions

4. Permission maps must match feature keys

#### 5.2 Create: `database/factories/PlanFactory.php`

**Instructions**:
1. Create factory:
   ```bash
   sail artisan make:factory PlanFactory
   ```

2. Copy complete implementation from `docs/PLANS-SEEDERS.md` lines 432-542

3. Should include states for `starter()`, `professional()`, `enterprise()`

#### 5.3 Update: `database/seeders/DatabaseSeeder.php`

**Instructions**:
1. Update `run()` method:
   ```php
   public function run(): void
   {
       $this->call([
           // 1. Sync base permissions first
           // NOTE: Run manually: php artisan permissions:sync

           // 2. Seed plans
           PlanSeeder::class,

           // 3. Sync plan permission mappings
           // NOTE: Run manually: php artisan plans:sync-permissions

           // 4. Existing seeders (if any)
           // TenantSeeder::class,
       ]);

       $this->command->info('🎉 Database seeded successfully!');
       $this->command->newLine();
       $this->command->info('Next steps:');
       $this->command->line('  1. Run: sail artisan permissions:sync');
       $this->command->line('  2. Run: sail artisan plans:sync-permissions');
       $this->command->line('  3. Assign plans to tenants');
   }
   ```

### Validation
- [ ] `PlanSeeder` created with 3 plans
- [ ] `PlanFactory` created with states
- [ ] `DatabaseSeeder` updated
- [ ] Test seeding:
   ```bash
   sail artisan migrate:fresh
   sail artisan permissions:sync
   sail artisan db:seed
   sail artisan plans:sync-permissions

   # Verify in Tinker
   Plan::count(); // 3
   Plan::where('slug', 'professional')->first()->features; // array
   ```

**Dependencies**: Phase 4 (permission sync system)

---

## Phase 6: Middleware Implementation (Days 7-8)

### Objective
Create middleware to enforce feature and limit checks.

### Files to Create

#### 6.1 Create: `app/Http/Middleware/CheckFeature.php`

**Instructions**:
1. Create middleware:
   ```bash
   sail artisan make:middleware CheckFeature
   ```

2. Implement from `docs/PLANS-HYBRID-ARCHITECTURE.md` lines 1098-1138

3. Key features:
   - Accept feature name as parameter
   - Use `Feature::for($tenant)->active($feature)` to check
   - JSON response for API, redirect for web

#### 6.2 Create: `app/Http/Middleware/CheckLimit.php`

**Instructions**:
1. Create middleware:
   ```bash
   sail artisan make:middleware CheckLimit
   ```

2. Implement from `docs/PLANS-HYBRID-ARCHITECTURE.md` lines 1143-1189

3. Key features:
   - Accept resource name as parameter
   - Use Pennant rich values for limits
   - Check current usage

#### 6.3 Register Middleware: `bootstrap/app.php`

**Instructions**:
1. Add to middleware aliases:
   ```php
   ->withMiddleware(function (Middleware $middleware) {
       $middleware->alias([
           'feature' => \App\Http\Middleware\CheckFeature::class,
           'limit' => \App\Http\Middleware\CheckLimit::class,
           // ... existing aliases
       ]);
   })
   ```

### Validation
- [ ] `CheckFeature` middleware created
- [ ] `CheckLimit` middleware created
- [ ] Middleware registered in `bootstrap/app.php`
- [ ] Test with a route

**Dependencies**: Phase 3 (Pennant features)

---

## Phase 7: Usage Tracking (Days 8-9)

### Objective
Implement automatic usage tracking for resources (users, projects, storage, etc.).

### Files to Create

#### 7.1 Create: `app/Observers/UserObserver.php`

**Instructions**:
1. Create observer:
   ```bash
   sail artisan make:observer UserObserver --model=User
   ```

2. Implement:
   - `created()`: Increment users usage
   - `deleted()`: Decrement users usage

#### 7.2 Create: `app/Observers/ProjectObserver.php`

**Instructions**:
1. Create observer:
   ```bash
   sail artisan make:observer ProjectObserver --model=Project
   ```

2. Implement similar to UserObserver for projects

#### 7.3 Register Observers: `app/Providers/AppServiceProvider.php`

**Instructions**:
1. Import observers
2. Add to `boot()`:
   ```php
   User::observe(UserObserver::class);
   Project::observe(ProjectObserver::class);
   ```

### Validation
- [ ] Observers created and registered
- [ ] Test usage tracking in Tinker

**Dependencies**: Phase 2 (Tenant model with usage methods)

---

## Phase 8: Frontend Integration (Days 9-12)

### Objective
Share plan data with frontend, create hooks and components for plan/feature checks.

### Files to Modify/Create

#### 8.1 Update: `app/Http/Middleware/HandleInertiaRequests.php`

**Instructions**:
1. Import Pennant
2. Add `plan` object to shared data
3. Include features (via Pennant), limits, usage, trial info

#### 8.2 Update: `resources/js/types/index.d.ts`

**Instructions**:
1. Add `Plan` interface
2. Update `PageProps` to include `plan: Plan | null`

#### 8.3 Create: `resources/js/hooks/use-plan.ts`

**Instructions**:
1. Create hook with methods:
   - `hasFeature()`
   - `getLimit()`
   - `getUsage()`
   - `hasReachedLimit()`
   - `canAdd()`
   - `getUsagePercentage()`

#### 8.4 Update: At least one page to use plan hook

**Instructions**:
1. Update `resources/js/pages/tenant/team/index.tsx` as example
2. Show usage bars, limits, upgrade prompts

### Validation
- [ ] Inertia sharing plan data
- [ ] TypeScript types updated
- [ ] `usePlan()` hook created
- [ ] At least one page using plan hook
- [ ] Test in browser

**Dependencies**: Phase 3 (Pennant features)

---

## Phase 9: Testing (Days 12-14)

### Objective
Create comprehensive tests for plan features, permission sync, and frontend integration.

### Files to Create

#### 9.1 Create: `tests/Feature/PennantIntegrationTest.php`

**Instructions**:
1. Create test file
2. Test Starter/Professional features
3. Test rich values
4. Test overrides

#### 9.2 Create: `tests/Feature/PermissionSyncTest.php`

**Instructions**:
1. Create test file
2. Test upgrade enables permissions
3. Test downgrade removes permissions
4. Test observer triggers

#### 9.3 Create: `tests/Feature/PlanLimitsTest.php`

**Instructions**:
1. Create test file
2. Test limit enforcement
3. Test middleware blocks when limit reached

#### 9.4 Create: `tests/Feature/PlanChangesTest.php`

**Instructions**:
1. Create test file
2. Test plan change flow
3. Test permission sync on plan change

#### 9.5 Run All Tests

**Instructions**:
```bash
sail artisan test --filter Pennant
sail artisan test --filter PermissionSync
sail artisan test --filter PlanLimits
sail artisan test --filter PlanChanges
sail artisan test
```

### Validation
- [ ] All test suites passing
- [ ] No breaking changes to existing tests

**Dependencies**: Phases 1-8 (all implementation complete)

---

## Phase 10: Documentation & Cleanup (Days 14-15)

### Objective
Document the implementation and clean up any temporary code.

### Files to Create/Update

#### 10.1 Create: `docs/PLANS-IMPLEMENTATION.md`

**Instructions**:
1. Create comprehensive documentation
2. Include architecture overview, diagrams, examples
3. Add troubleshooting guide

#### 10.2 Update: `CLAUDE.md`

**Instructions**:
1. Add Plans section with quick reference
2. Link to documentation

#### 10.3 Update: `README.md`

**Instructions**:
1. Add Plans feature to features list
2. Update screenshots if needed

### Validation
- [ ] Documentation complete
- [ ] No debug code left
- [ ] Code reviewed

**Dependencies**: Phase 9 (all tests passing)

---

## Timeline Summary

| Phase | Duration | Tasks | Dependencies |
|-------|----------|-------|--------------|
| 1. Database Schema | 1-2 days | 2 migrations | None |
| 2. Models | 1 day | Plan model, update Tenant | Phase 1 |
| 3. Pennant Installation | 1-2 days | Install, 8 features | Phase 2 |
| 4. Permission Sync | 2-3 days | Observer, command, Gate | Phase 3 |
| 5. Seeders | 1 day | PlanSeeder, factory | Phase 4 |
| 6. Middleware | 1 day | CheckFeature, CheckLimit | Phase 3 |
| 7. Usage Tracking | 1 day | Observers | Phase 2 |
| 8. Frontend | 3-4 days | Inertia, hooks, UI | Phase 3 |
| 9. Testing | 2-3 days | 4 test suites | Phases 1-8 |
| 10. Documentation | 1-2 days | Docs, cleanup | Phase 9 |

**Total**: 14-20 days (3-4 weeks)

---

## Success Criteria

Implementation is complete when:

✅ All 10 phases completed
✅ All tests passing
✅ No breaking changes
✅ Documentation complete
✅ Code reviewed
✅ Can change tenant plan and permissions sync automatically
✅ Frontend shows plan features and limits
✅ Middleware enforces feature/limit restrictions
✅ Usage tracking works automatically

---

## Rollback Strategy

If needed:
1. Database: `sail artisan migrate:rollback --step=2`
2. Remove Pennant: `sail composer remove laravel/pennant`
3. Restore files: Remove observers, features, revert modified files
4. Clean frontend: Remove hooks, types, page updates

---

## Support

- Main docs: `docs/PLANS-HYBRID-ARCHITECTURE.md`
- Seeders: `docs/PLANS-SEEDERS.md`
- Decision guide: `docs/PLANS-DECISION-GUIDE.md`
- Laravel Pennant: https://laravel.com/docs/12.x/pennant
- Spatie Permission: https://spatie.be/docs/laravel-permission
