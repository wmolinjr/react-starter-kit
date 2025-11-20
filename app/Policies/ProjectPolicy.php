<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tenant.projects:view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Project $project): bool
    {
        // Verificar se project pertence ao tenant atual
        return $project->tenant_id === current_tenant_id()
            && $user->can('tenant.projects:view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('tenant.projects:create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): bool
    {
        // Pode editar se tem permission "edit" (qualquer project)
        if ($user->can('tenant.projects:edit')) {
            return true;
        }

        // Ou se tem permission "editOwn" E é o criador do project
        return $user->can('tenant.projects:editOwn') && $project->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        // Apenas quem tem permission de delete OU é o criador do project
        return $user->can('tenant.projects:delete')
            || $project->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
    {
        // Restore requer permission de archive
        return $user->can('tenant.projects:archive');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        // Force delete requer permission de delete (apenas owners têm)
        return $user->can('tenant.projects:delete');
    }

    /**
     * Determine whether the user can upload files to the project.
     */
    public function uploadFiles(User $user, Project $project): bool
    {
        return $user->can('tenant.projects:upload');
    }

    /**
     * Determine whether the user can download files from the project.
     */
    public function downloadFiles(User $user, Project $project): bool
    {
        return $user->can('tenant.projects:download');
    }

    /**
     * Determine whether the user can archive/unarchive the project.
     */
    public function archive(User $user, Project $project): bool
    {
        return $user->can('tenant.projects:archive');
    }
}
