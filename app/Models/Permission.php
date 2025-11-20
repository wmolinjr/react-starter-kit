<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use BelongsToTenant;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tenant_id' => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'guard_name',
    ];

    /**
     * Find a permission by name, scoped to current tenant.
     *
     * Override do método original para adicionar tenant scope
     */
    public static function findByName(string $name, $guardName = null): self
    {
        $guardName = $guardName ?? static::getDefaultGuardName();

        $query = static::query()->where('name', $name)->where('guard_name', $guardName);

        // Se tenant context está ativo, filtrar por tenant_id
        if (tenancy()->initialized) {
            $query->where('tenant_id', tenant('id'));
        }

        $permission = $query->first();

        if (! $permission) {
            throw new \Spatie\Permission\Exceptions\PermissionDoesNotExist();
        }

        return $permission;
    }

    /**
     * Find or create permission, scoped to current tenant.
     */
    public static function findOrCreate(string $name, $guardName = null): self
    {
        $guardName = $guardName ?? static::getDefaultGuardName();

        $query = static::query()->where('name', $name)->where('guard_name', $guardName);

        // Se tenant context está ativo, filtrar por tenant_id
        if (tenancy()->initialized) {
            $query->where('tenant_id', tenant('id'));
        }

        $permission = $query->first();

        if (! $permission) {
            return static::create(['name' => $name, 'guard_name' => $guardName]);
        }

        return $permission;
    }
}
