<?php

namespace App\Observers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
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
