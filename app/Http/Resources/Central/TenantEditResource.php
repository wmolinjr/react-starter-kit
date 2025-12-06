<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * TenantEditResource
 *
 * Tenant data for edit forms.
 */
class TenantEditResource extends BaseResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'settings' => $this->settings,

            // Relationships
            'domains' => DomainResource::collection($this->whenLoaded('domains')),
            'plan' => $this->when(
                $this->relationLoaded('plan') && $this->plan,
                fn () => [
                    'id' => $this->plan->id,
                    'name' => $this->plan->trans('name'),
                ]
            ),
            'plan_id' => $this->plan_id,

            // Override settings for form
            'plan_features_override' => $this->plan_features_override ?? [],
            'plan_limits_override' => $this->plan_limits_override ?? [],
        ];
    }
}
