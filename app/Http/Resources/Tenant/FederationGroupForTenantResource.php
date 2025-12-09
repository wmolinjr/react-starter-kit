<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * FederationGroupForTenantResource
 *
 * Federation group information from tenant perspective.
 * Used in tenant federation settings page.
 */
class FederationGroupForTenantResource extends BaseResource
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
            'is_master' => 'boolean',
            'settings' => 'Record<string, unknown>',
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @param  bool  $isMaster  Whether current tenant is the master
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'sync_strategy' => $this->sync_strategy,
            'is_master' => $this->additional['is_master'] ?? false,
            'settings' => $this->settings ?? [],
        ];
    }
}
