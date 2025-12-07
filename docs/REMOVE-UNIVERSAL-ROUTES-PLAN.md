# Implementation Plan: Remove Universal Routes

## Overview

This plan describes the refactoring to remove the universal routes pattern from the application. Universal routes were a mechanism to make routes work in both central and tenant contexts using the `universal` middleware flag. This approach, while convenient, adds complexity and deviates from Stancl/Tenancy v4 best practices which recommend clear separation between central and tenant routes.

### Current Architecture

```
routes/shared.php
    |
    v
AuthenticateUniversal middleware (detects guard)
    |
    v
Shared Controllers (use auth()->user())
    |
    v
Shared React Pages (shared/settings/*.tsx)
```

### Target Architecture

```
routes/central.php                    routes/tenant.php
    |                                     |
    v                                     v
auth:central middleware               auth:tenant middleware
    |                                     |
    v                                     v
Shared Controllers                   Shared Controllers
(use auth()->user())                 (use auth()->user())
    |                                     |
    v                                     v
Shared React Pages                   Shared React Pages
(shared/settings/*.tsx)              (shared/settings/*.tsx)
```

## Files to be Modified

### 1. Route Files

| File | Action | Description |
|------|--------|-------------|
| `routes/central.php` | **MODIFY** | Add settings routes for central admin users |
| `routes/tenant.php` | **MODIFY** | Add settings routes for tenant users |
| `bootstrap/app.php` | **MODIFY** | Remove `require base_path('routes/shared.php')` |
| `config/tenancy.php` | **MODIFY** | Update `impersonation.blocked_routes` to use new route names |

### 2. Files to be Deleted

| File | Reason |
|------|--------|
| `routes/shared.php` | No longer needed - routes duplicated in central.php and tenant.php |
| `app/Http/Middleware/Shared/AuthenticateUniversal.php` | No longer needed - each context uses its own guard |

### 3. Frontend Files to Modify

| File | Action | Description |
|------|--------|-------------|
| `resources/js/components/sidebar/central-nav-items.tsx` | **MODIFY** | Replace `shared.settings.*` with `central.admin.settings.*` routes |

### 4. Test Files to Modify

| File | Action | Description |
|------|--------|-------------|
| `tests/Feature/Settings/ProfileUpdateTest.php` | **MODIFY** | Update route names from `shared.settings.*` to `tenant.admin.user-settings.*` |
| `tests/Feature/Settings/PasswordUpdateTest.php` | **MODIFY** | Update route names from `shared.settings.*` to `tenant.admin.user-settings.*` |
| `tests/Feature/Settings/TwoFactorAuthenticationTest.php` | **MODIFY** | Update route names from `shared.settings.*` to `tenant.admin.user-settings.*` |

### 5. Controller Files to Modify

| File | Action | Description |
|------|--------|-------------|
| `app/Http/Controllers/Shared/Settings/ProfileController.php` | **MODIFY** | Change redirect from `shared.settings.profile.edit` to context-aware redirect |

## Step-by-Step Implementation

### Phase 1: Add Settings Routes to Central

**File: `routes/central.php`**

Add the following inside the authenticated central admin routes:

```php
/*
|------------------------------------------------------------------
| Admin Settings Routes (central.admin.settings.*)
|------------------------------------------------------------------
*/

Route::prefix('settings')
    ->name('settings.')
    ->group(function () {
        Route::redirect('/', '/admin/settings/profile');

        Route::get('profile', [App\Http\Controllers\Shared\Settings\ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [App\Http\Controllers\Shared\Settings\ProfileController::class, 'update'])->name('profile.update');
        Route::patch('profile/locale', [App\Http\Controllers\Shared\Settings\ProfileController::class, 'updateLocale'])->name('profile.locale');
        Route::delete('profile', [App\Http\Controllers\Shared\Settings\ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::get('password', [App\Http\Controllers\Shared\Settings\PasswordController::class, 'edit'])->name('password.edit');
        Route::put('password', [App\Http\Controllers\Shared\Settings\PasswordController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('password.update');

        Route::get('appearance', function () {
            return Inertia::render('shared/settings/appearance');
        })->name('appearance.edit');

        Route::get('two-factor', [App\Http\Controllers\Shared\Settings\TwoFactorAuthenticationController::class, 'show'])
            ->name('two-factor.show');
    });
```

### Phase 2: Add Settings Routes to Tenant

