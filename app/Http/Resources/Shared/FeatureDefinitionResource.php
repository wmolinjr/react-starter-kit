<?php

namespace App\Http\Resources\Shared;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * FeatureDefinitionResource
 *
 * Feature definition for plan/addon forms.
 * Wraps PlanFeature enum data for frontend consumption.
 */
class FeatureDefinitionResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'value' => 'string',
            'label' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'color' => 'string',
            'category' => 'string',
            'permissions' => 'string[]',
            'is_customizable' => 'boolean',
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // This resource wraps PlanFeature::toFrontend() output
        return [
            'value' => $this->resource['value'] ?? '',
            'label' => $this->resource['label'] ?? '',
            'description' => $this->resource['description'] ?? '',
            'icon' => $this->resource['icon'] ?? '',
            'color' => $this->resource['color'] ?? '',
            'category' => $this->resource['category'] ?? '',
            'permissions' => $this->resource['permissions'] ?? [],
            'is_customizable' => $this->resource['is_customizable'] ?? true,
        ];
    }
}
