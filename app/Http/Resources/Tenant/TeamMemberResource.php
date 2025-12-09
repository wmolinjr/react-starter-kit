<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * TeamMemberResource
 *
 * Team member with role and permission information.
 */
class TeamMemberResource extends BaseResource
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
            'role' => 'TenantRole | null',
            'permissions' => 'string[]',
            'created_at' => 'string',
            'email_verified_at' => 'string | null',
            'is_owner' => 'boolean',
            'is_admin' => 'boolean',
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
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->roles->first()?->name,
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'created_at' => $this->formatIso($this->created_at),
            'email_verified_at' => $this->formatIso($this->email_verified_at),

            // Computed flags
            'is_owner' => $this->isOwner(),
            'is_admin' => $this->isAdmin(),
        ];
    }
}
