<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * ProjectResource
 *
 * Project resource for listing views.
 */
class ProjectResource extends BaseResource
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
            'user_id' => 'string | null',
            'attachments_count' => 'number | undefined',
            'images_count' => 'number | undefined',
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
            'created_at' => $this->formatIso($this->created_at),
            'updated_at' => $this->formatIso($this->updated_at),

            // User relationship
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'user_id' => $this->user_id,

            // Media counts
            'attachments_count' => $this->when(
                $this->relationLoaded('media'),
                fn () => $this->getMedia('attachments')->count()
            ),
            'images_count' => $this->when(
                $this->relationLoaded('media'),
                fn () => $this->getMedia('images')->count()
            ),
        ];
    }
}
