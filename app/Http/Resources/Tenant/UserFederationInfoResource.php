<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * UserFederationInfoResource
 *
 * Federation information for a specific tenant user.
 * Used in user federation info page.
 */
class UserFederationInfoResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'is_federated' => 'boolean',
            'federation_id' => 'string | null',
            'is_master_user' => 'boolean',
            'federated_user' => 'UserFederationInfoFederatedUser | null',
            'link' => 'UserFederationInfoLink | null',
            'group' => 'UserFederationInfoGroup | null',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public static function typescriptAdditionalTypes(): array
    {
        return [
            'UserFederationInfoFederatedUser' => [
                'id' => 'string',
                'email' => 'string',
                'synced_data' => 'Record<string, unknown>',
                'last_synced_at' => 'string | null',
                'created_at' => 'string',
            ],
            'UserFederationInfoLink' => [
                'id' => 'string',
                'status' => 'string',
                'sync_enabled' => 'boolean',
                'last_synced_at' => 'string | null',
                'linked_at' => 'string',
            ],
            'UserFederationInfoGroup' => [
                'id' => 'string',
                'name' => 'string',
                'sync_strategy' => 'FederationSyncStrategy',
            ],
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Resource wraps array data from FederationService::getUserFederationInfo()
        $data = $this->resource;

        if (! is_array($data)) {
            return [
                'is_federated' => false,
                'federation_id' => null,
                'is_master_user' => false,
                'federated_user' => null,
                'link' => null,
                'group' => null,
            ];
        }

        return [
            'is_federated' => $data['is_federated'] ?? false,
            'federation_id' => $data['federation_id'] ?? null,
            'is_master_user' => $data['is_master_user'] ?? false,
            'federated_user' => $data['federated_user'] ?? null,
            'link' => $data['link'] ?? null,
            'group' => $data['group'] ?? null,
        ];
    }
}
