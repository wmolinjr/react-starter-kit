<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * DomainResource
 *
 * Resource for tenant domain information.
 */
class DomainResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'domain' => 'string',
            'is_primary' => 'boolean',
            'created_at' => 'string',
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
            'domain' => $this->domain,
            'is_primary' => $this->is_primary,
            'created_at' => $this->formatIso($this->created_at),
        ];
    }
}
