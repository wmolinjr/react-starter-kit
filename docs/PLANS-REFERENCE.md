# Plans System - Reference Guide

**⚠️ CRITICAL: Read this before making ANY changes to the Plans system**

This document defines the architecture, decisions, and boundaries of the Hybrid Plans System. Use this as a reference to stay within scope and maintain consistency.

---

## 🏗️ Architecture (DO NOT CHANGE)

### The Three Pillars

```
┌─────────────────┐
│   DATABASE      │  ← Source of truth (plans table)
│   (Laravel)     │  ← Stores features, limits, permission_map
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ LARAVEL PENNANT │  ← Feature resolution layer
│  (Feature Flags)│  ← Boolean features + Rich values
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ SPATIE PERMISSION│ ← Authorization layer
│ (Role/Permissions)│ ← User-level access control
└─────────────────┘
```

**Why This Architecture?**
- ✅ Database-driven = Easy to manage plans via UI later
- ✅ Pennant = Clean feature checking in code
- ✅ Spatie = Granular user permissions within plan limits
- ✅ Separation of concerns = Maintainable

**⚠️ DO NOT:**
- Replace Pennant with custom feature flags
- Remove Spatie Permission integration
- Move plan data to config files
- Hardcode features in code

---

## 📋 Core Concepts

### 1. Plans (Database Model)

**Location**: `app/Models/Plan.php`

**Purpose**: Defines subscription tiers (Starter, Professional, Enterprise)

**Schema** (`plans` table):
```php
- id: bigint (auto-increment)
- name: string (e.g., "Professional")
- slug: string (e.g., "professional")
- description: text
- price: integer (cents, e.g., 9900 = $99.00)
- currency: string (default: "usd")
- billing_period: enum (monthly, yearly)
- stripe_price_id: string (nullable)
- paddle_price_id: string (nullable)
- features: json (e.g., {"customRoles": true, "apiAccess": true})
- limits: json (e.g., {"users": 50, "projects": -1})
- permission_map: json (e.g., {"customRoles": ["tenant.roles:*"]})
- is_active: boolean
- is_featured: boolean
- sort_order: integer
```

**Key Methods**:
- `hasFeature(string $feature): bool`
- `getLimit(string $resource): int`
- `isUnlimited(string $resource): bool`
- `getPermissionsForFeature(string $feature): array`
- `getAllEnabledPermissions(): array`
- `expandPermissions(array $permissions): array`

**⚠️ DO NOT:**
- Change the JSON structure of `features`, `limits`, or `permission_map`
- Add computed columns that duplicate Pennant logic
- Remove `expandPermissions()` method (needed for wildcards)

---

### 2. Tenant → Plan Relationship

**Location**: `app/Models/Tenant.php`

**Schema** (`tenants` table additions):
```php
- plan_id: bigint (foreign key to plans.id)
- plan_features_override: json (nullable)
- plan_limits_override: json (nullable)
- trial_ends_at: timestamp (nullable)
- current_usage: json (e.g., {"users": 5, "projects": 12})
- plan_enabled_permissions: json (cached permissions array)
```

**Key Methods**:
```php
// Feature checking
hasFeature(string $feature): bool
getFeatureValue(string $feature): mixed

// Limits
getLimit(string $resource): int
isUnlimited(string $resource): bool
hasReachedLimit(string $resource): bool
canAdd(string $resource, int $amount = 1): bool

// Usage tracking
getCurrentUsage(string $resource): int
incrementUsage(string $resource, int $amount = 1): void
decrementUsage(string $resource, int $amount = 1): void

// Permissions
getPlanEnabledPermissions(): array
regeneratePlanPermissions(): array

// Trial
isOnTrial(): bool
```

**⚠️ DO NOT:**
- Bypass `hasFeature()` by checking `plan->features` directly
- Access `current_usage` JSON directly (use helper methods)
- Modify `plan_enabled_permissions` manually (auto-generated)
- Remove override support (critical for custom deals)

---

### 3. Laravel Pennant Integration

