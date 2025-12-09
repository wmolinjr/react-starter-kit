<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * BillingPlanResource
 *
 * Plan information for billing/subscription pages.
 */
class BillingPlanResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'slug' => 'string',
            'name' => 'string',
            'price' => 'string',
            'price_id' => 'string',
            'interval' => 'string',
            'features' => 'string[]',
            'limits' => '{ max_users: number | null; max_projects: number | null; storage_mb: number }',
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
            'slug' => $this->resource['slug'],
            'name' => $this->resource['name'],
            'price' => $this->resource['price'],
            'price_id' => $this->resource['price_id'],
            'interval' => $this->resource['interval'],
            'features' => $this->resource['features'],
            'limits' => $this->resource['limits'],
        ];
    }
}
