<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * FederationGroupResource
 *
 * Resource for federation group listing views.
 */
class FederationGroupResource extends BaseResource
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
            'sync_strategy' => 'FederationSyncStrategy',
            'is_active' => 'boolean',
            'created_at' => 'string',
            'updated_at' => 'string',
            'master_tenant' => 'TenantSummaryResource | undefined',
            'tenants_count' => 'number',
            'federated_users_count' => 'number',
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
            'sync_strategy' => $this->sync_strategy,
            'is_active' => $this->is_active,
            'created_at' => $this->formatIso($this->created_at),
            'updated_at' => $this->formatIso($this->updated_at),

            // Relationships
            'master_tenant' => $this->when(
                $this->relationLoaded('masterTenant'),
                fn () => new TenantSummaryResource($this->masterTenant)
            ),

            // Counts
            'tenants_count' => $this->countOrCompute('tenants'),
            'federated_users_count' => $this->countOrCompute('federatedUsers'),
        ];
    }
}