**Location**: `app/Features/` (8 feature classes)

**Feature Classes**:
1. `CustomRoles.php` - Boolean feature
2. `ApiAccess.php` - Boolean feature
3. `AdvancedReports.php` - Boolean feature
4. `Sso.php` - Boolean feature
5. `WhiteLabel.php` - Boolean feature
6. `MaxUsers.php` - Rich value (integer limit)
7. `MaxProjects.php` - Rich value (integer limit)
8. `StorageLimit.php` - Rich value (integer limit)

**Pattern for Boolean Features**:
```php
class CustomRoles
{
    public function resolve(Tenant $tenant): bool
    {
        // 1. Trial tenants get everything
        if ($tenant->isOnTrial()) {
            return true;
        }

        // 2. Check overrides first
        if (isset($tenant->plan_features_override['customRoles'])) {
            return $tenant->plan_features_override['customRoles'];
        }

        // 3. Fall back to plan
        return $tenant->plan?->hasFeature('customRoles') ?? false;
    }
}
```

**Pattern for Rich Value Features (Limits)**:
```php
class MaxUsers
{
    public function resolve(Tenant $tenant): int
    {
        // 1. Check overrides first
        if (isset($tenant->plan_limits_override['users'])) {
            return $tenant->plan_limits_override['users'];
        }

        // 2. Fall back to plan limit
        return $tenant->plan?->getLimit('users') ?? 0;
    }
}
```

**Usage in Code**:
```php
use Laravel\Pennant\Feature;

// Boolean features
if (Feature::for($tenant)->active('customRoles')) {
    // Show custom roles UI
}

// Rich values
$userLimit = Feature::for($tenant)->value('maxUsers'); // 50, -1, etc
```

**⚠️ DO NOT:**
- Call feature classes directly (use `Feature::for()`)
- Change the resolution order (trial → override → plan)
- Return null from rich value features (use 0 or -1)
- Add database queries inside feature resolve methods

---

### 4. Permission Mapping & Sync

**How It Works**:
```
Plan.features → Plan.permission_map → Spatie Permissions
```

**Example**:
```json
// Plan: Professional
{
  "features": {
    "customRoles": true,
    "apiAccess": true
  },
  "permission_map": {
    "customRoles": ["tenant.roles:*"],
    "apiAccess": ["tenant.apiTokens:*"]
  }
}
```

**Result**: When tenant has Professional plan:
- `tenant.roles:view`, `tenant.roles:create`, `tenant.roles:edit`, `tenant.roles:delete` (wildcard expanded)
- `tenant.apiTokens:view`, `tenant.apiTokens:create`, `tenant.apiTokens:delete`

**Sync Triggers**:
1. Manual: `sail artisan plans:sync-permissions`
2. Automatic: When `tenant.plan_id` changes (via TenantObserver)

**Observer Logic** (`app/Observers/TenantObserver.php`):
```php
public function updated(Tenant $tenant): void
{
    if ($tenant->isDirty('plan_id')) {
        $tenant->regeneratePlanPermissions();
        Feature::flushCache($tenant); // Clear Pennant cache
    }
}
```

**⚠️ DO NOT:**
- Bypass the observer (always use `$tenant->update(['plan_id' => ...])`)
- Map permissions to features in code (use `permission_map` JSON)
- Skip wildcard expansion (breaks enterprise features)
- Sync permissions on every request (use cached `plan_enabled_permissions`)

---

### 5. Usage Tracking

**Automatic Tracking** (via Observers):
- **Users**: `app/Observers/UserObserver.php`
- **Projects**: `app/Observers/ProjectObserver.php`

**Pattern**:
```php
class UserObserver
{
    public function created(User $user): void
    {
        if (!tenancy()->initialized) {
            return;
        }

        $tenant = tenant();
        if ($tenant) {
            $tenant->incrementUsage('users');
        }
    }

    public function deleted(User $user): void
    {
        if (!tenancy()->initialized) {
            return;
        }

        $tenant = tenant();
        if ($tenant) {
            $tenant->decrementUsage('users');
        }
    }
}
```

