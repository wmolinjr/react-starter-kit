<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * ImpersonationUserResource
 *
 * Tenant user information for impersonation selection.
 * Used when central admin selects a user to impersonate.
 */
class ImpersonationUserResource extends BaseResource
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
            'email' => 'string',
            'created_at' => 'string | null',
            'roles' => 'string[]',
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Handle both User model and array from ImpersonationService
        if (is_array($this->resource)) {
            return $this->resource;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->formatIso($this->created_at),
            'roles' => $this->roles->pluck('name')->toArray(),
        ];
    }
}
