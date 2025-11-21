# Plans System - Quick Start Guide

**For detailed reference, see: [PLANS-REFERENCE.md](PLANS-REFERENCE.md)**

---

## 🚀 Add New Boolean Feature

**Example: Add "Two-Factor Auth" feature**

### 1. Create Pennant Feature Class
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

### 2. Update Plan Seeder
```php
// database/seeders/PlanSeeder.php
Plan::updateOrCreate(
    ['slug' => 'professional'],
    [
        // ... existing fields
        'features' => [
            'customRoles' => true,
            'apiAccess' => true,
            'twoFactorAuth' => true, // ← Add here
        ],
    ]
);
```

### 3. Add Permission Mapping (if needed)
```php
'permission_map' => [
    'customRoles' => ['tenant.roles:*'],
    'twoFactorAuth' => ['tenant.security:configure2fa'], // ← Add here
],
```

### 4. Run Commands
```bash
sail artisan permissions:sync           # Create new permissions
sail artisan plans:sync-permissions     # Sync permission maps
```

### 5. Update TypeScript Types
```typescript
// resources/js/types/index.d.ts
export interface PlanFeatures {
    customRoles: boolean;
    apiAccess: boolean;
    twoFactorAuth: boolean; // ← Add here
}
```

### 6. Update HandleInertiaRequests
```php
// app/Http/Middleware/HandleInertiaRequests.php
$features = [
    'customRoles' => Feature::for($tenant)->active('customRoles'),
    'apiAccess' => Feature::for($tenant)->active('apiAccess'),
    'twoFactorAuth' => Feature::for($tenant)->active('twoFactorAuth'), // ← Add here
];
```

### 7. Use in Code
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

## 🚀 Add New Limit

**Example: Add "Max Teams" limit**

### 1. Create Pennant Feature Class
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

### 2. Update Plan Seeder
```php
// database/seeders/PlanSeeder.php
Plan::updateOrCreate(
    ['slug' => 'professional'],
    [
        // ... existing fields
        'limits' => [
            'users' => 50,
            'projects' => -1,
            'teams' => 5, // ← Add here
        ],
    ]
);
```

### 3. Create Observer
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

### 4. Register Observer
```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    Team::observe(TeamObserver::class);
}
```

### 5. Update TypeScript Types
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

### 6. Update HandleInertiaRequests
```php
// app/Http/Middleware/HandleInertiaRequests.php
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
```

### 7. Use in Code
```php
// Backend
if ($tenant->hasReachedLimit('teams')) {
    return back()->with('error', 'Team limit reached');
}

// Frontend
const { hasReachedLimit, canAdd } = usePlan();
if (!canAdd('teams')) {
    return <UpgradePrompt resource="teams" />;
}
```

---

## 🚀 Protect Route by Feature

```php
// routes/tenant.php
Route::middleware(['feature:customRoles'])->group(function () {
    Route::resource('roles', RoleController::class);
});
```

---

## 🚀 Protect Route by Limit

```php
// routes/tenant.php
Route::post('/projects', [ProjectController::class, 'store'])
    ->middleware('limit:projects');
```

---

## 🚀 Give Tenant Override

```php
// Enable feature for specific tenant
$tenant->update([
    'plan_features_override' => ['customRoles' => true]
]);

// Increase limit for specific tenant
$tenant->update([
    'plan_limits_override' => ['users' => 100]
]);
```

---

## 🚀 Start Trial

```php
$tenant->update([
    'trial_ends_at' => now()->addDays(14)
]);

// Trial tenants get ALL features enabled
```

---

## 🚀 Change Plan

```php
$tenant->update(['plan_id' => $newPlan->id]);

// Observer automatically:
// - Regenerates plan permissions
// - Flushes Pennant cache
// - Updates plan_enabled_permissions
```

---

## 🚀 Track Usage Manually

```php
// Increment (e.g., file upload)
$tenant->incrementUsage('storage', $fileSizeInMB);

// Decrement (e.g., file delete)
$tenant->decrementUsage('storage', $fileSizeInMB);

// Check current usage
$current = $tenant->getCurrentUsage('storage');

// Check if limit reached
if ($tenant->hasReachedLimit('storage')) {
    // Show upgrade prompt
}
```

---

## 🚀 Frontend Usage

```tsx
import { usePlan } from '@/hooks/use-plan';

export default function MyComponent() {
    const {
        plan,                   // Full plan object
        hasFeature,             // (feature) => boolean
        hasReachedLimit,        // (resource) => boolean
        canAdd,                 // (resource, amount?) => boolean
        getUsagePercentage      // (resource) => number (0-100)
    } = usePlan();

    // Feature gate
    if (!hasFeature('customRoles')) {
        return <UpgradePrompt feature="Custom Roles" />;
    }

    // Limit check
    if (!canAdd('users', 5)) {
        return <UpgradePrompt resource="users" />;
    }

    // Usage display
    const percentage = getUsagePercentage('users');
    return <UsageBar percentage={percentage} />;
}
```

---

## 🚀 Commands Reference

```bash
# Sync base permissions
sail artisan permissions:sync

# Sync plan permission maps
sail artisan plans:sync-permissions

# Seed plans (3 plans: Starter, Professional, Enterprise)
sail artisan db:seed --class=PlanSeeder

# Run all plan tests
sail artisan test --filter=Plan

# Run specific test suite
sail artisan test --filter=PennantIntegrationTest
```

---

## 🚀 Debug Commands

```bash
# Check tenant's plan data
sail artisan tinker
>>> $tenant = Tenant::find(1);
>>> $tenant->plan->name;
>>> $tenant->plan->features;
>>> $tenant->plan->limits;
>>> $tenant->current_usage;

# Check feature resolution
>>> use Laravel\Pennant\Feature;
>>> Feature::for($tenant)->active('customRoles');
>>> Feature::for($tenant)->value('maxUsers');

# Check enabled permissions
>>> $tenant->getPlanEnabledPermissions();

# Regenerate permissions
>>> $tenant->regeneratePlanPermissions();

# Clear Pennant cache
>>> Feature::flushCache($tenant);
```

---

## ⚠️ Common Mistakes

### ❌ DON'T
```php
// DON'T check features directly
if ($tenant->plan->features['customRoles'] ?? false) { }

// DON'T modify plan_enabled_permissions manually
$tenant->update(['plan_enabled_permissions' => [...]]);

// DON'T hardcode plan checks
if ($tenant->plan->slug === 'enterprise') { }

// DON'T bypass usage tracking
$usage = $tenant->current_usage;
$usage['users']++;
$tenant->update(['current_usage' => $usage]);
```

### ✅ DO
```php
// DO use Pennant for features
if (Feature::for($tenant)->active('customRoles')) { }

// DO use helper for permissions
$tenant->regeneratePlanPermissions();

// DO use features instead of plan checks
if (Feature::for($tenant)->active('sso')) { }

// DO use helper methods for usage
$tenant->incrementUsage('users');
```

---

## 📚 Full Documentation

- **[PLANS-REFERENCE.md](PLANS-REFERENCE.md)** - Complete reference guide
- **[PLANS-IMPLEMENTATION-COMPLETE.md](PLANS-IMPLEMENTATION-COMPLETE.md)** - Implementation summary
- **[PLANS-HYBRID-ARCHITECTURE.md](PLANS-HYBRID-ARCHITECTURE.md)** - Architecture deep dive

---

**Last Updated**: 2025-11-21
