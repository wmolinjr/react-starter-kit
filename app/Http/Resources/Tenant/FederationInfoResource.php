<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * FederationInfoResource
 *
 * Resource for tenant's federation info view.
 */
class FederationInfoResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'is_federated' => 'boolean',
            'is_master' => 'boolean',
            'group_name' => 'string | null',
            'group_id' => 'string | null',
            'sync_strategy' => 'FederationSyncStrategy | null',
            'federated_users_count' => 'number',
            'local_users_count' => 'number',
            'total_group_tenants' => 'number',
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
            'is_federated' => $this['is_federated'] ?? false,
            'is_master' => $this['is_master'] ?? false,
            'group_name' => $this['group_name'] ?? null,
            'group_id' => $this['group_id'] ?? null,
            'sync_strategy' => $this['sync_strategy'] ?? null,
            'federated_users_count' => $this['federated_users_count'] ?? 0,
            'local_users_count' => $this['local_users_count'] ?? 0,
            'total_group_tenants' => $this['total_group_tenants'] ?? 0,
        ];
    }
}
