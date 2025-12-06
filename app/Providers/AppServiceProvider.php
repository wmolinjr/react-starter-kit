<?php

namespace App\Providers;

use App\Listeners\Central\SyncPermissionsOnSubscriptionChange;
use App\Listeners\Central\UpdateTenantLimits;
use App\Models\Central\AddonPurchase;
use App\Models\Central\AddonSubscription;
use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use App\Models\Central\User as CentralUser;
use App\Models\Tenant\Project;
use App\Models\Tenant\User;
use App\Observers\Central\AddonSubscriptionObserver;
use App\Observers\Central\DomainObserver;
use App\Observers\Central\TenantObserver;
use App\Observers\Tenant\ProjectObserver;
use App\Observers\Tenant\UserObserver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookReceived;

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
        // ⭐ Disable 'data' wrapping for API Resources (Inertia compatibility)
        JsonResource::withoutWrapping();

        // ⭐ Enforce MorphMap for UUID models
        // This ensures polymorphic relations use consistent type names
        // and works correctly with UUID primary keys
        Relation::enforceMorphMap([
            'user' => User::class,
            'admin' => CentralUser::class,
            'tenant' => Tenant::class,
            'project' => Project::class,
            'addon_subscription' => AddonSubscription::class,
            'addon_purchase' => AddonPurchase::class,
        ]);

        // ⭐ Register observers (organized by database context)
        // Central observers (operate on central database)
        Tenant::observe(TenantObserver::class);
        AddonSubscription::observe(AddonSubscriptionObserver::class);
        Domain::observe(DomainObserver::class);

        // Tenant observers (operate on tenant databases)
        User::observe(UserObserver::class);
        Project::observe(ProjectObserver::class);

        // Super Admin: bypass all permission checks
        // Plan permission check: grant if enabled by plan (for admin/owner), deny if not
        Gate::before(function ($user, $ability) {
            // 1. Central Admin bypass (Central\User model from central database)
            // Option C: Central\User uses isSuperAdmin() instead of Spatie roles
            if ($user instanceof \App\Models\Central\User) {
                return $user->isSuperAdmin() ? true : null;
            }

            // 2. Super Admin bypass for User model (tenant database)
            // Multi-database tenancy: SpatiePermissionsBootstrapper handles DB switching
            if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
                return true;
            }

            // 2. ⭐ Check plan-enabled permissions for tenant context
            $tenant = tenant();
            if ($tenant && str_starts_with($ability, 'tenant.')) {
                $isPlanEnabled = $tenant->isPlanPermissionEnabled($ability);

                // If permission not enabled by plan, deny
                if (!$isPlanEnabled) {
                    return false;
                }

                // If permission IS enabled by plan AND user is admin/owner, grant access
                // This allows plan-based features (like auditLog) to work without
                // needing to add permissions to every role template
                if ($isPlanEnabled && method_exists($user, 'isAdminOrOwner') && $user->isAdminOrOwner()) {
                    return true;
                }
            }

            // 3. Continue to normal permission check
            return null;
        });

        // NOTE: Pennant features are now registered dynamically in PlanFeatureServiceProvider
        // using Feature::define() instead of Feature::discover() with class-based features.
        // See: app/Providers/PlanFeatureServiceProvider.php

        // Macro para verificar se user é owner do model
        Builder::macro('ownedBy', function (User $user) {
            return $this->where('user_id', $user->id);
        });

        // Event Listeners
        Event::listen(
            WebhookReceived::class,
            UpdateTenantLimits::class,
        );

        Event::listen(
            WebhookReceived::class,
            SyncPermissionsOnSubscriptionChange::class,
        );
    }
}
