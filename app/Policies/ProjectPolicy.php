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
        return $user->hasAnyRole(['owner', 'admin', 'member', 'guest']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Project $project): bool
    {
        // Verificar se project pertence ao tenant atual
        return $project->tenant_id === current_tenant_id();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['owner', 'admin', 'member']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): bool
    {
        // Owners e admins podem editar qualquer project
        if ($user->hasAnyRole(['owner', 'admin'])) {
            return true;
        }

        // Members podem editar apenas seus próprios projects
        return $user->hasRole('member') && $project->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        // Apenas owner, admin ou criador do project
        return $user->hasAnyRole(['owner', 'admin'])
            || $project->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
    {
        // Apenas owners e admins podem restaurar
        return $user->hasAnyRole(['owner', 'admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        // Apenas owners podem deletar permanentemente
        return $user->isOwner();
    }
}
