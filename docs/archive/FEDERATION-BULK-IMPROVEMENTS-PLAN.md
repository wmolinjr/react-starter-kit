# Federation Bulk Improvements Plan

## Overview

This plan implements three improvements for the federation feature:
1. **Federate All** - One-click button to federate all local users at once
2. **Multiple Selection** - Checkboxes to select and federate multiple users
3. **Auto-Federate New Users** - Setting in the master tenant to automatically federate new users when they are created

## Current State Analysis

### Backend (`app/Services/Tenant/FederationService.php`)
- `federateUser(User $user)` - Federates a single user
- `getLocalOnlyUsers()` - Returns all local users not federated
- No bulk operation exists

### Frontend (`resources/js/pages/tenant/admin/settings/federation.tsx`)
- Dialog-based single user federation (Select dropdown)
- No bulk actions or checkboxes

### Federation Group Settings (`app/Models/Central/FederationGroup.php`)
- Has `settings` JSON field with `auto_create_on_login` (default: true)
- Form (`federation-group-form.tsx`) doesn't expose `auto_create_on_login`

---

## Implementation Tasks

### Task 1: Backend - Bulk Federation Service Method

**File**: `app/Services/Tenant/FederationService.php`

Add method `federateUsers(Collection $users)`:
```php
/**
 * Federate multiple users at once.
 *
 * @param Collection<User> $users
 * @return array{success: int, failed: int, errors: array}
 */
public function federateUsers(Collection $users): array
{
    $results = ['success' => 0, 'failed' => 0, 'errors' => []];

    foreach ($users as $user) {
        try {
            $this->federateUser($user);
            $results['success']++;
        } catch (FederationException $e) {
            $results['failed']++;
            $results['errors'][$user->email] = $e->getMessage();
        }
    }

    return $results;
}
```

### Task 2: Backend - Bulk Federation Request

**File**: `app/Http/Requests/Tenant/FederateBulkUsersRequest.php` (NEW)

```php
<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class FederateBulkUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|uuid|exists:users,id',
        ];
    }
}
```

### Task 3: Backend - Bulk Federation Controller Methods

**File**: `app/Http/Controllers/Tenant/Admin/FederationController.php`

Add two methods:

```php
/**
 * Federate all local users at once.
 */
public function federateAll(): RedirectResponse
{
    $localUsers = $this->federationService->getLocalOnlyUsers();

    if ($localUsers->isEmpty()) {
        return back()->with('info', __('flash.federation.no_local_users'));
    }

    $results = $this->federationService->federateUsers($localUsers);

    return back()->with('success', __('flash.federation.bulk_federated', [
        'success' => $results['success'],
        'failed' => $results['failed'],
    ]));
}

/**
 * Federate selected users.
 */
public function federateBulk(FederateBulkUsersRequest $request): RedirectResponse
{
    $users = User::whereIn('id', $request->validated()['user_ids'])
        ->whereNull('federated_user_id')
        ->get();

    if ($users->isEmpty()) {
        return back()->with('error', __('flash.federation.no_users_selected'));
    }

    $results = $this->federationService->federateUsers($users);

    return back()->with('success', __('flash.federation.bulk_federated', [
        'success' => $results['success'],
        'failed' => $results['failed'],
    ]));
}
```

### Task 4: Backend - Routes

**File**: `routes/tenant.php`

Add routes inside federation group:
```php
Route::post('/users/federate-all', [FederationController::class, 'federateAll'])->name('users.federate-all');
Route::post('/users/federate-bulk', [FederationController::class, 'federateBulk'])->name('users.federate-bulk');
```

### Task 5: Frontend Hook - Add Bulk Operations

**File**: `resources/js/hooks/shared/use-federation.ts`

Add to `TenantFederationReturn` interface:
```typescript
interface TenantFederationReturn {
    context: 'tenant';
    processingId: string | null;
    isProcessing: boolean;
    federateUser: (userId: string) => void;
    unfederateUser: (userId: string) => void;
    syncUser: (userId: string) => void;
    // NEW bulk operations
    federateAll: () => void;
    federateBulk: (userIds: string[]) => void;
}
```

Add operations to `tenantOperations`:
```typescript
federateAll: () => {
    handleRequest('federate-all', 'post', tenantAdmin.settings.federation.users.federateAll.url());
},
federateBulk: (userIds: string[]) => {
    handleRequest('federate-bulk', 'post', tenantAdmin.settings.federation.users.federateBulk.url(), { user_ids: userIds });
},
```

