<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * BundleAddonResource
 *
 * Represents an addon within a bundle, including pivot data.
 * Used in bundle listing and detail views.
 */
class BundleAddonResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'addon_id' => 'string',
            'slug' => 'string',
            'name' => 'string',
            'type' => 'AddonType',
            'type_label' => 'string',
            'price_monthly' => 'number',
            'quantity' => 'number',
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
            'addon_id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->trans('name'),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'price_monthly' => $this->price_monthly,
            'quantity' => $this->pivot->quantity ?? 1,
        ];
    }
}
