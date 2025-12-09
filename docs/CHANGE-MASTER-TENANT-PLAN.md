# Change Master Tenant Implementation Plan

## Overview

This plan implements the ability to change the master tenant in a federation group. This is a complex operation that requires careful data migration and validation.

## Current State Analysis

### Affected Tables

1. **`federation_groups`**
   - `master_tenant_id` - Points to current master

2. **`federated_users`**
   - `master_tenant_id` - Tenant that created/owns this federated identity
   - `master_tenant_user_id` - User ID in the master tenant's database

3. **`federated_user_links`**
   - `metadata.is_master` - Boolean flag indicating if this link is the master

### Current Constraints

- Master tenant cannot be removed from the group
- Only master can sync changes with `master_wins` strategy
- `master_tenant_user_id` references a user in the master tenant's database

---

## Implementation Tasks

### Task 1: Add Validation Rule for New Master

**File**: `app/Http/Requests/Central/ChangeMasterTenantRequest.php` (NEW)

```php
<?php

namespace App\Http\Requests\Central;

use App\Models\Central\FederationGroup;
use Illuminate\Foundation\Http\FormRequest;

class ChangeMasterTenantRequest extends FormRequest
{
    public function rules(): array
    {
        $group = $this->route('group');

        return [
            'new_master_tenant_id' => [
                'required',
                'uuid',
                'exists:tenants,id',
                // Must be a member of the group
                function ($attribute, $value, $fail) use ($group) {
                    if (!$group->hasTenant($value)) {
                        $fail(__('validation.federation.tenant_not_in_group'));
                    }
                },
                // Cannot be the current master
                function ($attribute, $value, $fail) use ($group) {
                    if ($group->master_tenant_id === $value) {
                        $fail(__('validation.federation.already_master'));
                    }
                },
            ],
            'confirm' => 'required|boolean|accepted',
        ];
    }
}
```

### Task 2: Service Method for Master Change

**File**: `app/Services/Central/FederationService.php`

Add method `changeMasterTenant()`:

```php
/**
 * Change the master tenant of a federation group.
 *
 * This is a complex operation that:
 * 1. Updates the group's master_tenant_id
 * 2. Updates all federated_users to point to new master
 * 3. Updates link metadata
 * 4. Optionally triggers a full re-sync
 *
 * @throws FederationException
 */
public function changeMasterTenant(
    FederationGroup $group,
    Tenant $newMaster,
    bool $triggerSync = true
): FederationGroup {
    $oldMaster = $group->masterTenant;

    // Validate new master is in group
    if (!$group->hasTenant($newMaster)) {
        throw FederationException::tenantNotInGroup($newMaster);
    }

    // Cannot change to same master
    if ($group->isMaster($newMaster)) {
        throw FederationException::alreadyMaster($newMaster);
    }

    return DB::transaction(function () use ($group, $newMaster, $oldMaster, $triggerSync) {
        // 1. Update group master
        $group->update(['master_tenant_id' => $newMaster->id]);

        // 2. Update all federated users in this group
        $this->migrateFederatedUsersToNewMaster($group, $oldMaster, $newMaster);

        // 3. Update link metadata
        $this->updateLinkMasterFlags($group, $oldMaster, $newMaster);

        // 4. Log the change
        $this->auditService->logMasterChanged($group, $oldMaster, $newMaster);

        // 5. Invalidate caches
        $this->cacheService->invalidateGroup($group->id);
        $this->cacheService->invalidateTenant($oldMaster->id);
        $this->cacheService->invalidateTenant($newMaster->id);

        // 6. Optionally trigger sync from new master
        if ($triggerSync) {
            dispatch(new SyncFromNewMaster($group, $newMaster));
        }

        return $group->fresh();
    });
}

/**
 * Migrate federated users to point to new master tenant.
 */
protected function migrateFederatedUsersToNewMaster(
    FederationGroup $group,
    Tenant $oldMaster,
    Tenant $newMaster
): void {
    // Get all federated users in this group
    $federatedUsers = FederatedUser::where('federation_group_id', $group->id)->get();

    foreach ($federatedUsers as $federatedUser) {
        // Find the user's link to the new master tenant
        $newMasterLink = FederatedUserLink::where('federated_user_id', $federatedUser->id)
            ->where('tenant_id', $newMaster->id)
            ->first();

        if ($newMasterLink) {
            // User exists in new master - update references
            $federatedUser->update([
                'master_tenant_id' => $newMaster->id,
                'master_tenant_user_id' => $newMasterLink->tenant_user_id,
                'last_sync_source' => $newMaster->id,
            ]);
        } else {
            // User doesn't exist in new master - they need to be created there
            // or we mark them as orphaned
            $federatedUser->update([
                'master_tenant_id' => $newMaster->id,
                'master_tenant_user_id' => null, // Will be set when user is created
                'status' => FederatedUser::STATUS_PENDING_MASTER_SYNC,
            ]);
        }
    }
}

/**
 * Update is_master flags in federated_user_links.
 */
protected function updateLinkMasterFlags(
    FederationGroup $group,
    Tenant $oldMaster,
    Tenant $newMaster
): void {
    // Get all federated user IDs in this group
    $federatedUserIds = FederatedUser::where('federation_group_id', $group->id)
        ->pluck('id');

    // Remove is_master from old master links
    FederatedUserLink::whereIn('federated_user_id', $federatedUserIds)
        ->where('tenant_id', $oldMaster->id)
        ->get()
        ->each(function ($link) {
            $metadata = $link->metadata ?? [];
            $metadata['is_master'] = false;
            $metadata['was_master_until'] = now()->toIso8601String();
            $link->update(['metadata' => $metadata]);
        });

    // Set is_master on new master links
    FederatedUserLink::whereIn('federated_user_id', $federatedUserIds)
        ->where('tenant_id', $newMaster->id)
        ->get()
        ->each(function ($link) {
            $metadata = $link->metadata ?? [];
            $metadata['is_master'] = true;
            $metadata['became_master_at'] = now()->toIso8601String();
            $link->update(['metadata' => $metadata]);
        });
}
```