**Manual Tracking** (e.g., Storage):
```php
// When uploading file
$tenant->incrementUsage('storage', $fileSizeInMB);

// When deleting file
$tenant->decrementUsage('storage', $fileSizeInMB);
```

**⚠️ DO NOT:**
- Modify `current_usage` JSON directly
- Forget to check `tenancy()->initialized` in observers
- Allow negative usage (helper methods prevent this)
- Track usage for unlimited resources (-1)

---

### 6. Middleware

**CheckFeature** (`app/Http/Middleware/CheckFeature.php`):
```php
// Route protection by feature
Route::get('/roles', RolesController::class)
    ->middleware('feature:customRoles');
```

**CheckLimit** (`app/Http/Middleware/CheckLimit.php`):
```php
// Route protection by limit
Route::post('/users', [UserController::class, 'store'])
    ->middleware('limit:users');
```

**⚠️ DO NOT:**
- Remove middleware from `bootstrap/app.php` aliases
- Change the middleware signature (breaks existing routes)
- Add complex logic in middleware (keep it simple)

---

### 7. Frontend Integration

**Shared Data** (`app/Http/Middleware/HandleInertiaRequests.php`):
```php
'tenant' => [
    // ... other tenant data
    'plan' => $this->getPlanData(current_tenant()),
]
```

**React Hook** (`resources/js/hooks/use-plan.ts`):
```tsx
const {
    plan,              // Full plan object
    features,          // { customRoles: true, ... }
    limits,            // { users: 50, ... }
    usage,             // { users: 12, ... }
    hasFeature,        // (feature) => boolean
    getLimit,          // (resource) => number
    getUsage,          // (resource) => number
    hasReachedLimit,   // (resource) => boolean
    canAdd,            // (resource, amount?) => boolean
    isUnlimited,       // (resource) => boolean
    getUsagePercentage // (resource) => number (0-100)
} = usePlan();
```

**TypeScript Types** (`resources/js/types/index.d.ts`):
- `Plan`
- `PlanFeatures`
- `PlanLimits`
- `PlanUsage`

**⚠️ DO NOT:**
- Fetch plan data via separate API calls (use Inertia shared data)
- Duplicate feature checking logic in frontend
- Change the structure of shared plan data (breaks TypeScript types)

---

## 🎯 Supported Features & Limits

### Boolean Features (5)

| Feature          | Key              | Description                    | Plans           |
|------------------|------------------|--------------------------------|-----------------|
| Custom Roles     | `customRoles`    | Create custom roles/permissions| Pro+            |
| API Access       | `apiAccess`      | API tokens and webhooks        | Pro+            |
| Advanced Reports | `advancedReports`| Custom reports and exports     | Enterprise      |
| SSO              | `sso`            | Single Sign-On integration     | Enterprise      |
| White Label      | `whiteLabel`     | Custom branding                | Enterprise      |

### Rich Value Features (3)

| Feature         | Key             | Type    | Values                          |
|-----------------|-----------------|---------|----------------------------------|
| Max Users       | `maxUsers`      | integer | 5, 50, -1 (unlimited)           |
| Max Projects    | `maxProjects`   | integer | 10, -1 (unlimited)              |
| Storage Limit   | `storageLimit`  | integer | MB (1024, 10240, -1)            |

**Convention**:
- `-1` = Unlimited
- `0` = Feature disabled / No access
- Positive integer = Actual limit

**⚠️ DO NOT:**
- Use `null` or other values for "unlimited"
- Mix units (always use MB for storage, not KB/GB)
- Add features without creating Pennant feature class

---

## 🔐 Permission Structure

### Total: 37 Permissions

#### Base Permissions (22) - Available to Starter+
- **Projects** (8): `tenant.projects:view`, `create`, `edit`, `delete`, `manage`, `archive`, `restore`, `export`
- **Team** (5): `tenant.team:view`, `invite`, `edit`, `remove`, `viewActivity`
- **Settings** (3): `tenant.settings:view`, `edit`, `editSecurity`
- **Billing** (3): `tenant.billing:view`, `manage`, `viewInvoices`
- **API Tokens** (3): `tenant.apiTokens:view`, `create`, `delete`

