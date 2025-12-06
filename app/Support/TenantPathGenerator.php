<?php

namespace App\Support;

use App\Models\Media as AppMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class TenantPathGenerator implements PathGenerator
{
    /**
     * Get the path for the given media, relative to the root storage path.
     *
     * MULTI-DATABASE TENANCY:
     * - Media stores tenant_id in custom_properties for consistent path generation
     * - Falls back to current tenant context if custom_property not set
     * - Falls back to 'global' for non-tenant media
     *
     * Stores files in: tenants/{tenant_id}/media/{media_id}/
     */
    public function getPath(Media $media): string
    {
        // Use getTenantIdForPath() from our custom Media model
        if ($media instanceof AppMedia) {
            $tenantId = $media->getTenantIdForPath();
        } else {
            // Fallback for base Media class
            $tenantId = tenancy()->initialized ? tenant('id') : 'global';
        }

        return "tenants/{$tenantId}/media/{$media->id}/";
    }

    /**
     * Get the path for conversions of the given media, relative to the root storage path.
     *
     * Stores conversions in: tenants/{tenant_id}/media/{media_id}/conversions/
     */
    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media) . 'conversions/';
    }

    /**
     * Get the path for responsive images of the given media, relative to the root storage path.
     *
     * Stores responsive images in: tenants/{tenant_id}/media/{media_id}/responsive/
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media) . 'responsive/';
    }
}