### Task 3: Add New Status for Pending Sync

**File**: `app/Models/Central/FederatedUser.php`

Add constant:

```php
public const STATUS_PENDING_MASTER_SYNC = 'pending_master_sync';
```

### Task 4: Job for Syncing from New Master

**File**: `app/Jobs/Central/SyncFromNewMaster.php` (NEW)

```php
<?php

namespace App\Jobs\Central;

use App\Models\Central\FederatedUser;
use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncFromNewMaster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public FederationGroup $group,
        public Tenant $newMaster
    ) {}

    public function handle(): void
    {
        Log::info('Starting sync from new master', [
            'group_id' => $this->group->id,
            'new_master_id' => $this->newMaster->id,
        ]);

        // Get users pending master sync
        $pendingUsers = FederatedUser::where('federation_group_id', $this->group->id)
            ->where('status', FederatedUser::STATUS_PENDING_MASTER_SYNC)
            ->get();

        $this->newMaster->run(function () use ($pendingUsers) {
            foreach ($pendingUsers as $federatedUser) {
                $this->syncUserFromNewMaster($federatedUser);
            }
        });

        Log::info('Completed sync from new master', [
            'group_id' => $this->group->id,
            'users_synced' => $pendingUsers->count(),
        ]);
    }

    protected function syncUserFromNewMaster(FederatedUser $federatedUser): void
    {
        // Find local user in new master by email
        $localUser = \App\Models\Tenant\User::where('email', $federatedUser->global_email)->first();

        if ($localUser) {
            // Update federated user with new master's user data
            $federatedUser->update([
                'master_tenant_user_id' => $localUser->id,
                'synced_data' => $localUser->toFederationSyncData(),
                'last_synced_at' => now(),
                'last_sync_source' => $this->newMaster->id,
                'status' => FederatedUser::STATUS_ACTIVE,
                'sync_version' => $federatedUser->sync_version + 1,
            ]);

            Log::info('Synced federated user from new master', [
                'federated_user_id' => $federatedUser->id,
                'local_user_id' => $localUser->id,
            ]);
        } else {
            // User doesn't exist in new master - create them
            $this->createUserInNewMaster($federatedUser);
        }
    }

    protected function createUserInNewMaster(FederatedUser $federatedUser): void
    {
        $syncedData = $federatedUser->synced_data;

        $localUser = \App\Models\Tenant\User::create([
            'name' => $syncedData['name'] ?? 'User',
            'email' => $federatedUser->global_email,
            'password' => $syncedData['password_hash'] ?? \Hash::make(\Str::random(32)),
            'locale' => $syncedData['locale'] ?? 'en',
            'email_verified_at' => now(),
            'federated_user_id' => $federatedUser->id,
        ]);

        // Create link
        \App\Models\Central\FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->newMaster->id,
            'tenant_user_id' => $localUser->id,
            'sync_status' => \App\Models\Central\FederatedUserLink::STATUS_SYNCED,
            'last_synced_at' => now(),
            'metadata' => [
                'created_via' => 'master_change',
                'is_master' => true,
            ],
        ]);

        // Update federated user
        $federatedUser->update([
            'master_tenant_user_id' => $localUser->id,
            'status' => FederatedUser::STATUS_ACTIVE,
        ]);

        Log::info('Created user in new master during master change', [
            'federated_user_id' => $federatedUser->id,
            'new_local_user_id' => $localUser->id,
        ]);
    }
}
```

