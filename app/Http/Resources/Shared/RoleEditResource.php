<?php

namespace App\Http\Resources\Shared;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * RoleEditResource
 *
 * Role data for edit forms.
 */
class RoleEditResource extends BaseResource
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
            'display_name' => $this->translations('display_name'),
            'display_name_display' => $this->trans('display_name') ?: $this->name,
            'description' => $this->translations('description'),
            'is_protected' => $this->isProtected(),
            'permission_ids' => $this->when(
                $this->relationLoaded('permissions'),
                fn () => $this->permissions->pluck('id')->toArray()
            ),
        ];
    }
}
