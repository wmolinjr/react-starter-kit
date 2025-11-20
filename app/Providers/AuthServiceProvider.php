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
        // OWNER BYPASS (Legacy Gates Only)
        // ==========================================

        // Owner bypass APENAS para gates legacy (sem prefixo tenant.)
        // Permissions novas (tenant.*) devem ser respeitadas
        Gate::before(function (User $user, string $ability) {
            // Não aplicar bypass para permissions com prefixo tenant.
            if (str_starts_with($ability, 'tenant.')) {
                return null; // Deixar verificação de permission continuar
            }

            // Bypass para gates legacy
            if ($ability !== 'manage-billing' && tenancy()->initialized) {
                if ($user->isOwner()) {
                    return true;
                }
            }
        });

        // ==========================================
        // LEGACY GATES (manter para compatibilidade)
        // ==========================================

        // Gerenciar equipe -> delegado para permission
        Gate::define('manage-team', function (User $user) {
            return $user->can('tenant.team:manage-roles');
        });

        // Gerenciar billing -> delegado para permission
        Gate::define('manage-billing', function (User $user) {
            return $user->can('tenant.billing:manage');
        });

        // Gerenciar settings -> delegado para permission
        Gate::define('manage-settings', function (User $user) {
            return $user->can('tenant.settings:edit');
        });

        // Criar recursos -> delegado para permission
        Gate::define('create-resources', function (User $user) {
            return $user->can('tenant.projects:create');
        });

        // Ver recursos -> delegado para permission
        Gate::define('view-resources', function (User $user) {
            return tenancy()->initialized
                && $user->belongsToCurrentTenant()
                && $user->can('tenant.projects:view');
        });
    }
}
