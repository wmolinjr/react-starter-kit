<?php

namespace App\Providers;

use App\Events\Central\Federation\FederatedUserCreated;
use App\Events\Central\Federation\FederatedUserPasswordChanged;
use App\Events\Central\Federation\FederatedUserTwoFactorChanged;
use App\Events\Central\Federation\FederatedUserUpdated;
use App\Events\Central\Federation\TenantJoinedFederation;
use App\Listeners\Central\Federation\PropagatePasswordChange;
use App\Listeners\Central\Federation\PropagateTwoFactorChange;
use App\Listeners\Central\Federation\SyncNewFederatedUser;
use App\Listeners\Central\Federation\SyncUpdatedFederatedUser;
use App\Listeners\Central\Federation\SyncUsersToNewTenant;
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
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Disable Fortify route registration - we use custom auth controllers
        // Fortify is kept only as a library for 2FA functionality
        Fortify::ignoreRoutes();
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

        // Permission check: All permissions are checked via Spatie roles/permissions.
        // NO bypass - all users (Central\User and Tenant\User) use explicit permissions.
        // Central\User uses guard 'central', Tenant\User uses guard 'tenant'.
        Gate::before(function ($user, $ability) {
            // Central\User: Let Spatie handle via HasRoles trait (guard: central)
            // No bypass - permissions are assigned via roles (super-admin, central-admin, support-admin)
            if ($user instanceof \App\Models\Central\User) {
                return null; // Let normal permission check proceed
            }

            // Tenant\User: Check plan-enabled permissions for tenant context
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

        // Federation Event Listeners
        Event::listen(FederatedUserCreated::class, SyncNewFederatedUser::class);
        Event::listen(FederatedUserUpdated::class, SyncUpdatedFederatedUser::class);
        Event::listen(FederatedUserPasswordChanged::class, PropagatePasswordChange::class);
        Event::listen(FederatedUserTwoFactorChanged::class, PropagateTwoFactorChange::class);
        Event::listen(TenantJoinedFederation::class, SyncUsersToNewTenant::class);
    }
}
