<?php

namespace App\Jobs;

use App\Enums\TenantRole;
use App\Models\Central\Tenant;
use App\Models\Shared\Permission;
use App\Models\Shared\Role;
use App\Services\Central\PlanPermissionResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

/**
 * SyncTenantPermissions Job
 *
 * Synchronizes tenant permissions based on their subscription plan.
 * This job runs within the tenant context to modify the tenant's
 * permissions and roles tables.
 *
 * TRIGGERS:
 * - Plan change (via TenantObserver)
 * - Manual command (tenant:sync-permissions)
 * - Cashier webhook (subscription updated)
 *
 * MULTI-DATABASE TENANCY:
 * - Runs inside $tenant->run() to switch database context
 * - Permissions/roles are in tenant database
 * - Plan is in central database
 */
class SyncTenantPermissions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry the job.
     */
    public int $tries = 3;

    /**
     * Backoff strategy (seconds).
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public Tenant $tenant,
        public bool $isDowngrade = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PlanPermissionResolver $resolver): void
    {
        Log::info("SyncTenantPermissions: Starting for tenant {$this->tenant->id}", [
            'plan' => $this->tenant->plan?->slug,
            'is_downgrade' => $this->isDowngrade,
        ]);

        // Resolve new permissions based on plan
        $allowedPermissions = $resolver->resolve($this->tenant);

        if (empty($allowedPermissions)) {
            Log::warning("SyncTenantPermissions: No permissions resolved for tenant {$this->tenant->id}");
            return;
        }

        // Run inside tenant context
        $this->tenant->run(function () use ($allowedPermissions) {
            DB::beginTransaction();

            try {
                // 1. Ensure all allowed permissions exist in tenant database
                $this->ensurePermissionsExist($allowedPermissions);

                // 2. If downgrade, remove unauthorized permissions from roles
                if ($this->isDowngrade) {
                    $this->removeUnauthorizedPermissionsFromRoles($allowedPermissions);
                }

                // 3. Update default roles (owner, admin, member)
                $this->syncDefaultRoles($allowedPermissions);

                DB::commit();

                Log::info("SyncTenantPermissions: Completed for tenant {$this->tenant->id}", [
                    'permissions_count' => count($allowedPermissions),
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("SyncTenantPermissions: Failed for tenant {$this->tenant->id}", [
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });

        // 4. Update cache in central database (outside tenant context)
        $this->tenant->forceFill([
            'plan_enabled_permissions' => $allowedPermissions,
        ])->saveQuietly();

        // 5. Clear Pennant cache
        Feature::flushCache($this->tenant);
    }

    /**
     * Ensure all required permissions exist in tenant database.
     * Category and description are derived from TenantPermission enum via accessors.
     */
    protected function ensurePermissionsExist(array $permissions): void
    {
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'tenant',
            ]);
        }
    }

    /**
     * Remove permissions that are no longer allowed from all roles.
     */
    protected function removeUnauthorizedPermissionsFromRoles(array $allowedPermissions): void
    {
        // Get IDs of allowed permissions
        $allowedIds = Permission::whereIn('name', $allowedPermissions)->pluck('id');

        if ($allowedIds->isEmpty()) {
            return;
        }

        // Remove unauthorized permissions from pivot table
        $removed = DB::table('role_has_permissions')
            ->whereNotIn('permission_id', $allowedIds)
            ->delete();

        if ($removed > 0) {
            Log::info("SyncTenantPermissions: Removed {$removed} unauthorized permission assignments");
        }

        // Clear Spatie permission cache
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Sync permissions for default roles (owner, admin, member).
     * Uses TenantRole enum as single source of truth.
     */
    protected function syncDefaultRoles(array $allowedPermissions): void
    {
        foreach (TenantRole::systemRoles() as $tenantRole) {
            $role = Role::where('name', $tenantRole->value)->first();

            if ($role) {
                // Use TenantRole enum for filtering
                $rolePermissions = $tenantRole->filterPermissions($allowedPermissions);

                // Get permission models
                $permissionModels = Permission::whereIn('name', $rolePermissions)->get();

                // Sync permissions (removes old, adds new)
                $role->syncPermissions($permissionModels);

                Log::debug("SyncTenantPermissions: Synced {$tenantRole->value} with " . count($rolePermissions) . " permissions");
            }
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SyncTenantPermissions: Job failed for tenant {$this->tenant->id}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return [
            'tenant:' . $this->tenant->id,
            'sync-permissions',
        ];
    }
}
