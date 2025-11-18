<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    /**
     * Determine whether the user can view any tenants.
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can see their own tenants
    }

    /**
     * Determine whether the user can view the tenant.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->hasAccessToTenant($tenant);
    }

    /**
     * Determine whether the user can create tenants.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can create tenants
    }

    /**
     * Determine whether the user can update the tenant.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        $role = $user->roleInTenant($tenant);

        return in_array($role, ['owner', 'admin']);
    }

    /**
     * Determine whether the user can delete the tenant.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->roleInTenant($tenant) === 'owner';
    }

    /**
     * Determine whether the user can manage members.
     */
    public function manageMembers(User $user, Tenant $tenant): bool
    {
        $role = $user->roleInTenant($tenant);

        return in_array($role, ['owner', 'admin']);
    }
}
