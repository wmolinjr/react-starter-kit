<?php

namespace App\Providers;

use App\Listeners\UpdateTenantLimits;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
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
        // Super Admin: bypass all permission checks
        // This works with auth()->user()->can() and @can() Blade directive
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

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
