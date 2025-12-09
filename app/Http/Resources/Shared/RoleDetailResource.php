<?php

namespace App\Http\Resources\Shared;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use App\Http\Resources\Tenant\UserSummaryResource;
use Illuminate\Http\Request;

/**
 * RoleDetailResource
 *
 * Full role information with permissions and users.
 */
class RoleDetailResource extends BaseResource
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
            'display_name' => 'string',
            'description' => 'string | null',
            'is_protected' => 'boolean',
            'permissions' => 'PermissionResource[] | undefined',
            'users' => 'UserSummaryResource[] | undefined',
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
            'name' => $this->name,
            'display_name' => $this->trans('display_name') ?: $this->name,
            'description' => $this->trans('description'),
            'is_protected' => $this->isProtected(),
            'permissions' => $this->when(
                $this->relationLoaded('permissions'),
                fn () => PermissionResource::collection($this->permissions)
            ),
            'users' => $this->when(
                $this->relationLoaded('users'),
                fn () => UserSummaryResource::collection($this->users)
            ),
            'created_at' => $this->formatIso($this->created_at),
        ];
    }
}
