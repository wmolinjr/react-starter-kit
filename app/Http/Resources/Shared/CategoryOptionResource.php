<?php

namespace App\Http\Resources\Shared;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * CategoryOptionResource
 *
 * Simple category option for dropdowns/filters (value + label).
 * Used for feature categories, addon categories, etc.
 */
class CategoryOptionResource extends BaseResource
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
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // This resource wraps a plain array, not a model
        return [
            'value' => $this->resource['value'] ?? '',
            'label' => $this->resource['label'] ?? '',
        ];
    }
}