#### Enterprise Permissions (15) - Available to Enterprise only
- **Custom Roles** (4): `tenant.roles:view`, `create`, `edit`, `delete`
- **Advanced Reports** (4): `tenant.reports:view`, `create`, `edit`, `export`
- **SSO** (3): `tenant.sso:view`, `configure`, `manage`
- **White Label** (4): `tenant.branding:view`, `edit`, `uploadLogo`, `customDomain`

**Wildcard Mapping**:
```json
{
  "customRoles": ["tenant.roles:*"],
  "advancedReports": ["tenant.reports:*"],
  "sso": ["tenant.sso:*"],
  "whiteLabel": ["tenant.branding:*"]
}
```

**⚠️ DO NOT:**
- Use wildcards outside `permission_map` (security risk)
- Change permission naming convention (`tenant.resource:action`)
- Remove base permissions from Starter plan
- Add permissions without running `sail artisan permissions:sync`

---

## 📦 The Three Plans

### 1. Starter ($29/month)

**Target**: Small teams, basic features

**Features**:
- ❌ Custom Roles
- ❌ API Access
- ❌ Advanced Reports
- ❌ SSO
- ❌ White Label

**Limits**:
- Users: 5
- Projects: 10
- Storage: 1 GB

**Permissions**: 8 (only Projects permissions)

---

### 2. Professional ($99/month)

**Target**: Growing teams, API access

**Features**:
- ✅ Custom Roles
- ✅ API Access
- ❌ Advanced Reports
- ❌ SSO
- ❌ White Label

**Limits**:
- Users: 50
- Projects: Unlimited (-1)
- Storage: 10 GB

**Permissions**: 22 (all base permissions)

---

### 3. Enterprise (Custom pricing)

**Target**: Large organizations, full control

**Features**:
- ✅ Custom Roles
- ✅ API Access
- ✅ Advanced Reports
- ✅ SSO
- ✅ White Label

**Limits**:
- Users: Unlimited
- Projects: Unlimited
- Storage: Unlimited

**Permissions**: 37 (all permissions)

---

## 🚫 What NOT to Do

### ❌ Common Anti-Patterns

1. **DON'T check features directly on Plan model**:
   ```php
   // ❌ WRONG
   if ($tenant->plan->features['customRoles'] ?? false) { }

   // ✅ CORRECT
   if (Feature::for($tenant)->active('customRoles')) { }
   ```

2. **DON'T bypass usage tracking**:
   ```php
   // ❌ WRONG
   User::create([...]);

   // ✅ CORRECT - Observer handles it automatically
   User::create([...]);
   $tenant->refresh(); // Usage updated via observer
   ```

3. **DON'T modify plan_enabled_permissions manually**:
   ```php
   // ❌ WRONG
   $tenant->update(['plan_enabled_permissions' => [...]]);

   // ✅ CORRECT
   $tenant->regeneratePlanPermissions(); // Handles cache
   ```

4. **DON'T create features without Pennant classes**:
   ```php
   // ❌ WRONG
   if ($tenant->plan->hasFeature('newFeature')) { }

   // ✅ CORRECT
   // 1. Create app/Features/NewFeature.php
   // 2. Register in AppServiceProvider
   // 3. Then use Feature::for($tenant)->active('newFeature')
   ```

5. **DON'T hardcode plan checks**:
   ```php
   // ❌ WRONG
   if ($tenant->plan->slug === 'enterprise') { }

   // ✅ CORRECT
   if (Feature::for($tenant)->active('sso')) { }
   ```

---

## ✅ How to Add New Features

### Step 1: Create Pennant Feature Class

