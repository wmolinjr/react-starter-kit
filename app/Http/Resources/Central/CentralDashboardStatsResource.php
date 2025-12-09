<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * CentralDashboardStatsResource
 *
 * Statistics for the central admin dashboard.
 */
class CentralDashboardStatsResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'total_tenants' => 'number',
            'total_admins' => 'number',
            'total_addons' => 'number',
            'total_plans' => 'number',
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // This resource wraps a plain array, not a model
        return [
            'total_tenants' => $this->resource['total_tenants'] ?? 0,
            'total_admins' => $this->resource['total_admins'] ?? 0,
            'total_addons' => $this->resource['total_addons'] ?? 0,
            'total_plans' => $this->resource['total_plans'] ?? 0,
        ];
    }
}
