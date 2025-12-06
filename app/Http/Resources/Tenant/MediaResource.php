<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * MediaResource
 *
 * Media file information.
 */
class MediaResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'human_readable_size' => $this->human_readable_size,
            'collection_name' => $this->collection_name,
            'disk' => $this->disk,
            'created_at' => $this->formatIso($this->created_at),

            // URLs
            'url' => $this->getUrl(),
            'thumb_url' => $this->when(
                $this->hasGeneratedConversion('thumb'),
                fn () => $this->getUrl('thumb')
            ),

            // Custom properties
            'custom_properties' => $this->custom_properties,
        ];
    }
}
