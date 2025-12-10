<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Translatable\HasTranslations;

/**
 * Role Model
 *
 * SHARED MODEL:
 * - Works in both central and tenant contexts
 * - Central roles: Stored in central database (Super Admin, Central Admin)
 * - Tenant roles: Stored in each tenant's database (owner, admin, member)
 * - Isolation is at the database level - no type column needed
 * - Uses UUID for consistency across all models
 */
class Role extends SpatieRole
{
    use HasTranslations, HasUuids;

    /**
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    public array $translatable = [
        'display_name',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_protected' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'display_name',
        'is_protected',
    ];

    /**
     * Create a new role.
     *
     * Simplified for multi-database tenancy - no tenant_id logic needed.
     *
     * @throws \Spatie\Permission\Exceptions\RoleAlreadyExists
     */
    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] ??= Guard::getDefaultName(static::class);

        // Check for exact duplicate (same name and guard)
        $query = static::query()
            ->where('name', $attributes['name'])
            ->where('guard_name', $attributes['guard_name']);

        if ($query->exists()) {
            throw \Spatie\Permission\Exceptions\RoleAlreadyExists::create(
                $attributes['name'],
                $attributes['guard_name']
            );
        }

        return static::query()->create($attributes);
    }

    /**
     * Find a role by name.
     *
     * @throws \Spatie\Permission\Exceptions\RoleDoesNotExist
     */
    public static function findByName(string $name, $guardName = null): self
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $role = static::query()
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->first();

        if (! $role) {
            throw new \Spatie\Permission\Exceptions\RoleDoesNotExist;
        }

        return $role;
    }

    /**
     * Scope to filter protected roles.
     */
    public function scopeProtected(Builder $query): Builder
    {
        return $query->where('is_protected', true);
    }

    /**
     * Scope to filter custom (non-protected) roles.
     */
    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_protected', false);
    }

    /**
     * Check if this role is a system/protected role.
     */
    public function isProtected(): bool
    {
        return $this->is_protected || in_array($this->name, ['owner', 'admin', 'member', 'Super Admin', 'Central Admin']);
    }
}
