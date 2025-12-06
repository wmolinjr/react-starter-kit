<?php

namespace App\Http\Resources\Universal;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * RoleResource
 *
 * Role information for listing views.
 */
class RoleResource extends BaseResource
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
            'display_name' => $this->trans('display_name') ?: $this->name,
            'description' => $this->trans('description'),
            'users_count' => $this->countOrCompute('users'),
            'permissions_count' => $this->countOrCompute('permissions'),
            'is_protected' => $this->isProtected(),
            'created_at' => $this->formatIso($this->created_at),
        ];
    }
}