### Task 6: Frontend - Update Federation Page UI

**File**: `resources/js/pages/tenant/admin/settings/federation.tsx`

Changes:
1. Add state for selected users: `const [selectedUsers, setSelectedUsers] = useState<Set<string>>(new Set())`
2. Add checkboxes to each row in "Local Only Users" table
3. Add "Select All" checkbox in header
4. Add action bar with "Federate Selected" button when users are selected
5. Add "Federate All" button in card header

```tsx
// Header with Federate All button
<CardHeader>
    <div className="flex items-center justify-between">
        <div>
            <CardTitle>{t('tenant.federation.local_only_users')}</CardTitle>
            <CardDescription>{t('tenant.federation.local_only_users_description')}</CardDescription>
        </div>
        <div className="flex gap-2">
            {selectedUsers.size > 0 && (
                <Button
                    variant="secondary"
                    onClick={() => federateBulk(Array.from(selectedUsers))}
                    disabled={processingId === 'federate-bulk'}
                >
                    <LinkIcon className="mr-2 h-4 w-4" />
                    {t('tenant.federation.federate_selected', { count: selectedUsers.size })}
                </Button>
            )}
            <Button
                onClick={() => federateAll()}
                disabled={processingId === 'federate-all' || localOnlyUsers.length === 0}
            >
                <Users className="mr-2 h-4 w-4" />
                {t('tenant.federation.federate_all')}
            </Button>
        </div>
    </div>
</CardHeader>

// Table with checkboxes
<TableHeader>
    <TableRow>
        <TableHead className="w-12">
            <Checkbox
                checked={selectedUsers.size === localOnlyUsers.length && localOnlyUsers.length > 0}
                onCheckedChange={(checked) => {
                    if (checked) {
                        setSelectedUsers(new Set(localOnlyUsers.map(u => u.id)));
                    } else {
                        setSelectedUsers(new Set());
                    }
                }}
            />
        </TableHead>
        <TableHead>{t('common.user')}</TableHead>
        ...
    </TableRow>
</TableHeader>

// Row with checkbox
<TableCell>
    <Checkbox
        checked={selectedUsers.has(user.id)}
        onCheckedChange={(checked) => {
            const newSelection = new Set(selectedUsers);
            if (checked) {
                newSelection.add(user.id);
            } else {
                newSelection.delete(user.id);
            }
            setSelectedUsers(newSelection);
        }}
    />
</TableCell>
```

### Task 7: Backend - Auto-Federate Setting in FederationGroup

**File**: `app/Models/Central/FederationGroup.php`

The `shouldAutoCreateOnLogin()` method already exists (line 148). We need to add a new method for auto-federating new users:

```php
/**
 * Check if new users should be automatically federated.
 */
public function shouldAutoFederateNewUsers(): bool
{
    return $this->getSetting('auto_federate_new_users', false);
}
```

### Task 8: Frontend - Add Auto-Federate Toggle to Form

**File**: `resources/js/pages/central/admin/federation/components/federation-group-form.tsx`

1. Update `GroupData` interface:
```typescript
settings: {
    sync_password: boolean;
    sync_profile: boolean;
    sync_two_factor: boolean;
    sync_roles: boolean;
    auto_federate_new_users: boolean; // NEW
};
```

2. Add default value in useForm:
```typescript
settings: {
    ...
    auto_federate_new_users: group?.settings?.auto_federate_new_users ?? false,
}
```

3. Add toggle in Sync Settings card (after sync_roles):
```tsx
<div className="flex items-center justify-between rounded-lg border p-4 md:col-span-2">
    <div>
        <Label className="font-medium">{t('admin.federation.settings.auto_federate_new_users')}</Label>
        <p className="text-muted-foreground text-sm">
            {t('admin.federation.settings.auto_federate_new_users_description')}
        </p>
    </div>
    <Switch
        checked={data.settings.auto_federate_new_users}
        onCheckedChange={(checked) => updateSettings('auto_federate_new_users', checked)}
    />
</div>
```

### Task 9: Backend - Listen for User Creation Events

**File**: `app/Listeners/Tenant/AutoFederateNewUser.php` (NEW)

