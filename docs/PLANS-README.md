# Plans System Documentation

**Complete documentation for the Hybrid Plans Architecture (Database + Laravel Pennant + Spatie Permission)**

---

## 📖 Documentation Index

### 🚨 Start Here (Essential Reading)

#### 1. **[PLANS-REFERENCE.md](PLANS-REFERENCE.md)** - Complete Reference Guide
**READ THIS FIRST before making any changes to the Plans system**

- Architecture overview (the three pillars)
- Core concepts (Plans, Tenant, Pennant, Permissions)
- What NOT to do (anti-patterns)
- How to add features and limits correctly
- File reference and common tasks
- Debug procedures

**When to use**: Before ANY modification to the Plans system.

**Size**: ~500 lines

---

#### 2. **[PLANS-QUICK-START.md](PLANS-QUICK-START.md)** - Quick Reference
**Quick guide for common tasks**

- Add new boolean feature (7 steps)
- Add new limit (7 steps)
- Protect routes
- Give tenant overrides
- Track usage manually
- Common mistakes

**When to use**: When you need to add a feature/limit quickly.

**Size**: ~250 lines

---

## 🏗️ System Overview

### Architecture

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

### Features Available

**Boolean Features (5)**:
- Custom Roles
- API Access
- Advanced Reports
- SSO
- White Label

**Limit Features (3)**:
- Max Users
- Max Projects
- Storage Limit

### Plans Available

1. **Starter** ($29/month)
   - Basic features
   - 5 users, 10 projects, 1GB storage
   - 8 permissions

2. **Professional** ($99/month)
   - Custom Roles + API Access
   - 50 users, unlimited projects, 10GB storage
   - 22 permissions

3. **Enterprise** (Custom)
   - All features
   - Unlimited everything
   - 37 permissions

---

## 🔑 Key Files

### Models
- `app/Models/Plan.php` - Plan model (10 methods)
- `app/Models/Tenant.php` - Tenant model with plan relationship (13 methods)

### Pennant Features
- `app/Features/CustomRoles.php`
- `app/Features/ApiAccess.php`
- `app/Features/AdvancedReports.php`
- `app/Features/Sso.php`
- `app/Features/WhiteLabel.php`
- `app/Features/MaxUsers.php`
- `app/Features/MaxProjects.php`
- `app/Features/StorageLimit.php`

### Observers
- `app/Observers/TenantObserver.php` - Syncs permissions on plan change
- `app/Observers/UserObserver.php` - Tracks user count
- `app/Observers/ProjectObserver.php` - Tracks project count

### Commands
- `app/Console/Commands/SyncPermissions.php` - Base permission sync
- `app/Console/Commands/SyncPlanPermissions.php` - Plan permission map sync

### Middleware
- `app/Http/Middleware/CheckFeature.php` - Route protection by feature
- `app/Http/Middleware/CheckLimit.php` - Route protection by limit
- `app/Http/Middleware/HandleInertiaRequests.php` - Shares plan data to frontend

### Frontend
- `resources/js/hooks/use-plan.ts` - React hook (11 utility methods)
- `resources/js/types/index.d.ts` - TypeScript types

### Database
- `database/migrations/2025_11_21_013521_create_plans_table.php`
- `database/migrations/2025_11_21_013523_add_plan_to_tenants_table.php`
- `database/seeders/PlanSeeder.php`
- `database/factories/PlanFactory.php`

### Tests
- `tests/Feature/PlanTest.php` (6 tests)
- `tests/Feature/PennantIntegrationTest.php` (7 tests)
- `tests/Feature/PermissionSyncTest.php` (4 tests)
- `tests/Feature/PlanLimitsTest.php` (6 tests)

**Total**: 23 tests, all passing ✅

---

## 🚀 Quick Commands

```bash
# Sync base permissions
sail artisan permissions:sync

# Sync plan permission maps
sail artisan plans:sync-permissions

# Seed plans
sail artisan db:seed --class=PlanSeeder

# Run all plan tests
sail artisan test --filter=Plan

# Full setup (correct order)
sail artisan migrate
sail artisan permissions:sync
sail artisan db:seed --class=PlanSeeder
sail artisan plans:sync-permissions
```

---

## 💡 Usage Examples

### Backend (Laravel)

