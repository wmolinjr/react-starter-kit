<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * ProjectDetailResource
 *
 * Complete project information with media for show views.
 */
class ProjectDetailResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'description' => 'string | null',
            'status' => 'string',
            'created_at' => 'string',
            'updated_at' => 'string',
            'user' => 'UserSummaryResource | null',
            'attachments' => 'ProjectAttachment[]',
            'images' => 'ProjectImage[]',
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => $this->formatDateOnly($this->created_at),
            'updated_at' => $this->formatIso($this->updated_at),

            // User relationship
            'user' => new UserSummaryResource($this->whenLoaded('user')),

            // Media collections
            'attachments' => $this->getMedia('attachments')->map(fn ($media) => [
                'id' => $media->id,
                'name' => $media->file_name,
                'size' => $media->human_readable_size,
                'mime_type' => $media->mime_type,
                'url' => route('tenant.admin.projects.media.download', [$this->resource, $media]),
            ]),
            'images' => $this->getMedia('images')->map(fn ($media) => [
                'id' => $media->id,
                'name' => $media->file_name,
                'size' => $media->human_readable_size,
                'url' => route('tenant.admin.projects.media.download', [$this->resource, $media]),
                'thumb_url' => $media->getUrl('thumb'),
            ]),
        ];
    }
}
