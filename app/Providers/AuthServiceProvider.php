<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\User;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Project::class => ProjectPolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // ==========================================
        // SUPER ADMIN BYPASS
        // ==========================================

        Gate::before(function (User $user, string $ability) {
            if ($user->is_super_admin) {
                return true; // Super admin tem acesso total
            }
        });

        // ==========================================
        // TENANT-LEVEL GATES
        // ==========================================

        // Owner bypass (exceto billing, que é específico)
        Gate::before(function (User $user, string $ability) {
            if ($ability !== 'manage-billing' && tenancy()->initialized) {
                if ($user->isOwner()) {
                    return true;
                }
            }
        });

        // ==========================================
        // SPECIFIC GATES
        // ==========================================

        // Gerenciar equipe (admin e owner)
        Gate::define('manage-team', function (User $user) {
            return $user->hasAnyRole(['owner', 'admin']);
        });

        // Gerenciar billing (apenas owner)
        Gate::define('manage-billing', function (User $user) {
            return $user->isOwner();
        });

        // Gerenciar settings (admin e owner)
        Gate::define('manage-settings', function (User $user) {
            return $user->hasAnyRole(['owner', 'admin']);
        });

        // Criar recursos
        Gate::define('create-resources', function (User $user) {
            return $user->hasAnyRole(['owner', 'admin', 'member']);
        });

        // Ver recursos (todos autenticados do tenant)
        Gate::define('view-resources', function (User $user) {
            return tenancy()->initialized && $user->belongsToCurrentTenant();
        });
    }
}
