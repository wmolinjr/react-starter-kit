<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * BundleResource
 *
 * Represents an addon bundle with all its details.
 * Used in bundle listing and detail views.
 */
class BundleResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'slug' => 'string',
            'name' => 'Translations',
            'name_display' => 'string',
            'description' => 'Translations',
            'active' => 'boolean',
            'discount_percent' => 'number',
            'price_monthly' => 'number | null',
            'price_yearly' => 'number | null',
            'price_monthly_effective' => 'number',
            'price_yearly_effective' => 'number',
            'base_price_monthly' => 'number',
            'savings_monthly' => 'number',
            'badge' => 'BadgePreset | null',
            'icon' => 'string',
            'icon_color' => 'string | null',
            'features' => 'Translations[]',
            'sort_order' => 'number',
            'addon_count' => 'number',
            'addons' => 'BundleAddonResource[]',
            'plan_ids' => 'string[]',
            'plans' => 'BundlePlanSummary[]',
            'stripe_product_id' => 'string | null',
            'stripe_price_monthly_id' => 'string | null',
            'stripe_price_yearly_id' => 'string | null',
            'is_synced' => 'boolean',
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
            'slug' => $this->slug,
            'name' => $this->translations('name'),
            'name_display' => $this->trans('name'),
            'description' => $this->translations('description'),
            'active' => $this->active,
            'discount_percent' => $this->discount_percent,
            'price_monthly' => $this->price_monthly,
            'price_yearly' => $this->price_yearly,
            'price_monthly_effective' => $this->getEffectivePriceMonthly(),
            'price_yearly_effective' => $this->getEffectivePriceYearly(),
            'base_price_monthly' => $this->getBasePriceMonthly(),
            'savings_monthly' => $this->getSavingsMonthly(),
            'badge' => $this->badge,
            'icon' => $this->icon ?? 'Package',
            'icon_color' => $this->icon_color ?? 'slate',
            'features' => $this->features ?? [],
            'sort_order' => $this->sort_order,
            'addon_count' => $this->when(
                $this->relationLoaded('addons'),
                fn () => $this->addons->count(),
                0
            ),
            'addons' => $this->when(
                $this->relationLoaded('addons'),
                fn () => BundleAddonResource::collection($this->addons)
            ),
            'plan_ids' => $this->when(
                $this->relationLoaded('plans'),
                fn () => $this->plans->pluck('id')->toArray(),
                []
            ),
            'plans' => $this->when(
                $this->relationLoaded('plans'),
                fn () => $this->plans->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->trans('name'),
                    'slug' => $p->slug,
                ])
            ),
            'stripe_product_id' => $this->stripe_product_id,
            'stripe_price_monthly_id' => $this->stripe_price_monthly_id,
            'stripe_price_yearly_id' => $this->stripe_price_yearly_id,
            'is_synced' => (bool) $this->stripe_product_id,
        ];
    }
}
