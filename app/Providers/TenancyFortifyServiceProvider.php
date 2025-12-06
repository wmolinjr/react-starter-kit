<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Bootstrappers\Integrations\FortifyRouteBootstrapper;

/**
 * Configure FortifyRouteBootstrapper for tenant-aware Fortify redirects.
 *
 * STANCL/TENANCY V4:
 * - When tenancy is initialized, fortify.home is set to tenant.admin.dashboard
 * - When tenancy ends, fortify.home reverts to original config
 * - Uses domain identification, so no tenant parameter needed
 */
class TenancyFortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Tenant route for Fortify home (after login, register, etc.)
        FortifyRouteBootstrapper::$fortifyHome = 'tenant.admin.dashboard';

        // Domain identification - no need to pass tenant parameter
        FortifyRouteBootstrapper::$passTenantParameter = false;

        // Optional: Map specific Fortify redirects to tenant routes
        FortifyRouteBootstrapper::$fortifyRedirectMap = [
            'email-verification' => 'tenant.admin.dashboard',
        ];
    }
}
