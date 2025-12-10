<?php

namespace App\Bootstrappers;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Spatie Permission Tenancy Bootstrapper
 *
 * MULTI-DATABASE TENANCY:
 * - Each tenant has its own permissions/roles tables in their database
 * - No team_id needed - isolation is at the database level
 * - Cache key is per-tenant to avoid conflicts
 * - Cache is cleared when switching tenants (database changes)
 *
 * @see https://tenancyforlaravel.com/integrations/spatie
 */
class SpatiePermissionsBootstrapper implements TenancyBootstrapper
{
    public function __construct(
        protected PermissionRegistrar $registrar,
    ) {}

    /**
     * Bootstrap tenancy: Configure permission cache for tenant's database.
     */
    public function bootstrap(Tenant $tenant): void
    {
        $tenantKey = $tenant->getTenantKey();

        // Set cache key to include tenant ID
        $this->registrar->cacheKey = 'spatie.permission.cache.tenant.'.$tenantKey;

        // Clear cached permissions - they're from the previous database
        $this->registrar->forgetCachedPermissions();
    }

    /**
     * Revert tenancy: Reset to default cache key.
     */
    public function revert(): void
    {
        // Revert to default cache key
        $this->registrar->cacheKey = 'spatie.permission.cache';

        // Clear cached permissions from tenant database
        $this->registrar->forgetCachedPermissions();
    }
}
