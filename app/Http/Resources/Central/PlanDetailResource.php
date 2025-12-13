<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * PlanDetailResource
 *
 * Complete plan information for show/detail views.
 */
class PlanDetailResource extends BaseResource
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
            'provider_product_ids' => 'Record<string, string> | null',
            'provider_price_ids' => 'Record<string, string> | null',
            'features' => 'PlanFeatures',
            'limits' => 'PlanLimits',
            'permission_map' => 'Record<string, string[]>',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'badge' => 'BadgePreset | null',
            'icon' => 'string',
            'icon_color' => 'string',
            'sort_order' => 'number',
            'created_at' => 'string',
            'updated_at' => 'string',
            'tenants_count' => 'number',
            'addons' => 'AddonSummary[] | undefined',
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
            'provider_product_ids' => $this->provider_product_ids,
            'provider_price_ids' => $this->provider_price_ids,
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
                    'name' => $addon->name,
                    'slug' => $addon->slug,
                ])
            ),
        ];
    }
}
