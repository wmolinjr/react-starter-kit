<?php

namespace App\Http\Resources\Shared;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * PermissionResource
 *
 * Permission information for listing and assignment.
 */
class PermissionResource extends BaseResource
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
            'description' => $this->trans('description'),
            'category' => $this->category,
        ];
    }
}