#### Check Features
```php
use Laravel\Pennant\Feature;

if (Feature::for($tenant)->active('customRoles')) {
    // Show custom roles UI
}

$userLimit = Feature::for($tenant)->value('maxUsers'); // 50, -1, etc
```

#### Protect Routes
```php
// By feature
Route::get('/roles', RolesController::class)
    ->middleware('feature:customRoles');

// By limit
Route::post('/projects', [ProjectController::class, 'store'])
    ->middleware('limit:projects');
```

#### Track Usage
```php
$tenant->incrementUsage('storage', 1024); // +1GB
$tenant->decrementUsage('storage', 512);  // -512MB

if ($tenant->hasReachedLimit('storage')) {
    // Show upgrade prompt
}
```

### Frontend (React)

```tsx
import { usePlan } from '@/hooks/use-plan';

export default function MyComponent() {
    const {
        hasFeature,
        getLimit,
        getUsage,
        hasReachedLimit,
        getUsagePercentage
    } = usePlan();

    if (!hasFeature('customRoles')) {
        return <UpgradePrompt feature="Custom Roles" />;
    }

    const percentage = getUsagePercentage('users');
    const current = getUsage('users');
    const limit = getLimit('users');

    return (
        <div>
            <UsageBar percentage={percentage} />
            <p>{current} / {limit === -1 ? '∞' : limit} users</p>
        </div>
    );
}
```

---

## ⚠️ Important Rules

### DO NOT:
- ❌ Change the three-pillar architecture
- ❌ Bypass `Feature::for()` by checking plan JSON directly
- ❌ Modify `plan_enabled_permissions` manually
- ❌ Remove observer triggers
- ❌ Hardcode plan checks
- ❌ Skip permission sync after adding features

### DO:
- ✅ Use `Feature::for($tenant)->active()` for features
- ✅ Use helper methods for usage tracking
- ✅ Create Pennant feature classes for new features
- ✅ Update TypeScript types when adding features/limits
- ✅ Run tests after changes
- ✅ Read PLANS-REFERENCE.md before modifying

---

## 🧪 Testing

```bash
# Run all plan tests
sail artisan test --filter=Plan

# Individual test suites
sail artisan test --filter=PlanTest
sail artisan test --filter=PennantIntegrationTest
sail artisan test --filter=PermissionSyncTest
sail artisan test --filter=PlanLimitsTest
```

**Coverage**: 23 tests, all passing ✅

---

## 🐛 Troubleshooting

### Features Not Working
```bash
# 1. Verify feature classes exist
ls -la app/Features/

# 2. Clear cache
sail artisan cache:clear

# 3. Test in tinker
sail artisan tinker
>>> use Laravel\Pennant\Feature;
>>> $tenant = Tenant::find(1);
>>> Feature::for($tenant)->active('customRoles');
```

### Permissions Not Syncing
```bash
# 1. Resync permissions
sail artisan permissions:sync --fresh
sail artisan plans:sync-permissions

# 2. Verify permission_map
sail artisan tinker
>>> Plan::pluck('permission_map');

# 3. Force regenerate
>>> Tenant::each(fn($t) => $t->regeneratePlanPermissions());
```

### Usage Not Tracking
```bash
# Verify observers are registered
sail artisan tinker
>>> User::getObservableEvents();
>>> Project::getObservableEvents();
```

---

## 📊 Implementation Status

✅ **PRODUCTION READY**

- [x] Database schema
- [x] Plan model with 10 methods
- [x] Tenant model with 13 plan methods
- [x] 8 Pennant feature classes
- [x] Permission sync system
- [x] 3 observers (Tenant, User, Project)
- [x] 2 middleware (CheckFeature, CheckLimit)
- [x] Frontend integration (HandleInertiaRequests)
- [x] React hook with 11 methods
- [x] TypeScript types
- [x] 23 tests passing
- [x] Complete documentation

**Total**: ~3,500 lines of code

---

## 📚 Additional Resources

- **CLAUDE.md** - Project overview (includes Plans section)
- **docs/PERMISSIONS.md** - Permission system details
- **docs/STANCL-FEATURES.md** - Multi-tenancy integration

---

**Last Updated**: 2025-11-21
**Version**: 1.0.0
**Status**: Production Ready ✅
