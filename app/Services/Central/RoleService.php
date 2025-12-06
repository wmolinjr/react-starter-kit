<?php

namespace App\Services\Central;

use App\Enums\CentralPermission;
use App\Exceptions\RoleException;
use App\Models\Shared\Permission;
use App\Models\Shared\Role;
use Illuminate\Support\Collection;

/**
 * RoleService (Central)
 *
 * Handles all business logic for role management in central admin context.
 *
 * MULTI-DATABASE TENANCY:
 * - This service manages roles in the CENTRAL database
 * - All roles here are central admin roles (Super Admin, Central Admin)
 * - Tenant roles (owner, admin, member) are in each tenant's database
 */
class RoleService
{
    /**
     * Get all roles with counts.
     *
     * Returns Role models for use with RoleResource.
     *
     * @return Collection<int, Role>
     */
    public function getAllRoles(): Collection
    {
        return Role::query()
            ->withCount('users', 'permissions')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all permissions for role assignment.
     *
     * @return Collection<int, Permission>
     */
    public function getAllPermissions(): Collection
    {
        return Permission::query()
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
                'label' => CentralPermission::categoryTrans($category),
                'permissions' => $perms->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->trans('description'),
                ])->values()->all(),
            ])
            ->all();
    }

    /**
     * Create a new role.
     *
     * @param  array<string, mixed>  $data
     */
    public function createRole(array $data): Role
    {
        // Create central role (stored in central database)
        $role = Role::create([
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
            'guard_name' => 'tenant',
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
    public function updateRole(Role $role, array $data): Role
    {
        // Protected roles can't have their name changed
        if ($role->isProtected() && $data['name'] !== $role->name) {
            throw new RoleException(__('central.roles.cannot_change_protected_name'));
        }

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
            throw new RoleException(__('central.roles.cannot_delete_protected'));
        }

        // Cannot delete roles with users
        if ($role->users()->count() > 0) {
            throw new RoleException(__('central.roles.cannot_delete_with_users'));
        }

        $role->delete();
    }

    /**
     * Get role detail for show page.
     *
     * Returns Role model with relationships for use with RoleDetailResource.
     */
    public function getRoleDetail(Role $role): Role
    {
        return $role->load('permissions', 'users');
    }

    /**
     * Get role for edit page.
     *
     * Returns Role model with permissions for use with RoleEditResource.
     */
    public function getRoleForEdit(Role $role): Role
    {
        return $role->load('permissions');
    }

    /**
     * Check if role can be deleted.
     */
    public function canDelete(Role $role): bool
    {
        return ! $role->isProtected() && $role->users()->count() === 0;
    }
}