### Task 5: Controller Method

**File**: `app/Http/Controllers/Central/Admin/FederationGroupController.php`

Add method:

```php
/**
 * Change the master tenant of a federation group.
 */
public function changeMaster(
    ChangeMasterTenantRequest $request,
    FederationGroup $group
): RedirectResponse {
    $newMaster = Tenant::findOrFail($request->validated()['new_master_tenant_id']);

    try {
        $this->federationService->changeMasterTenant($group, $newMaster);

        return redirect()->route('central.admin.federation.show', $group)
            ->with('success', __('flash.federation.master_changed', [
                'tenant' => $newMaster->name,
            ]));
    } catch (FederationException $e) {
        return back()->with('error', $e->getMessage());
    }
}
```

### Task 6: Route

**File**: `routes/central.php`

Add route inside federation group:

```php
Route::post('/{group}/change-master', [FederationGroupController::class, 'changeMaster'])
    ->name('change-master');
```

### Task 7: Audit Service Method

**File**: `app/Services/Central/FederationAuditService.php`

Add method:

```php
/**
 * Log master tenant change.
 */
public function logMasterChanged(
    FederationGroup $group,
    Tenant $oldMaster,
    Tenant $newMaster
): void {
    $this->log(
        group: $group,
        action: 'master_changed',
        details: [
            'old_master_id' => $oldMaster->id,
            'old_master_name' => $oldMaster->name,
            'new_master_id' => $newMaster->id,
            'new_master_name' => $newMaster->name,
        ]
    );
}
```

### Task 8: Exception Methods

**File**: `app/Exceptions/Central/FederationException.php`

Add methods:

```php
public static function alreadyMaster(Tenant $tenant): self
{
    return new self("Tenant '{$tenant->name}' is already the master of this group.");
}
```

### Task 9: Frontend - Change Master Dialog

**File**: `resources/js/pages/central/admin/federation/components/change-master-dialog.tsx` (NEW)

```tsx
import { useForm } from '@inertiajs/react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AlertTriangle, Crown } from 'lucide-react';
import admin from '@/routes/central/admin';

interface Tenant {
    id: string;
    name: string;
    slug: string;
    is_master: boolean;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    groupId: string;
    groupName: string;
    currentMasterId: string;
    tenants: Tenant[];
}

export function ChangeMasterDialog({
    open,
    onOpenChange,
    groupId,
    groupName,
    currentMasterId,
    tenants,
}: Props) {
    const { t } = useLaravelReactI18n();
    const { data, setData, post, processing, reset } = useForm({
        new_master_tenant_id: '',
        confirm: false,
    });

    const availableTenants = tenants.filter((t) => t.id !== currentMasterId);

    const handleSubmit = () => {
        post(admin.federation.changeMaster.url(groupId), {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2">
                        <Crown className="h-5 w-5 text-yellow-500" />
                        {t('admin.federation.change_master.title')}
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {t('admin.federation.change_master.description', { group: groupName })}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div className="space-y-4 py-4">
                    <Alert variant="destructive">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertDescription>
                            {t('admin.federation.change_master.warning')}
                        </AlertDescription>
                    </Alert>

                    <div className="space-y-2">
                        <Label>{t('admin.federation.change_master.new_master')}</Label>
                        <Select
                            value={data.new_master_tenant_id}
                            onValueChange={(value) => setData('new_master_tenant_id', value)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('admin.federation.change_master.select_tenant')} />
                            </SelectTrigger>
                            <SelectContent>
                                {availableTenants.map((tenant) => (
                                    <SelectItem key={tenant.id} value={tenant.id}>
                                        {tenant.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="flex items-start gap-2">
                        <Checkbox
                            id="confirm"
                            checked={data.confirm}
                            onCheckedChange={(checked) => setData('confirm', checked === true)}
                        />
                        <Label htmlFor="confirm" className="text-sm leading-tight">
                            {t('admin.federation.change_master.confirm_text')}
                        </Label>
                    </div>
                </div>

                <AlertDialogFooter>
                    <AlertDialogCancel onClick={() => reset()}>
                        {t('common.cancel')}
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={handleSubmit}
                        disabled={!data.new_master_tenant_id || !data.confirm || processing}
                        className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    >
                        {t('admin.federation.change_master.confirm_button')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
```

### Task 10: Update Show Page with Change Master Button

**File**: `resources/js/pages/central/admin/federation/show.tsx`

Add button in the header actions and integrate the dialog.

### Task 11: Disable Master Select on Edit

