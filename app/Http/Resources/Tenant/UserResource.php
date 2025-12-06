<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * UserResource
 *
 * Full tenant user information.
 */
class UserResource extends BaseResource
{
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
            'department' => $this->department,
            'employee_id' => $this->employee_id,
            'email_verified_at' => $this->formatIso($this->email_verified_at),
            'two_factor_confirmed_at' => $this->formatIso($this->two_factor_confirmed_at),
            'created_at' => $this->formatIso($this->created_at),
            'updated_at' => $this->formatIso($this->updated_at),

            // Role information
            'role' => $this->roles->first()?->name,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->when(
                $request->has('include_permissions'),
                fn () => $this->getAllPermissions()->pluck('name')
            ),

            // Computed flags
            'is_owner' => $this->isOwner(),
            'is_admin' => $this->isAdmin(),
            'has_2fa' => $this->two_factor_confirmed_at !== null,
        ];
    }
}
