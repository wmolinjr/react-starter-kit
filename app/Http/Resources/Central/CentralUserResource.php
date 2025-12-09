<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * CentralUserResource
 *
 * Central admin user information for listing views.
 */
class CentralUserResource extends BaseResource
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
            'locale' => 'string | null',
            'email_verified_at' => 'string | null',
            'two_factor_confirmed_at' => 'string | null',
            'created_at' => 'string',
            'updated_at' => 'string',
            'role' => 'string | null',
            'role_display_name' => 'string | null',
            'roles' => 'string[] | undefined',
            'permissions' => 'string[] | undefined',
            'is_super_admin' => 'boolean',
            'has_2fa' => 'boolean',
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
            'locale' => $this->locale,
            'email_verified_at' => $this->formatIso($this->email_verified_at),
            'two_factor_confirmed_at' => $this->formatIso($this->two_factor_confirmed_at),
            'created_at' => $this->formatIso($this->created_at),
            'updated_at' => $this->formatIso($this->updated_at),

            // Role information
            'role' => $this->getRoleName(),
            'role_display_name' => $this->getRoleDisplayName(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->when(
                $request->has('include_permissions'),
                fn () => $this->getAllPermissions()->pluck('name')
            ),

            // Computed flags
            'is_super_admin' => $this->isSuperAdmin(),
            'has_2fa' => $this->two_factor_confirmed_at !== null,
        ];
    }
}
