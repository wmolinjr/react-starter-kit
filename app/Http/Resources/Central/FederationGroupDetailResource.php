<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * FederationGroupDetailResource
 *
 * Detailed resource for federation group show/edit views.
 */
class FederationGroupDetailResource extends BaseResource
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
            'description' => $this->description,
            'sync_strategy' => $this->sync_strategy,
            'master_tenant_id' => $this->master_tenant_id,
            'settings' => $this->settings ?? [],
            'is_active' => $this->is_active,
            'created_at' => $this->formatIso($this->created_at),
            'updated_at' => $this->formatIso($this->updated_at),

            // Relationships
            'master_tenant' => $this->when(
                $this->relationLoaded('masterTenant'),
                fn () => new TenantSummaryResource($this->masterTenant)
            ),

            'tenants' => $this->when(
                $this->relationLoaded('tenants'),
                fn () => $this->tenants->map(fn ($tenant) => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'is_master' => $tenant->id === $this->master_tenant_id,
                    'sync_enabled' => $tenant->pivot->sync_enabled ?? true,
                    'joined_at' => $this->formatIso($tenant->pivot->joined_at),
                    'settings' => $tenant->pivot->settings ?? [],
                ])
            ),

            'federated_users' => $this->when(
                $this->relationLoaded('federatedUsers'),
                fn () => FederatedUserResource::collection($this->federatedUsers)
            ),

            // Counts
            'tenants_count' => $this->countOrCompute('tenants'),
            'federated_users_count' => $this->countOrCompute('federatedUsers'),

            // Stats
            'stats' => $this->when(
                $request->has('include_stats'),
                fn () => $this->getStats()
            ),
        ];
    }

    /**
     * Get statistics for this group.
     */
    protected function getStats(): array
    {
        return app(\App\Services\Central\FederationService::class)->getGroupStats($this->resource);
    }
}
