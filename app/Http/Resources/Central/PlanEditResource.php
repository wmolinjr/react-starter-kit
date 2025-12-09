<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * PlanEditResource
 *
 * Plan data for edit forms with all translations.
 */
class PlanEditResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'name' => 'Translations',
            'name_display' => 'string',
            'slug' => 'string',
            'description' => 'Translations',
            'price' => 'number',
            'currency' => 'string',
            'billing_period' => 'BillingPeriod',
            'stripe_price_id' => 'string | null',
            'stripe_product_id' => 'string | null',
            'features' => 'PlanFeatures',
            'limits' => 'PlanLimits',
            'permission_map' => 'Record<string, string[]>',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'badge' => 'BadgePreset | null',
            'icon' => 'string',
            'icon_color' => 'string',
            'sort_order' => 'number',
            'addon_ids' => 'string[]',
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
            'name' => $this->translations('name'),
            'name_display' => $this->trans('name'),
            'slug' => $this->slug,
            'description' => $this->translations('description'),
            'price' => $this->price,
            'currency' => $this->currency,
            'billing_period' => $this->billing_period,
            'stripe_price_id' => $this->stripe_price_id,
            'stripe_product_id' => $this->stripe_product_id,
            'features' => $this->features ?? [],
            'limits' => $this->limits ?? [],
            'permission_map' => $this->permission_map ?? [],
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'badge' => $this->badge,
            'icon' => $this->icon ?? 'Layers',
            'icon_color' => $this->icon_color ?? 'slate',
            'sort_order' => $this->sort_order,
            'addon_ids' => $this->when(
                $this->relationLoaded('addons'),
                fn () => $this->addons->pluck('id')->toArray(),
                []
            ),
        ];
    }
}
