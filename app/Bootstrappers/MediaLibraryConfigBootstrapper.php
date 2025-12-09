<?php

declare(strict_types=1);

namespace App\Bootstrappers;

use App\Enums\PlanLimit;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * MediaLibraryConfigBootstrapper
 *
 * Overrides media-library max_file_size config based on tenant's plan limit.
 * The limit is read from PlanLimit::FILE_UPLOAD_SIZE which is stored in MB.
 *
 * Priority order:
 * 1. tenant.plan_limits_override['fileUploadSize'] (admin override)
 * 2. tenant.plan->limits['fileUploadSize'] (plan default)
 * 3. PlanLimit::FILE_UPLOAD_SIZE->defaultValue() (fallback: 10 MB)
 *
 * @see https://v4.tenancyforlaravel.com/bootstrappers
 */
class MediaLibraryConfigBootstrapper implements TenancyBootstrapper
{
    /**
     * Original max_file_size value (in bytes) to restore on revert.
     */
    protected int $originalMaxFileSize;

    public function __construct()
    {
        // Store original value from config (default: 10MB = 10485760 bytes)
        $this->originalMaxFileSize = (int) config('media-library.max_file_size', 1024 * 1024 * 10);
    }

    /**
     * Bootstrap tenancy by overriding max_file_size based on plan limit.
     */
    public function bootstrap(Tenant $tenant): void
    {
        // Get the file upload size limit in MB
        // Uses tenant's getLimit() which handles override > plan > default priority
        $limitInMb = $this->getFileUploadLimit($tenant);

        // Convert MB to bytes
        $limitInBytes = $limitInMb * 1024 * 1024;

        // Override media-library config
        config(['media-library.max_file_size' => $limitInBytes]);
    }

    /**
     * Revert to original max_file_size when tenancy ends.
     */
    public function revert(): void
    {
        config(['media-library.max_file_size' => $this->originalMaxFileSize]);
    }

    /**
     * Get the file upload size limit for the tenant.
     *
     * @param Tenant $tenant
     * @return int Limit in MB
     */
    protected function getFileUploadLimit(Tenant $tenant): int
    {
        $defaultLimit = PlanLimit::FILE_UPLOAD_SIZE->defaultValue();

        // Type-check: ensure tenant has getLimit method (our Tenant model)
        if (! method_exists($tenant, 'getLimit')) {
            return $defaultLimit;
        }

        /** @var \App\Models\Central\Tenant $tenant */
        $limit = $tenant->getLimit(PlanLimit::FILE_UPLOAD_SIZE->value);

        // Use default if limit is 0 or negative (invalid for file upload)
        // 0 would mean "no uploads allowed" which is typically not intended
        return $limit > 0 ? $limit : $defaultLimit;
    }
}
