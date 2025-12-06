<?php

namespace App\Observers;

use App\Models\Central\Domain;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

/**
 * DomainObserver
 *
 * Invalidates the tenant resolver cache when domain records change.
 * This is crucial when using cached tenant resolution (v4 feature).
 *
 * @see config/tenancy.php (identification.resolvers.DomainTenantResolver)
 */
class DomainObserver
{
    public function __construct(
        protected DomainTenantResolver $resolver
    ) {}

    /**
     * Handle the Domain "created" event.
     */
    public function created(Domain $domain): void
    {
        $this->invalidateTenantCache($domain, 'created');
    }

    /**
     * Handle the Domain "updated" event.
     */
    public function updated(Domain $domain): void
    {
        // Only invalidate if domain name actually changed
        if ($domain->wasChanged('domain')) {
            $this->invalidateTenantCache($domain, 'updated');
        }
    }

    /**
     * Handle the Domain "deleted" event.
     */
    public function deleted(Domain $domain): void
    {
        $this->invalidateTenantCache($domain, 'deleted');
    }

    /**
     * Invalidate the tenant resolver cache for this domain's tenant.
     */
    protected function invalidateTenantCache(Domain $domain, string $event): void
    {
        // Only invalidate if caching is enabled
        if (!DomainTenantResolver::shouldCache()) {
            return;
        }

        $tenant = $domain->tenant;

        if (!$tenant) {
            Log::warning("DomainObserver: Domain {$domain->domain} has no tenant, skipping cache invalidation");
            return;
        }

        try {
            $this->resolver->invalidateCache($tenant);

            Log::info("DomainObserver: Invalidated tenant resolver cache for tenant {$tenant->id} after domain {$event}", [
                'domain' => $domain->domain,
                'tenant_id' => $tenant->id,
            ]);
        } catch (\Exception $e) {
            Log::error("DomainObserver: Failed to invalidate cache for tenant {$tenant->id}: {$e->getMessage()}");
        }
    }
}
