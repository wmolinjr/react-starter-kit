<?php

namespace App\Providers;

use App\Listeners\UpdateTenantLimits;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Observers\ProjectObserver;
use App\Observers\TenantObserver;
use App\Observers\UserObserver;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ⭐ Register observers
        Tenant::observe(TenantObserver::class);
        User::observe(UserObserver::class);
        Project::observe(ProjectObserver::class);

        // Super Admin: bypass all permission checks
        // Plan permission check: deny if permission not enabled by plan
        Gate::before(function ($user, $ability) {
            // 1. Super Admin bypass
            $currentTeamId = getPermissionsTeamId();
            setPermissionsTeamId(null);
            $isSuperAdmin = $user->hasRole('Super Admin');
            setPermissionsTeamId($currentTeamId);

            if ($isSuperAdmin) {
                return true;
            }

            // 2. ⭐ Check plan-enabled permissions
            $tenant = tenant();
            if ($tenant && str_starts_with($ability, 'tenant.')) {
                // If permission not enabled by plan, deny
                if (!$tenant->isPlanPermissionEnabled($ability)) {
                    return false;
                }
            }

            // 3. Continue to normal permission check
            return null;
        });

        // ⭐ Register Pennant features
        Feature::discover();

        // Macro para queries tenant-scoped
        Builder::macro('forTenant', function ($tenantId = null) {
            $tenantId = $tenantId ?? current_tenant_id();

            return $this->where('tenant_id', $tenantId);
        });

        // Macro para queries SEM tenant scope (para admins)
        Builder::macro('withoutTenantScope', function () {
            return $this->withoutGlobalScope(TenantScope::class);
        });

        // Macro para verificar se user é owner do model
        Builder::macro('ownedBy', function (User $user) {
            return $this->where('user_id', $user->id);
        });

        // Event Listeners
        Event::listen(
            WebhookReceived::class,
            UpdateTenantLimits::class,
        );
    }
}
