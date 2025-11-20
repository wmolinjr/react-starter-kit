<?php

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class TenantPathGenerator implements PathGenerator
{
    /**
     * Get the path for the given media, relative to the root storage path.
     *
     * Stores files in: tenants/{tenant_id}/media/{media_id}/
     */
    public function getPath(Media $media): string
    {
        $tenantId = $media->tenant_id ?? 'global';

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
