<?php

namespace App\Http\Resources\Shared;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * RoleEditResource
 *
 * Role data for edit forms.
 */
class RoleEditResource extends BaseResource
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
            'display_name' => 'Translations',
            'display_name_display' => 'string',
            'description' => 'Translations',
            'is_protected' => 'boolean',
            'permission_ids' => 'string[] | undefined',
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