```php
// app/Features/TwoFactorAuth.php
<?php

namespace App\Features;

use App\Models\Tenant;

class TwoFactorAuth
{
    public function resolve(Tenant $tenant): bool
    {
        if ($tenant->isOnTrial()) {
            return true;
        }

        if (isset($tenant->plan_features_override['twoFactorAuth'])) {
            return $tenant->plan_features_override['twoFactorAuth'];
        }

        return $tenant->plan?->hasFeature('twoFactorAuth') ?? false;
    }
}
```

### Step 2: Register Feature (if not using auto-discovery)

```php
// app/Providers/AppServiceProvider.php
Feature::discover();
// Auto-discovers all classes in app/Features/
```

### Step 3: Update Plan Seeds

```php
// database/seeders/PlanSeeder.php
Plan::create([
    // ...
    'features' => [
        'customRoles' => true,
        'apiAccess' => true,
        'twoFactorAuth' => true, // ← Add here
    ],
]);
```

### Step 4: Add Permission Mapping (if needed)

```php
'permission_map' => [
    'customRoles' => ['tenant.roles:*'],
    'twoFactorAuth' => ['tenant.security:configure2fa'], // ← Add here
],
```

### Step 5: Run Sync Command

```bash
sail artisan permissions:sync  # Create new permissions
sail artisan plans:sync-permissions  # Sync permission maps
```

### Step 6: Update Frontend Types

```typescript
// resources/js/types/index.d.ts
export interface PlanFeatures {
    customRoles: boolean;
    apiAccess: boolean;
    twoFactorAuth: boolean; // ← Add here
    // ...
}
```

### Step 7: Use in Code

```php
// Backend
if (Feature::for($tenant)->active('twoFactorAuth')) {
    // Enable 2FA settings
}

// Frontend
const { hasFeature } = usePlan();
if (hasFeature('twoFactorAuth')) {
    return <TwoFactorSettings />;
}
```

---

## ✅ How to Add New Limits

### Step 1: Create Pennant Feature Class (Rich Value)

```php
// app/Features/MaxTeams.php
<?php

namespace App\Features;

use App\Models\Tenant;

class MaxTeams
{
    public function resolve(Tenant $tenant): int
    {
        if (isset($tenant->plan_limits_override['teams'])) {
            return $tenant->plan_limits_override['teams'];
        }

        return $tenant->plan?->getLimit('teams') ?? 0;
    }
}
```

### Step 2: Update Plan Seeds

```php
// database/seeders/PlanSeeder.php
Plan::create([
    // ...
    'limits' => [
        'users' => 50,
        'projects' => -1,
        'teams' => 5, // ← Add here
    ],
]);
```

### Step 3: Create Observer for Tracking

```php
// app/Observers/TeamObserver.php
<?php

namespace App\Observers;

use App\Models\Team;

class TeamObserver
{
    public function created(Team $team): void
    {
        if (!tenancy()->initialized) {
            return;
        }

        $tenant = tenant();
        if ($tenant) {
            $tenant->incrementUsage('teams');
        }
    }

    public function deleted(Team $team): void
    {
        if (!tenancy()->initialized) {
            return;
        }

        $tenant = tenant();
        if ($tenant) {
            $tenant->decrementUsage('teams');
        }
    }
}
```

### Step 4: Register Observer

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    Team::observe(TeamObserver::class);
}
```

### Step 5: Update Frontend Types

```typescript
// resources/js/types/index.d.ts
export interface PlanLimits {
    users: number;
    projects: number;
    teams: number; // ← Add here
}

export interface PlanUsage {
    users: number;
    projects: number;
    teams: number; // ← Add here
}
```

### Step 6: Update HandleInertiaRequests

```php
// app/Http/Middleware/HandleInertiaRequests.php
protected function getPlanData($tenant): ?array
{
    // ...
    $limits = [
        'users' => Feature::for($tenant)->value('maxUsers'),
        'projects' => Feature::for($tenant)->value('maxProjects'),
        'teams' => Feature::for($tenant)->value('maxTeams'), // ← Add here
    ];

    $usage = [
        'users' => $tenant->getCurrentUsage('users'),
        'projects' => $tenant->getCurrentUsage('projects'),
        'teams' => $tenant->getCurrentUsage('teams'), // ← Add here
    ];
}
```

### Step 7: Use in Code

```php
// Backend
if ($tenant->hasReachedLimit('teams')) {
    return redirect()->back()->with('error', 'Team limit reached');
}

