<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * PlanDetailResource
 *
 * Complete plan information for show/detail views.
 */
class PlanDetailResource extends BaseResource
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
            'stripe_product_id' => $this->stripe_product_id,
            'stripe_price_id' => $this->stripe_price_id,
            'features' => $this->features ?? [],
            'limits' => $this->limits ?? [],
            'permission_map' => $this->permission_map ?? [],
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'badge' => $this->badge,
            'icon' => $this->icon ?? 'Layers',
            'icon_color' => $this->icon_color ?? 'slate',
            'sort_order' => $this->sort_order,
            'created_at' => $this->formatIso($this->created_at),
            'updated_at' => $this->formatIso($this->updated_at),

            // Relationships
            'tenants_count' => $this->countOrCompute('tenants'),
            'addons' => $this->when(
                $this->relationLoaded('addons'),
                fn () => $this->addons->map(fn ($addon) => [
                    'id' => $addon->id,
                    'name' => $addon->trans('name'),
                    'slug' => $addon->slug,
                ])
            ),
        ];
    }
}
