<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * PlanResource
 *
 * Plan resource for listing with tenant counts.
 */
class PlanResource extends BaseResource
{
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
            'stripe_price_id' => $this->stripe_price_id,
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