**File: `routes/tenant.php`**

Add the following inside the admin routes group:

```php
/*
|------------------------------------------------------------------
| User Settings Routes (tenant.admin.user-settings.*)
|------------------------------------------------------------------
*/

Route::prefix('settings')
    ->name('user-settings.')
    ->group(function () {
        Route::redirect('/', '/admin/settings/profile');

        Route::get('profile', [App\Http\Controllers\Shared\Settings\ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [App\Http\Controllers\Shared\Settings\ProfileController::class, 'update'])->name('profile.update');
        Route::patch('profile/locale', [App\Http\Controllers\Shared\Settings\ProfileController::class, 'updateLocale'])->name('profile.locale');
        Route::delete('profile', [App\Http\Controllers\Shared\Settings\ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::get('password', [App\Http\Controllers\Shared\Settings\PasswordController::class, 'edit'])->name('password.edit');
        Route::put('password', [App\Http\Controllers\Shared\Settings\PasswordController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('password.update');

        Route::get('appearance', function () {
            return Inertia::render('shared/settings/appearance');
        })->name('appearance.edit');

        Route::get('two-factor', [App\Http\Controllers\Shared\Settings\TwoFactorAuthenticationController::class, 'show'])
            ->name('two-factor.show');
    });
```

**Note:** Using `user-settings` prefix to avoid conflict with existing `tenant.admin.settings.*` routes (tenant organization settings).

### Phase 3: Update ProfileController Redirect

**File: `app/Http/Controllers/Shared/Settings/ProfileController.php`**

Change redirect from:
```php
return to_route('shared.settings.profile.edit');
```

To context-aware redirect:
```php
// Redirect based on context (central or tenant)
$route = tenancy()->initialized
    ? 'tenant.admin.user-settings.profile.edit'
    : 'central.admin.settings.profile.edit';

return to_route($route);
```

### Phase 4: Update Frontend Navigation

**File: `resources/js/components/sidebar/central-nav-items.tsx`**

Update imports (remove shared, add central routes):
```tsx
// Remove: import shared from '@/routes/shared';
// Add central settings when wayfinder regenerates
import admin from '@/routes/central/admin';
```

Update route references:
- `shared.settings.profile.edit.url()` -> `admin.settings.profile.edit.url()`
- `shared.settings.appearance.edit.url()` -> `admin.settings.appearance.edit.url()`

### Phase 5: Update Tenancy Config (Blocked Routes)

**File: `config/tenancy.php`**

Update impersonation blocked routes:
```php
'blocked_routes' => [
    // ...existing routes...

    // Password - prevent credential changes
    'tenant.admin.user-settings.password.*',
    'central.admin.settings.password.*',
    'password.update',

    // Two-factor authentication - prevent security changes
    'tenant.admin.user-settings.two-factor.*',
    'central.admin.settings.two-factor.*',
    'two-factor.*',

    // ...rest of routes...
],
```

### Phase 6: Update Test Files

**File: `tests/Feature/Settings/ProfileUpdateTest.php`**

Update all route references from `shared.settings.*` to `tenant.admin.user-settings.*`:
- `route('shared.settings.profile.edit')` -> `route('tenant.admin.user-settings.profile.edit')`
- `route('shared.settings.profile.update')` -> `route('tenant.admin.user-settings.profile.update')`
- `route('shared.settings.profile.destroy')` -> `route('tenant.admin.user-settings.profile.destroy')`

**File: `tests/Feature/Settings/PasswordUpdateTest.php`**

- `route('shared.settings.password.edit')` -> `route('tenant.admin.user-settings.password.edit')`
- `route('shared.settings.password.update')` -> `route('tenant.admin.user-settings.password.update')`

**File: `tests/Feature/Settings/TwoFactorAuthenticationTest.php`**

- `route('shared.settings.two-factor.show')` -> `route('tenant.admin.user-settings.two-factor.show')`

### Phase 7: Remove Shared Routes Loading

**File: `bootstrap/app.php`**

Remove the line:
```php
// Shared routes (work in both central and tenant contexts)
require base_path('routes/shared.php');
```

### Phase 8: Delete Legacy Files

Delete the following files:
1. `routes/shared.php`
2. `app/Http/Middleware/Shared/AuthenticateUniversal.php`

### Phase 9: Regenerate Wayfinder Routes

