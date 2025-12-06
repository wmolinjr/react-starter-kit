<?php

namespace App\Support;

use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

/**
 * Tenant Aware URL Generator for Spatie MediaLibrary
 *
 * MULTI-DATABASE TENANCY:
 * - Generates URLs using asset() helper for tenant-aware URLs
 * - Works correctly with multiple tenant domains
 * - Uses TenantPathGenerator for consistent path generation
 *
 * @see https://v4.tenancyforlaravel.com/integrations/spatie
 */
class TenantAwareUrlGenerator extends DefaultUrlGenerator
{
    /**
     * Get the URL for the media item.
     *
     * Uses asset() helper to generate tenant-aware URLs that work
     * correctly across different tenant domains.
     */
    public function getUrl(): string
    {
        $url = asset($this->getPathRelativeToRoot());

        $url = $this->versionUrl($url);

        return $url;
    }
}
