<?php

namespace App\Bootstrappers;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Spatie Permission Tenancy Bootstrapper
 *
 * Isola o cache do Spatie Permission por tenant, prevenindo conflitos de cache
 * entre diferentes tenants. Cada tenant terá sua própria cache key.
 *
 * Integração com Observers:
 * - Os observers do Spatie Permission (RoleObserver, PermissionObserver) funcionam
 *   automaticamente e invalidam o cache quando roles/permissions são modificadas
 * - Não é necessário invalidar cache manualmente quando usar assignRole(),
 *   givePermissionTo(), etc.
 *
 * @see https://tenancyforlaravel.com/integrations/spatie
 */
class SpatiePermissionsBootstrapper implements TenancyBootstrapper
{
    public function __construct(
        protected PermissionRegistrar $registrar,
    ) {}

    /**
     * Bootstrap tenancy: Isola cache do Spatie Permission para o tenant atual
     */
    public function bootstrap(Tenant $tenant): void
    {
        // Muda a cache key para incluir o tenant_id
        // Formato: spatie.permission.cache.tenant.{tenant_id}
        $this->registrar->cacheKey = 'spatie.permission.cache.tenant.' . $tenant->getTenantKey();
    }

    /**
     * Revert tenancy: Volta para a cache key padrão (contexto central)
     */
    public function revert(): void
    {
        // Volta para a cache key padrão do Spatie
        $this->registrar->cacheKey = 'spatie.permission.cache';
    }
}
