<?php

namespace App\Providers;

use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;

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
    }
}
