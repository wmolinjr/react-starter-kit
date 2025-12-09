<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * TenantFederationMembershipResource
 *
 * Tenant's membership in a federation group.
 * Contains pivot data from federation_group_tenant.
 */
class TenantFederationMembershipResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'sync_enabled' => 'boolean',
            'joined_at' => 'string | null',
            'settings' => 'Record<string, unknown>',
            'default_role' => 'string | null',
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
            'sync_enabled' => $this->sync_enabled,
            'joined_at' => $this->formatIso($this->joined_at),
            'settings' => $this->settings ?? [],
            'default_role' => $this->getDefaultRole(),
        ];
    }

    /**
     * Get the default role from settings.
     */
    protected function getDefaultRole(): ?string
    {
        if (method_exists($this->resource, 'getDefaultRole')) {
            return $this->resource->getDefaultRole();
        }

        return $this->settings['default_role'] ?? null;
    }
}