// Frontend
const { hasReachedLimit, getUsagePercentage } = usePlan();
if (hasReachedLimit('teams')) {
    return <UpgradePrompt />;
}
```

---

## 🧪 Testing Checklist

When making changes to the Plans system, run these tests:

```bash
# Full test suite
sail artisan test --filter=Plan

# Individual test suites
sail artisan test --filter=PlanTest                  # Plan model
sail artisan test --filter=PennantIntegrationTest    # Pennant features
sail artisan test --filter=PermissionSyncTest        # Permission sync
sail artisan test --filter=PlanLimitsTest            # Limits & usage
```

**Required Tests for New Features**:
1. Feature returns correct boolean for each plan
2. Trial tenants get access to new feature
3. Override works correctly
4. Permission mapping syncs correctly (if applicable)

**Required Tests for New Limits**:
1. Limit returns correct value for each plan
2. Usage tracking increments/decrements correctly
3. `hasReachedLimit()` works correctly
4. Unlimited (-1) never reaches limit
5. Override works correctly

---

## 📁 File Reference

### Core Files (DO NOT DELETE)

```
app/
├── Models/
│   ├── Plan.php                    # Plan model (10 methods)
│   └── Tenant.php                  # Tenant model (13 plan methods)
├── Features/                       # Pennant feature classes
│   ├── CustomRoles.php
│   ├── ApiAccess.php
│   ├── AdvancedReports.php
│   ├── Sso.php
│   ├── WhiteLabel.php
│   ├── MaxUsers.php
│   ├── MaxProjects.php
│   └── StorageLimit.php
├── Observers/
│   ├── TenantObserver.php          # Syncs permissions on plan change
│   ├── UserObserver.php            # Tracks user usage
│   └── ProjectObserver.php         # Tracks project usage
├── Console/Commands/
│   ├── SyncPermissions.php         # Base permission sync
│   └── SyncPlanPermissions.php     # Plan permission map sync
└── Http/Middleware/
    ├── CheckFeature.php            # Route protection by feature
    ├── CheckLimit.php              # Route protection by limit
    └── HandleInertiaRequests.php   # Shares plan data to frontend

database/
├── migrations/
│   ├── 2025_11_21_013521_create_plans_table.php
│   └── 2025_11_21_013523_add_plan_to_tenants_table.php
├── seeders/
│   └── PlanSeeder.php              # Seeds 3 plans
└── factories/
    └── PlanFactory.php             # Factory with states

resources/js/
├── hooks/
│   └── use-plan.ts                 # React hook (11 methods)
└── types/
    └── index.d.ts                  # TypeScript types

tests/Feature/
├── PlanTest.php                    # 6 tests
├── PennantIntegrationTest.php      # 7 tests
├── PermissionSyncTest.php          # 4 tests
└── PlanLimitsTest.php              # 6 tests
```

### Configuration Files

```
config/
└── pennant.php                     # Pennant configuration

bootstrap/
└── app.php                         # Middleware registration

app/Providers/
└── AppServiceProvider.php          # Feature discovery, observers
```

---

## 🎯 Common Tasks

### Change Plan Price

```php
// database/seeders/PlanSeeder.php
Plan::updateOrCreate(
    ['slug' => 'professional'],
    ['price' => 12900] // $129.00
);
```

### Add Feature to Existing Plan

```php
// database/seeders/PlanSeeder.php
$plan = Plan::where('slug', 'professional')->first();
$features = $plan->features;
$features['newFeature'] = true;
$plan->update(['features' => $features]);