Run:
```bash
sail artisan wayfinder:generate --with-form
```

This will:
- Generate new routes for `central.admin.settings.*`
- Generate new routes for `tenant.admin.user-settings.*`
- Remove the `shared.settings.*` routes

### Phase 10: Update Documentation

**File: `docs/STANCL-FEATURES.md`**

Remove or update section about "Universal Routes - Via Middleware Flag" since this pattern is no longer used.

**File: `CLAUDE.md`**

Remove references to `routes/shared.php` and update route structure documentation.

## Testing Checklist

### Unit/Feature Tests

- [ ] Run `sail artisan test --filter ProfileUpdateTest` - All tests pass
- [ ] Run `sail artisan test --filter PasswordUpdateTest` - All tests pass
- [ ] Run `sail artisan test --filter TwoFactorAuthenticationTest` - All tests pass
- [ ] Run `sail artisan test` - All 430+ tests pass

### Manual Testing - Tenant Context

- [ ] Login as tenant user (john@acme.com at tenant1.localhost)
- [ ] Navigate to `/admin/settings/profile` - Page loads correctly
- [ ] Update profile name - Form submits and redirects correctly
- [ ] Navigate to `/admin/settings/password` - Page loads correctly
- [ ] Update password - Form submits correctly
- [ ] Navigate to `/admin/settings/appearance` - Page loads correctly
- [ ] Navigate to `/admin/settings/two-factor` - Page loads correctly

### Manual Testing - Central Admin Context

- [ ] Login as central admin (admin@setor3.app at localhost/admin/login)
- [ ] Navigate to `/admin/settings/profile` - Page loads correctly
- [ ] Update profile name - Form submits and redirects correctly
- [ ] Navigate to `/admin/settings/password` - Page loads correctly
- [ ] Update password - Form submits correctly
- [ ] Navigate to `/admin/settings/appearance` - Page loads correctly
- [ ] Navigate to `/admin/settings/two-factor` - Page loads correctly

### Sidebar Navigation

- [ ] Central admin sidebar shows Settings link pointing to `/admin/settings/profile`
- [ ] Tenant admin sidebar shows correct settings links

### Impersonation

- [ ] Central admin can impersonate tenant user
- [ ] Password change is blocked during impersonation
- [ ] 2FA settings are blocked during impersonation

## Rollback Strategy

If issues are encountered after deployment:

1. **Git Revert**: Revert all commits from this PR
   ```bash
   git revert --no-commit <commit-range>
   git commit -m "Revert: Remove universal routes (rollback)"
   ```

2. **Restore Files**:
   - Restore `routes/shared.php` from git history
   - Restore `app/Http/Middleware/Shared/AuthenticateUniversal.php` from git history

3. **Regenerate Routes**:
   ```bash
   sail artisan wayfinder:generate --with-form
   ```

4. **Clear Caches**:
   ```bash
   sail artisan cache:clear
   sail artisan route:clear
   sail artisan config:clear
   sail npm run build
   ```

## Benefits of This Change

1. **Clearer Route Separation**: Central and tenant routes are explicitly defined in their respective files
2. **No Magic Middleware**: No need for `AuthenticateUniversal` which dynamically detects context
3. **Follows v4 Best Practices**: Stancl/Tenancy v4 recommends clear separation via `RouteMode::CENTRAL` (default) and explicit tenant routes
4. **Simpler Mental Model**: Developers know exactly which routes belong to which context
5. **Better IDE Support**: Route names are explicit, improving autocomplete and navigation

## Potential Issues

1. **Route Name Changes**: Frontend components using `shared.settings.*` need updates
2. **Test Updates**: All tests referencing shared routes need updates
3. **Documentation**: CLAUDE.md and other docs reference shared routes
4. **Wayfinder Cache**: May need to clear and regenerate TypeScript routes

## Critical Files for Implementation

1. `/home/junior/git/react-starter-kit/routes/central.php` - Add central settings routes
2. `/home/junior/git/react-starter-kit/routes/tenant.php` - Add tenant user settings routes
3. `/home/junior/git/react-starter-kit/bootstrap/app.php` - Remove shared routes loading
4. `/home/junior/git/react-starter-kit/app/Http/Controllers/Shared/Settings/ProfileController.php` - Update redirect logic
5. `/home/junior/git/react-starter-kit/resources/js/components/sidebar/central-nav-items.tsx` - Update navigation routes
