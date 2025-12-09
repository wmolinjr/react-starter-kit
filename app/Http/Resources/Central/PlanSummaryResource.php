<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * PlanSummaryResource
 *
 * Minimal plan information for dropdowns and selection lists.
 */
class PlanSummaryResource extends BaseResource
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
            'slug' => 'string',
            'price' => 'number',
            'formatted_price' => 'string',
            'currency' => 'string',
            'billing_period' => 'BillingPeriod',
            'is_featured' => 'boolean',
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
            'name' => $this->trans('name'),
            'slug' => $this->slug,
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'currency' => $this->currency,
            'billing_period' => $this->billing_period,
            'is_featured' => $this->is_featured,
        ];
    }
}
