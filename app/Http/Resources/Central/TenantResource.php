<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * TenantResource
 *
 * Tenant resource for listing views.
 * Includes domains, plan summary, and user count.
 */
class TenantResource extends BaseResource
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
            'created_at' => $this->formatIso($this->created_at),
            'updated_at' => $this->formatIso($this->updated_at),

            // Relationships
            'domains' => DomainResource::collection($this->whenLoaded('domains')),
            'plan' => $this->when(
                $this->relationLoaded('plan') && $this->plan,
                fn () => new PlanSummaryResource($this->plan)
            ),

            // Computed: primary domain shortcut
            'primary_domain' => $this->when(
                $this->relationLoaded('domains'),
                fn () => $this->domains->firstWhere('is_primary', true)?->domain
            ),

            // User count from tenant database
            'users_count' => $this->getUserCount(),

            // Settings summary
            'settings' => $this->when(
                $request->has('include_settings'),
                fn () => $this->settings
            ),
        ];
    }
}
