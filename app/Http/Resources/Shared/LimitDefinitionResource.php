<?php

namespace App\Http\Resources\Shared;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * LimitDefinitionResource
 *
 * Limit definition for plan/addon forms.
 * Wraps PlanLimit enum data for frontend consumption.
 */
class LimitDefinitionResource extends BaseResource
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
            'unit' => 'string',
            'unit_label' => 'string',
            'default_value' => 'number',
            'allows_unlimited' => 'boolean',
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
        // This resource wraps PlanLimit::toFrontend() output
        return [
            'value' => $this->resource['value'] ?? '',
            'label' => $this->resource['label'] ?? '',
            'description' => $this->resource['description'] ?? '',
            'icon' => $this->resource['icon'] ?? '',
            'color' => $this->resource['color'] ?? '',
            'unit' => $this->resource['unit'] ?? '',
            'unit_label' => $this->resource['unit_label'] ?? '',
            'default_value' => $this->resource['default_value'] ?? 0,
            'allows_unlimited' => $this->resource['allows_unlimited'] ?? false,
            'is_customizable' => $this->resource['is_customizable'] ?? true,
        ];
    }
}
