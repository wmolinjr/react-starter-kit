<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use App\Models\Central\Addon;

/**
 * AddonResource
 *
 * Transforms an Addon model for the addon catalog.
 *
 * @property Addon $resource
 */
class AddonResource extends BaseResource
{
    use HasTypescriptType;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->getTranslation('name', app()->getLocale()),
            'description' => $this->getTranslation('description', app()->getLocale()),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'active' => $this->active,

            // Pricing
            'price_monthly' => $this->price_monthly,
            'price_yearly' => $this->price_yearly,
            'price_one_time' => $this->price_one_time,
            'price_metered' => $this->price_metered,
            'formatted_price_monthly' => $this->price_monthly ? $this->formatMoney($this->price_monthly) : null,
            'formatted_price_yearly' => $this->price_yearly ? $this->formatMoney($this->price_yearly) : null,
            'formatted_price_one_time' => $this->price_one_time ? $this->formatMoney($this->price_one_time) : null,
            'currency' => $this->currency ?? 'USD',

            // Quantity limits
            'min_quantity' => $this->min_quantity ?? 1,
            'max_quantity' => $this->max_quantity ?? 1,
            'stackable' => $this->stackable ?? false,

            // Quota info (for quota type addons)
            'unit_value' => $this->unit_value,
            'unit_label' => $this->unit_label ? $this->getTranslation('unit_label', app()->getLocale()) : null,
            'limit_key' => $this->limit_key,

            // Features
            'features' => $this->features,

            // Display
            'icon' => $this->icon,
            'icon_color' => $this->icon_color,
            'badge' => $this->badge,
            'sort_order' => $this->sort_order,

            // Validity (for one-time purchases)
            'validity_months' => $this->validity_months,
        ];
    }

    /**
     * Format money value in cents to currency string.
     */
    protected function formatMoney(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }

    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'slug' => 'string',
            'name' => 'string',
            'description' => 'string | null',
            'type' => 'AddonType',
            'type_label' => 'string',
            'active' => 'boolean',
            'price_monthly' => 'number | null',
            'price_yearly' => 'number | null',
            'price_one_time' => 'number | null',
            'price_metered' => 'number | null',
            'formatted_price_monthly' => 'string | null',
            'formatted_price_yearly' => 'string | null',
            'formatted_price_one_time' => 'string | null',
            'currency' => 'string',
            'min_quantity' => 'number',
            'max_quantity' => 'number',
            'stackable' => 'boolean',
            'unit_value' => 'number | null',
            'unit_label' => 'string | null',
            'limit_key' => 'string | null',
            'features' => 'Record<string, boolean> | null',
            'icon' => 'string | null',
            'icon_color' => 'string | null',
            'badge' => 'string | null',
            'sort_order' => 'number',
            'validity_months' => 'number | null',
        ];
    }
}
