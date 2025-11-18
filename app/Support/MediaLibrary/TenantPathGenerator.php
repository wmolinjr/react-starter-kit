<?php

namespace App\Support\MediaLibrary;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class TenantPathGenerator implements PathGenerator
{
    /**
     * Get the path for the given media, relative to the root storage path.
     *
     * Structure: tenants/{tenant_id}/{model_type}/{model_id}/{media_id}/
     *
     * @param  \Spatie\MediaLibrary\MediaCollections\Models\Media  $media
     * @return string
     */
    public function getPath(Media $media): string
    {
        $tenantId = $media->tenant_id ?? 'shared';
        $modelType = $this->getModelTypeName($media);
        $modelId = $media->model_id;
        $mediaId = $media->id;

        return "tenants/{$tenantId}/{$modelType}/{$modelId}/{$mediaId}/";
    }

    /**
     * Get the path for conversions of the given media, relative to the root storage path.
     *
     * @param  \Spatie\MediaLibrary\MediaCollections\Models\Media  $media
     * @return string
     */
    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'conversions/';
    }

    /**
     * Get the path for responsive images of the given media, relative to the root storage path.
     *
     * @param  \Spatie\MediaLibrary\MediaCollections\Models\Media  $media
     * @return string
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'responsive/';
    }

    /**
     * Get a simplified model type name.
     *
     * Converts App\Models\PageBlock to page-blocks
     *
     * @param  \Spatie\MediaLibrary\MediaCollections\Models\Media  $media
     * @return string
     */
    protected function getModelTypeName(Media $media): string
    {
        $modelType = $media->model_type;

        // Extract class name from fully qualified class name
        $className = class_basename($modelType);

        // Convert PascalCase to kebab-case and pluralize
        $kebabCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className));

        // Simple pluralization (add 's' if not already plural)
        if (! str_ends_with($kebabCase, 's')) {
            $kebabCase .= 's';
        }

        return $kebabCase;
    }
}