// Then sync
sail artisan plans:sync-permissions
```

### Change Limit for Plan

```php
$plan = Plan::where('slug', 'starter')->first();
$limits = $plan->limits;
$limits['users'] = 10; // Increase from 5 to 10
$plan->update(['limits' => $limits]);
```

### Give Tenant Override

```php
// Enable feature for single tenant
$tenant->update([
    'plan_features_override' => ['customRoles' => true]
]);

// Increase limit for single tenant
$tenant->update([
    'plan_limits_override' => ['users' => 100]
]);

// Observer automatically regenerates permissions
```

### Start Trial

```php
$tenant->update([
    'trial_ends_at' => now()->addDays(14)
]);

// Trial tenants get all features enabled
```

### Check Feature in Blade

```blade
@if(Feature::for($tenant)->active('customRoles'))
    <a href="/roles">Manage Roles</a>
@endif
```

### Protect Route by Feature

```php
// routes/tenant.php
Route::middleware(['feature:customRoles'])->group(function () {
    Route::resource('roles', RoleController::class);
});
```

### Protect Route by Limit

```php
// routes/tenant.php
Route::post('/projects', [ProjectController::class, 'store'])
    ->middleware('limit:projects');
```

---

## 🔍 Debugging

### Check Tenant's Plan

```php
$tenant = Tenant::find(1);
dd([
    'plan' => $tenant->plan->name,
    'features' => $tenant->plan->features,
    'limits' => $tenant->plan->limits,
    'usage' => $tenant->current_usage,
]);
```

### Check Feature Resolution

```php
use Laravel\Pennant\Feature;

$tenant = Tenant::find(1);
dd([
    'customRoles' => Feature::for($tenant)->active('customRoles'),
    'maxUsers' => Feature::for($tenant)->value('maxUsers'),
]);
```

### Check Enabled Permissions

```php
$tenant = Tenant::find(1);
dd($tenant->getPlanEnabledPermissions());
```

### Verify Permission Sync

```bash
# Resync all plans
sail artisan plans:sync-permissions

# Check in tinker
sail artisan tinker
>>> $tenant = Tenant::find(1);
>>> $tenant->regeneratePlanPermissions();
>>> $tenant->plan_enabled_permissions;
```

### Clear Pennant Cache

```php
use Laravel\Pennant\Feature;

Feature::flushCache($tenant);
```

---

## 📚 Additional Documentation

For deeper dives, see:

- `docs/PLANS-HYBRID-ARCHITECTURE.md` - Complete architecture (80 pages)
- `docs/PLANS-IMPLEMENTATION-COMPLETE.md` - Implementation summary
- `docs/PLANS-IMPLEMENTATION-PLAN.md` - Original implementation plan
- `docs/PERMISSIONS.md` - Permission system details
- `docs/STANCL-FEATURES.md` - Multi-tenancy integration

---

## 🚨 Emergency Procedures

### Plans Not Working After Deploy

```bash
# 1. Verify migrations
sail artisan migrate:status

# 2. Resync permissions
sail artisan permissions:sync
sail artisan plans:sync-permissions

# 3. Clear all caches
sail artisan cache:clear
sail artisan config:clear

# 4. Verify plan data
sail artisan tinker
>>> Plan::count(); // Should be 3
>>> Plan::pluck('name', 'slug');
```

### Features Not Resolving

```bash
# 1. Verify feature classes exist
ls -la app/Features/

# 2. Clear Pennant cache
sail artisan cache:clear

# 3. Test feature resolution
sail artisan tinker
>>> use Laravel\Pennant\Feature;
>>> $tenant = Tenant::find(1);
>>> Feature::for($tenant)->active('customRoles');
```

### Permissions Not Syncing

```bash
# 1. Verify base permissions exist
sail artisan permissions:sync --fresh

# 2. Verify permission_map is populated
sail artisan tinker
>>> Plan::pluck('permission_map');

# 3. Force regenerate for all tenants
sail artisan tinker
>>> Tenant::each(fn($t) => $t->regeneratePlanPermissions());
```

---

**Last Updated**: 2025-11-21
**Version**: 1.0.0
**Status**: Production Ready
