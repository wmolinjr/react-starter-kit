<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;

class PagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // User must have access to the current tenant
        return $user->current_tenant_id !== null
            && $user->hasAccessToTenant(
                Tenant::find($user->current_tenant_id)
            );
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Page $page): bool
    {
        // User must be member of the page's tenant
        $tenant = $page->tenant;

        return $user->hasAccessToTenant($tenant);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // User must have current tenant and role owner or admin
        if (!$user->current_tenant_id) {
            return false;
        }

        $tenant = Tenant::find($user->current_tenant_id);
        if (!$tenant) {
            return false;
        }

        $role = $user->roleInTenant($tenant);

        return in_array($role, ['owner', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Page $page): bool
    {
        $tenant = $page->tenant;
        $role = $user->roleInTenant($tenant);

        return in_array($role, ['owner', 'admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Page $page): bool
    {
        $tenant = $page->tenant;
        $role = $user->roleInTenant($tenant);

        return $role === 'owner';
    }

    /**
     * Determine whether the user can publish/unpublish the model.
     */
    public function publish(User $user, Page $page): bool
    {
        $tenant = $page->tenant;
        $role = $user->roleInTenant($tenant);

        return in_array($role, ['owner', 'admin']);
    }

    /**
     * Determine whether the user can manage blocks.
     */
    public function manageBlocks(User $user, Page $page): bool
    {
        $tenant = $page->tenant;
        $role = $user->roleInTenant($tenant);

        return in_array($role, ['owner', 'admin']);
    }

    /**
     * Determine whether the user can view versions.
     */
    public function viewVersions(User $user, Page $page): bool
    {
        $tenant = $page->tenant;

        return $user->hasAccessToTenant($tenant);
    }
}