**File**: `resources/js/pages/central/admin/federation/components/federation-group-form.tsx`

Disable the master tenant select when editing:

```tsx
<Select
    value={data.master_tenant_id}
    onValueChange={(value) => setData('master_tenant_id', value)}
    disabled={!!group?.id} // Disable on edit mode
>
```

Add hint text explaining why it's disabled and how to change master.

### Task 12: Translations

**Files**: `lang/en.json`, `lang/pt_BR.json`

```json
{
  "admin.federation.change_master.title": "Change Master Tenant",
  "admin.federation.change_master.description": "Change the master tenant for federation group \":group\"",
  "admin.federation.change_master.warning": "Changing the master tenant will update all federated user references. The new master's user data will become the authoritative source. This operation cannot be undone.",
  "admin.federation.change_master.new_master": "New Master Tenant",
  "admin.federation.change_master.select_tenant": "Select a tenant...",
  "admin.federation.change_master.confirm_text": "I understand that this will change the authoritative source for all federated users in this group.",
  "admin.federation.change_master.confirm_button": "Change Master",
  "admin.federation.change_master.button": "Change Master",
  "admin.federation.form.master_disabled_hint": "Master tenant cannot be changed here. Use \"Change Master\" on the group details page.",

  "flash.federation.master_changed": "Master tenant changed to :tenant successfully. Sync in progress.",

  "validation.federation.tenant_not_in_group": "The selected tenant is not a member of this federation group.",
  "validation.federation.already_master": "The selected tenant is already the master of this group."
}
```

### Task 13: Regenerate Wayfinder Routes

```bash
sail artisan wayfinder:generate --with-form
```

---

## File Changes Summary

| File | Action |
|------|--------|
| `app/Http/Requests/Central/ChangeMasterTenantRequest.php` | CREATE |
| `app/Services/Central/FederationService.php` | ADD methods |
| `app/Services/Central/FederationAuditService.php` | ADD method |
| `app/Models/Central/FederatedUser.php` | ADD constant |
| `app/Jobs/Central/SyncFromNewMaster.php` | CREATE |
| `app/Exceptions/Central/FederationException.php` | ADD method |
| `app/Http/Controllers/Central/Admin/FederationGroupController.php` | ADD method |
| `routes/central.php` | ADD route |
| `resources/js/pages/central/admin/federation/components/change-master-dialog.tsx` | CREATE |
| `resources/js/pages/central/admin/federation/components/federation-group-form.tsx` | MODIFY |
| `resources/js/pages/central/admin/federation/show.tsx` | MODIFY |
| `lang/en.json` | ADD translations |
| `lang/pt_BR.json` | ADD translations |

---

## Testing Checklist

1. [ ] Cannot change master to tenant not in group
2. [ ] Cannot change master to current master
3. [ ] Master change updates `federation_groups.master_tenant_id`
4. [ ] Master change updates all `federated_users.master_tenant_id`
5. [ ] Master change updates all `federated_users.master_tenant_user_id`
6. [ ] Old master links have `is_master: false`
7. [ ] New master links have `is_master: true`
8. [ ] Users not in new master are created via job
9. [ ] Audit log records the master change
10. [ ] Caches are invalidated
11. [ ] UI dialog requires confirmation checkbox
12. [ ] Master select is disabled on edit form
13. [ ] Success toast shown after change
14. [ ] Translations work (EN/PT_BR)

---

## Security Considerations

1. **Permission Required**: Only users with `federation:manage` permission can change master
2. **Confirmation Required**: Double-confirmation via checkbox prevents accidents
3. **Audit Trail**: All changes are logged with before/after state
4. **Transaction Safety**: All DB operations wrapped in transaction

---

## Rollback Strategy

If something goes wrong:

1. The transaction will rollback automatically on failure
2. To manually rollback after successful change:
   - Change master back to original tenant
   - Run sync job again

---

## Edge Cases Handled

| Case | Handling |
|------|----------|
| User exists in new master | Update references to new master's user |
| User doesn't exist in new master | Create user in new master from synced_data |
| New master has different user data | New master's data becomes authoritative |
| Sync job fails | User stays in `pending_master_sync` status |

---

## Estimated Complexity

- **Backend**: High (service methods, job, migrations)
- **Frontend**: Medium (dialog component, form changes)
- **Testing**: High (many edge cases to verify)
- **Risk**: Medium-High (data migration operation)

---

## Changelog

### v1.2.0 - Change Master Tenant Feature
- Added ability to change master tenant via dedicated dialog
- Disabled master select on edit form (use dedicated flow)
- Added sync job to handle users not in new master
- Added `pending_master_sync` status for federated users
- Full audit trail for master changes
