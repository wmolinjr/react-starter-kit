<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Tenant\Project;
use App\Models\Tenant\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::PROJECTS_VIEW);
    }

    /**
     * Determine whether the user can view the model.
     *
     * MULTI-DATABASE TENANCY: No need to check tenant_id - isolation is at database level.
     * The project is already guaranteed to belong to the current tenant because it's
     * stored in the tenant's dedicated database.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->can(TenantPermission::PROJECTS_VIEW);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::PROJECTS_CREATE);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): bool
    {
        // Pode editar se tem permission "edit" (qualquer project)
        if ($user->can(TenantPermission::PROJECTS_EDIT)) {
            return true;
        }

        // Ou se tem permission "editOwn" E é o criador do project
        return $user->can(TenantPermission::PROJECTS_EDIT_OWN) && $project->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        // Apenas quem tem permission de delete OU é o criador do project
        return $user->can(TenantPermission::PROJECTS_DELETE)
            || $project->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
    {
        // Restore requer permission de archive
        return $user->can(TenantPermission::PROJECTS_ARCHIVE);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        // Force delete requer permission de delete (apenas owners têm)
        return $user->can(TenantPermission::PROJECTS_DELETE);
    }

    /**
     * Determine whether the user can upload files to the project.
     */
    public function uploadFiles(User $user, Project $project): bool
    {
        return $user->can(TenantPermission::PROJECTS_UPLOAD);
    }

    /**
     * Determine whether the user can download files from the project.
     */
    public function downloadFiles(User $user, Project $project): bool
    {
        return $user->can(TenantPermission::PROJECTS_DOWNLOAD);
    }

    /**
     * Determine whether the user can archive/unarchive the project.
     */
    public function archive(User $user, Project $project): bool
    {
        return $user->can(TenantPermission::PROJECTS_ARCHIVE);
    }
}
