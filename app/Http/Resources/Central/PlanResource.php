<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * PlanResource
 *
 * Plan resource for listing with tenant counts.
 */
class PlanResource extends BaseResource
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
            'description' => 'string | null',
            'price' => 'number',
            'formatted_price' => 'string',
            'currency' => 'string',
            'billing_period' => 'BillingPeriod',
            'provider_price_ids' => 'Record<string, string> | null',
            'features' => 'PlanFeatures',
            'limits' => 'PlanLimits',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'badge' => 'BadgePreset | null',
            'icon' => 'string | null',
            'icon_color' => 'string | null',
            'sort_order' => 'number',
            'tenants_count' => 'number',
            'addons_count' => 'number',
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
            'description' => $this->trans('description'),
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'currency' => $this->currency,
            'billing_period' => $this->billing_period,
            'provider_price_ids' => $this->provider_price_ids,
            'features' => $this->features,
            'limits' => $this->limits,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'badge' => $this->badge,
            'icon' => $this->icon,
            'icon_color' => $this->icon_color,
            'sort_order' => $this->sort_order,
            'tenants_count' => $this->countOrCompute('tenants'),
            'addons_count' => $this->when(
                $this->relationLoaded('addons'),
                fn () => $this->addons->count(),
                fn () => $this->addons()->count()
            ),
        ];
    }
}
