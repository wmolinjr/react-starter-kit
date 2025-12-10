<?php

namespace App\Services\Tenant;

use App\Enums\TenantPermission;
use App\Exceptions\Shared\RoleException;
use App\Models\Central\Tenant;
use App\Models\Shared\Permission;
use App\Models\Shared\Role;
use App\Models\Tenant\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RoleService (Tenant)
 *
 * Handles all business logic for role management in tenant context.
 *
 * MULTI-DATABASE TENANCY:
 * - All roles in tenant database belong to that tenant (no tenant_id needed)
 * - Users are in tenant database via model_has_roles table
 * - Each tenant has isolated roles/permissions tables
 */
class RoleService
{
    /**
     * Get all roles with stats.
     *
     * Returns Role models for use with RoleResource.
     * Note: users_count needs special handling via withCount callback.
     *
     * @return Collection<int, Role>
     */
    public function getRolesWithStats(): Collection
    {
        return Role::withCount('permissions')
            ->orderBy('name')
            ->get()
            ->each(function (Role $role) {
                // Add users_count manually since we need to count from model_has_roles
                $role->setAttribute('users_count', $this->getUserCountForRole($role->id));
            });
    }

    /**
     * Get user count for a role from model_has_roles table.
     */
    public function getUserCountForRole(string $roleId): int
    {
        return DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_type', 'user')
            ->count();
    }

    /**
     * Get plan info for custom roles.
     *
     * @return array{canCreateCustomRoles: bool, customRolesLimit: int, customRolesCount: int, hasReachedLimit: bool, planName: string|null}
     */
    public function getPlanInfo(Tenant $tenant): array
    {
        $customRolesCount = Role::where('is_protected', false)->count();
        $customRolesLimit = $tenant->getLimit('customRoles');

        return [
            'canCreateCustomRoles' => $customRolesLimit !== 0,
            'customRolesLimit' => $customRolesLimit,
            'customRolesCount' => $customRolesCount,
            'hasReachedLimit' => $tenant->hasReachedLimit('customRoles'),
            'planName' => $tenant->plan?->name,
        ];
    }

    /**
     * Get allowed permissions based on tenant's plan.
     *
     * @return Collection<int, Permission>
     */
    public function getAllowedPermissions(Tenant $tenant): Collection
    {
        $allowedPermissions = $tenant->getPlanEnabledPermissions();

        return Permission::query()
            ->whereIn('name', $allowedPermissions)
            ->orderBy('name')
            ->get();
    }

    /**
     * Format permissions grouped by category with translated labels.
     *
     * @param  Collection<int, Permission>  $permissions
     * @return array<string, array{label: string, permissions: array}>
     */
    public function formatPermissionsByCategory(Collection $permissions): array
    {
        return $permissions
            ->groupBy(fn ($p) => $p->category)
            ->sortKeys()
            ->map(fn ($perms, $category) => [
                'label' => TenantPermission::categoryTrans($category),
                'permissions' => $perms->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->trans('description'),
                ])->values()->all(),
            ])
            ->all();
    }

    /**
     * Check if tenant can create custom roles.
     *
     * @throws RoleException
     */
    public function validateCanCreateRole(Tenant $tenant): void
    {
        $customRolesLimit = $tenant->getLimit('customRoles');

        if ($customRolesLimit === 0) {
            throw new RoleException(__('tenant.roles.custom_roles_not_available'));
        }

        if ($customRolesLimit !== -1) {
            $customRolesCount = Role::where('is_protected', false)->count();
            if ($customRolesCount >= $customRolesLimit) {
                throw new RoleException(__('tenant.roles.limit_reached'));
            }
        }
    }

    /**
     * Filter permission IDs to only include those allowed by plan.
     *
     * @param  array<int, string>  $permissionIds
     * @return array<int, string>
     */
    public function filterAllowedPermissions(Tenant $tenant, array $permissionIds): array
    {
        if (empty($permissionIds)) {
            return [];
        }

        $allowedPermissions = $tenant->getPlanEnabledPermissions();

        return Permission::whereIn('id', $permissionIds)
            ->whereIn('name', $allowedPermissions)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Create a new role.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws RoleException
     */
    public function createRole(Tenant $tenant, array $data): Role
    {
        $this->validateCanCreateRole($tenant);

        // Filter permissions by plan
        $data['permissions'] = $this->filterAllowedPermissions(
            $tenant,
            $data['permissions'] ?? []
        );

        // Create role (no tenant_id needed - isolation at database level)
        $role = Role::create([
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
            'guard_name' => 'tenant',
            'is_protected' => false, // Custom roles are not protected
        ]);

        // Sync permissions
        if (! empty($data['permissions'])) {
            $permissionNames = Permission::whereIn('id', $data['permissions'])->pluck('name');
            $role->syncPermissions($permissionNames);
        }

        return $role;
    }

    /**
     * Update an existing role.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws RoleException
     */
    public function updateRole(Role $role, Tenant $tenant, array $data): Role
    {
        // Protected roles can't have their name changed
        if ($role->isProtected() && $data['name'] !== $role->name) {
            throw new RoleException(__('tenant.roles.cannot_change_protected_name'));
        }

        // Filter permissions by plan
        $data['permissions'] = $this->filterAllowedPermissions(
            $tenant,
            $data['permissions'] ?? []
        );

        $role->update([
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
        ]);

        // Sync permissions
        $permissionNames = [];
        if (! empty($data['permissions'])) {
            $permissionNames = Permission::whereIn('id', $data['permissions'])->pluck('name');
        }
        $role->syncPermissions($permissionNames);

        return $role->fresh();
    }

    /**
     * Delete a role.
     *
     * @throws RoleException
     */
    public function deleteRole(Role $role): void
    {
        // Cannot delete protected roles
        if ($role->isProtected()) {
            throw new RoleException(__('tenant.roles.cannot_delete_protected'));
        }

        // Cannot delete roles with users
        if ($this->getUserCountForRole($role->id) > 0) {
            throw new RoleException(__('tenant.roles.cannot_delete_with_users'));
        }

        $role->delete();
    }

    /**
     * Get role detail for show page.
     *
     * Returns Role model with relationships for use with RoleDetailResource.
     * Sets users relationship manually since it uses model_has_roles.
     */
    public function getRoleDetail(Role $role): Role
    {
        $role->load('permissions');

        // Get users from tenant database via model_has_roles
        $userIds = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', 'user')
            ->pluck('model_id');

        $users = User::whereIn('id', $userIds)
            ->select('id', 'name', 'email')
            ->get();

        // Set users as a relationship for the Resource
        $role->setRelation('users', $users);

        return $role;
    }

    /**
     * Get role for edit page.
     *
     * Returns Role model with filtered permissions for use with RoleEditResource.
     */
    public function getRoleForEdit(Role $role, Tenant $tenant): Role
    {
        $role->load('permissions');
        $allowedPermissions = $tenant->getPlanEnabledPermissions();

        // Filter role's current permissions to only show allowed ones
        $filteredPermissions = $role->permissions
            ->filter(fn ($p) => in_array($p->name, $allowedPermissions));

        // Override permissions relationship with filtered ones
        $role->setRelation('permissions', $filteredPermissions);

        return $role;
    }

    /**
     * Check if role can be deleted.
     */
    public function canDelete(Role $role): bool
    {
        return ! $role->isProtected() && $this->getUserCountForRole($role->id) === 0;
    }
}
