<?php

namespace App\Observers\Central;

use App\Models\Central\Tenant;
use App\Models\Shared\Permission;
use App\Models\Shared\Role;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

/**
 * CENTRAL OBSERVER
 *
 * Observes Tenant model in the central database.
 *
 * MULTI-DATABASE TENANCY:
 * - Each tenant has its own database
 * - Roles/permissions are per-tenant database (no tenant_id column)
 * - Must check if tenant database exists before initializing tenancy
 */
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
     *
     * MULTI-DATABASE TENANCY:
     * - Only runs if tenant database exists (not during creation)
     * - Initializes tenant context to switch to tenant database
     * - Roles are queried without tenant_id (each DB has its own roles)
     */
    protected function syncPermissionsForPlanChange(Tenant $tenant): void
    {
        // Check if tenant database exists (skip during initial creation)
        $database = $tenant->database()->getName();
        if (!$tenant->database()->manager()->databaseExists($database)) {
            Log::info("Skipping permission sync - tenant database {$database} does not exist yet");
            return;
        }

        Log::info("Syncing permissions for tenant {$tenant->id} after plan change");

        // Initialize tenancy context (switches to tenant database)
        tenancy()->initialize($tenant);

        try {
            // Refresh the plan relationship to get the new plan (not cached old one)
            $tenant->load('plan');

            // 1. Regenerate plan permissions cache (without triggering observer again)
            $permissions = $tenant->plan->getAllEnabledPermissions();
            $expanded = $tenant->plan->expandPermissions($permissions);

            // Use forceFill to bypass fillable and avoid triggering updated event
            $tenant->forceFill(['plan_enabled_permissions' => $expanded])->saveQuietly();

            // 2. Clear Pennant in-memory cache and purge stored values for this tenant
            Feature::flushCache();
            // Purge stored feature values so they get re-resolved
            Feature::purge();

            // 3. For each role in this tenant database, sync their permissions
            // based on what's enabled by the plan
            $this->syncRolePermissions($tenant, $expanded);

            // 4. Log activity (optional - only if activity log is available)
            if (function_exists('activity')) {
                activity()
                    ->performedOn($tenant)
                    ->withProperties([
                        'plan_name' => $tenant->plan?->slug,
                        'permissions_count' => count($expanded),
                    ])
                    ->log('Plan permissions synchronized');
            }

        } catch (\Exception $e) {
            Log::error("Failed to sync permissions for tenant {$tenant->id}: {$e->getMessage()}");
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Sync role permissions based on plan
     *
     * MULTI-DATABASE TENANCY:
     * - Queries roles from the tenant database (no tenant_id filter needed)
     */
    protected function syncRolePermissions(Tenant $tenant, array $enabledPermissions): void
    {
        // In multi-database tenancy, we're already in tenant context
        // Roles are in the tenant database without tenant_id column
        $roles = Role::all();

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
