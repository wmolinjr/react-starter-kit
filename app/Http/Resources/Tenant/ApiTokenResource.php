<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * ApiTokenResource
 *
 * API Token (Sanctum Personal Access Token) for tenant users.
 */
class ApiTokenResource extends BaseResource
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
            'abilities' => 'string[]',
            'last_used_at' => 'string | null',
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
            'id' => (string) $this->id,
            'name' => $this->name,
            'abilities' => $this->abilities ?? ['*'],
            'last_used_at' => $this->formatIso($this->last_used_at),
            'created_at' => $this->formatIso($this->created_at),
        ];
    }
}
