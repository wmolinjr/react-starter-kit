<?php

namespace App\Jobs\Central;

use App\Enums\TenantRole;
use App\Models\Tenant\User;
use App\Models\Shared\Permission;
use App\Models\Shared\Role;
use App\Services\Central\PlanPermissionResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Database\Contracts\TenantWithDatabase;

/**
 * SeedTenantDatabase Job
 *
 * OPTION C: TENANT-ONLY USERS
 * - Runs after tenant database is created and migrated
 * - Seeds permissions based on tenant's plan
 * - Seeds default roles with plan-appropriate permissions
 * - Creates owner user in TENANT database (NOT central)
 *
 * PLAN-AWARE:
 * - Uses PlanPermissionResolver to determine which permissions to seed
 * - Only seeds permissions the tenant's plan allows
 * - Default roles get permissions filtered by role type (owner/admin/member)
 */
class SeedTenantDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected TenantWithDatabase $tenant;

    public function __construct(TenantWithDatabase $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle(PlanPermissionResolver $resolver): void
    {
        // Resolve permissions based on tenant's plan
        $allowedPermissions = $resolver->resolve($this->tenant);

        Log::info("SeedTenantDatabase: Seeding tenant {$this->tenant->id}", [
            'plan' => $this->tenant->plan?->slug,
            'permissions_count' => count($allowedPermissions),
        ]);

        $this->tenant->run(function () use ($allowedPermissions) {
            $this->seedPermissions($allowedPermissions);
            $this->seedRoles($allowedPermissions);
            $this->seedOwner();
        });

        // Save plan_enabled_permissions cache
        $this->tenant->forceFill([
            'plan_enabled_permissions' => $allowedPermissions,
        ])->saveQuietly();
    }

    /**
     * Seed permissions for this tenant based on their plan.
     * Category and description are derived from TenantPermission enum via accessors.
     *
     * PLAN-AWARE: Only seeds permissions that the tenant's plan allows.
     */
    protected function seedPermissions(array $allowedPermissions): void
    {
        foreach ($allowedPermissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'tenant',
            ]);
        }
    }

    /**
     * Seed default roles for this tenant with plan-appropriate permissions.
     * Uses TenantRole enum as single source of truth.
     *
     * PLAN-AWARE: Each role gets permissions filtered based on:
     * - Owner: All plan permissions
     * - Admin: Plan permissions minus billing/api-tokens/danger
     * - Member: Only view permissions (excluding sensitive categories)
     *
     * NOTE: We use setTranslations() for translatable fields because:
     * - JSON columns in PostgreSQL + Spatie HasTranslations can double-encode
     * - setTranslations() ensures proper encoding via the trait
     */
    protected function seedRoles(array $allowedPermissions): void
    {
        foreach (TenantRole::systemRoles() as $tenantRole) {
            $role = Role::firstOrCreate(
                ['name' => $tenantRole->value, 'guard_name' => 'tenant'],
                ['is_protected' => $tenantRole->isSystemRole()]
            );

            // Set translations using Spatie's method to avoid double-encoding
            $role->setTranslations('display_name', $tenantRole->displayName());
            $role->setTranslations('description', $tenantRole->description());
            $role->save();

            // Get permissions filtered by role type using TenantRole enum
            $rolePermissions = $tenantRole->filterPermissions($allowedPermissions);

            // Sync permissions (get permission models that exist in database)
            $permissionModels = Permission::whereIn('name', $rolePermissions)->get();
            $role->syncPermissions($permissionModels);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     * Required for Laravel Horizon tenant job filtering.
     */
    public function tags(): array
    {
        return [
            'tenant:' . $this->tenant->id,
            'seed-database',
        ];
    }

    /**
     * Create owner user in tenant database.
     *
     * OPTION C (TENANT-ONLY USERS):
     * - Reads owner data from tenant settings (_seed_owner)
     * - Creates User in TENANT database
     * - Assigns 'owner' role
     * - User does NOT exist in central database
     */
    protected function seedOwner(): void
    {
        // Get owner data from tenant settings
        $ownerData = $this->tenant->getSetting('_seed_owner');

        if (! $ownerData) {
            Log::info("SeedTenantDatabase: No owner data found for tenant {$this->tenant->id}");

            return;
        }

        // Create user in TENANT database
        $user = User::firstOrCreate(
            ['email' => $ownerData['email']],
            [
                'name' => $ownerData['name'],
                'password' => bcrypt($ownerData['password']),
                'email_verified_at' => now(),
            ]
        );

        // Assign owner role
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $user->assignRole($ownerRole);
            Log::info("SeedTenantDatabase: Created owner user {$user->email} in tenant {$this->tenant->id}");
        }
    }
}