```php
<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\UserCreated;
use App\Services\Tenant\FederationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class AutoFederateNewUser implements ShouldQueue
{
    public function __construct(
        protected FederationService $federationService
    ) {}

    public function handle(UserCreated $event): void
    {
        $user = $event->user;

        // Check if tenant is federated
        $group = $this->federationService->getCurrentGroup();
        if (!$group) {
            return;
        }

        // Check if auto-federate is enabled
        if (!$group->shouldAutoFederateNewUsers()) {
            return;
        }

        // Skip if user is already federated
        if ($user->isFederated()) {
            return;
        }

        // Federate the new user
        $this->federationService->federateUser($user);
    }
}
```

### Task 10: Ensure UserCreated Event Exists

**File**: `app/Events/Tenant/UserCreated.php` (verify or create)

```php
<?php

namespace App\Events\Tenant;

use App\Models\Tenant\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user
    ) {}
}
```

### Task 11: Register Event Listener

**File**: `app/Providers/EventServiceProvider.php` (or `bootstrap/app.php`)

Add listener mapping:
```php
\App\Events\Tenant\UserCreated::class => [
    \App\Listeners\Tenant\AutoFederateNewUser::class,
],
```

### Task 12: Dispatch Event on User Creation

**File**: `app/Models/Tenant/User.php`

Add to boot method or Observer:
```php
protected static function booted(): void
{
    static::created(function (User $user) {
        event(new \App\Events\Tenant\UserCreated($user));
    });
}
```

### Task 13: Translations

**Files**: `lang/en/flash.php`, `lang/en/tenant.php`, `lang/en/admin.php`
**Files**: `lang/pt_BR/flash.php`, `lang/pt_BR/tenant.php`, `lang/pt_BR/admin.php`

Add translations:
```php
// flash.php
'federation' => [
    'bulk_federated' => ':success users federated successfully. :failed failed.',
    'no_local_users' => 'No local users to federate.',
    'no_users_selected' => 'No users selected for federation.',
],

// tenant.php
'federation' => [
    'federate_all' => 'Federate All',
    'federate_selected' => 'Federate Selected (:count)',
    'select_all' => 'Select All',
],

// admin.php
'federation' => [
    'settings' => [
        'auto_federate_new_users' => 'Auto-Federate New Users',
        'auto_federate_new_users_description' => 'Automatically federate new users when they are created in member tenants.',
    ],
],
```

### Task 14: Regenerate Wayfinder Routes

Run after adding routes:
```bash
sail artisan wayfinder:generate --with-form
```

---

## File Changes Summary

| File | Action |
|------|--------|
| `app/Services/Tenant/FederationService.php` | Add `federateUsers()` |
| `app/Http/Requests/Tenant/FederateBulkUsersRequest.php` | CREATE |
| `app/Http/Controllers/Tenant/Admin/FederationController.php` | Add 2 methods |
| `routes/tenant.php` | Add 2 routes |
| `resources/js/hooks/shared/use-federation.ts` | Add bulk operations |
| `resources/js/pages/tenant/admin/settings/federation.tsx` | Add checkboxes + buttons |
| `app/Models/Central/FederationGroup.php` | Add `shouldAutoFederateNewUsers()` |
| `resources/js/pages/central/admin/federation/components/federation-group-form.tsx` | Add toggle |
| `app/Events/Tenant/UserCreated.php` | CREATE (if not exists) |
| `app/Listeners/Tenant/AutoFederateNewUser.php` | CREATE |
| `app/Providers/EventServiceProvider.php` or User model | Register listener |
| `lang/en/*.php` | Add translations |
| `lang/pt_BR/*.php` | Add translations |

---

## Testing Checklist

1. [ ] Federate All button works and federates all local users
2. [ ] Checkbox selection works (individual and select all)
3. [ ] Federate Selected button federates only selected users
4. [ ] Error handling for failed federations
5. [ ] Auto-federate toggle appears in federation group form (master)
6. [ ] New users are automatically federated when setting is enabled
7. [ ] New users are NOT federated when setting is disabled
8. [ ] Translations display correctly (EN/PT_BR)

---

## Estimated Complexity

- **Total files to modify/create**: 13
- **Backend changes**: Medium complexity (service + controller + listener)
- **Frontend changes**: Medium complexity (checkboxes + state management)
- **Risk**: Low (additive changes, no breaking modifications)
