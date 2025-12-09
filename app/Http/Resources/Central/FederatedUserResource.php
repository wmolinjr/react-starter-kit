<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * FederatedUserResource
 *
 * Resource for federated user listing views.
 */
class FederatedUserResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'global_email' => 'string',
            'name' => 'string | null',
            'status' => 'FederatedUserStatus',
            'sync_version' => 'number',
            'last_synced_at' => 'string | null',
            'created_at' => 'string',
            'master_tenant' => 'TenantSummaryResource | undefined',
            'links_count' => 'number',
            'two_factor_enabled' => 'boolean',
        ];
    }

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
            'global_email' => $this->global_email,
            'name' => $syncedData['name'] ?? null,
            'status' => $this->status,
            'sync_version' => $this->sync_version,
            'last_synced_at' => $this->formatIso($this->last_synced_at),
            'created_at' => $this->formatIso($this->created_at),

            // Master tenant
            'master_tenant' => $this->when(
                $this->relationLoaded('masterTenant'),
                fn () => new TenantSummaryResource($this->masterTenant)
            ),

            // Links count
            'links_count' => $this->countOrCompute('links'),

            // 2FA status (from synced data)
            'two_factor_enabled' => $syncedData['two_factor_enabled'] ?? false,
        ];
    }
}
