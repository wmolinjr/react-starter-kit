<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * FederatedUserDetailResource
 *
 * Detailed resource for federated user show views.
 */
class FederatedUserDetailResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $syncedData = $this->synced_data ?? [];

        return [
            'id' => $this->id,
            'federation_group_id' => $this->federation_group_id,
            'global_email' => $this->global_email,
            'status' => $this->status,
            'sync_version' => $this->sync_version,
            'last_synced_at' => $this->formatIso($this->last_synced_at),
            'last_sync_source' => $this->last_sync_source,
            'created_at' => $this->formatIso($this->created_at),
            'updated_at' => $this->formatIso($this->updated_at),

            // Synced profile data (safe to expose)
            'synced_data' => [
                'name' => $syncedData['name'] ?? null,
                'locale' => $syncedData['locale'] ?? null,
                'two_factor_enabled' => $syncedData['two_factor_enabled'] ?? false,
                'password_changed_at' => $syncedData['password_changed_at'] ?? null,
            ],

            // Master tenant
            'master_tenant' => $this->when(
                $this->relationLoaded('masterTenant'),
                fn () => new TenantSummaryResource($this->masterTenant)
            ),

            // Federation group
            'federation_group' => $this->when(
                $this->relationLoaded('federationGroup'),
                fn () => new FederationGroupResource($this->federationGroup)
            ),

            // Tenant links
            'links' => $this->when(
                $this->relationLoaded('links'),
                fn () => $this->links->map(fn ($link) => [
                    'id' => $link->id,
                    'tenant_id' => $link->tenant_id,
                    'tenant_name' => $link->tenant?->name,
                    'tenant_user_id' => $link->tenant_user_id,
                    'sync_status' => $link->sync_status,
                    'sync_attempts' => $link->sync_attempts,
                    'last_synced_at' => $this->formatIso($link->last_synced_at),
                    'last_sync_error' => $link->last_sync_error,
                    'is_master' => $link->getMetadata('is_master', false),
                    'created_via' => $link->getCreatedVia(),
                ])
            ),

            // Counts
            'links_count' => $this->countOrCompute('links'),
        ];
    }
}
