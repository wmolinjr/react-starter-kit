<?php

namespace App\Models\Shared;

use App\Enums\CentralPermission;
use App\Enums\TenantPermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permission Model
 *
 * SHARED MODEL:
 * - Works in both central and tenant contexts
 * - Stored in respective database (central or tenant)
 * - Isolation is at the database level
 * - Uses UUID for consistency across all models
 *
 * SINGLE SOURCE OF TRUTH:
 * - category and description are derived from enums (TenantPermission/CentralPermission)
 * - No redundant columns in database
 */
class Permission extends SpatiePermission
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'guard_name',
    ];

    /**
     * Get the category derived from permission name.
     * Format: category:action -> returns category
     */
    public function getCategoryAttribute(): string
    {
        return TenantPermission::extractCategory($this->name);
    }

    /**
     * Get the category description from the enum (single source of truth).
     * Checks TenantPermission first, then CentralPermission.
     *
     * @return array{en: string, pt_BR: string}
     */
    public function getCategoryDescriptionAttribute(): array
    {
        $category = $this->category;

        // Try TenantPermission first
        if (TenantPermission::tryFrom($this->name)) {
            return TenantPermission::categoryDescription($category);
        }

        // Fall back to CentralPermission
        return CentralPermission::categoryDescription($category);
    }

    /**
     * Get the description from the enum (single source of truth).
     * Checks TenantPermission first, then CentralPermission.
     */
    public function getDescriptionAttribute(): array
    {
        // Try TenantPermission first
        $description = TenantPermission::descriptionFor($this->name);
        if ($description) {
            return $description;
        }

        // Fall back to CentralPermission
        return CentralPermission::descriptionFor($this->name) ?? [];
    }

    /**
     * Get translated value for a field in the current locale.
     *
     * @param  string  $field  'description' or 'category'
     */
    public function trans(string $field): ?string
    {
        $locale = app()->getLocale();

        if ($field === 'description') {
            $data = $this->description;

            return $data[$locale] ?? $data['en'] ?? null;
        }

        if ($field === 'category') {
            $data = $this->category_description;

            return $data[$locale] ?? $data['en'] ?? null;
        }

        return null;
    }

    /**
     * Find a permission by name.
     *
     * @throws \Spatie\Permission\Exceptions\PermissionDoesNotExist
     */
    public static function findByName(string $name, $guardName = null): self
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $permission = static::query()
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->first();

        if (! $permission) {
            throw new \Spatie\Permission\Exceptions\PermissionDoesNotExist;
        }

        return $permission;
    }

    /**
     * Find or create permission.
     */
    public static function findOrCreate(string $name, $guardName = null): self
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $permission = static::query()
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->first();

        if (! $permission) {
            return static::create([
                'name' => $name,
                'guard_name' => $guardName,
            ]);
        }

        return $permission;
    }

    /**
     * Scope to filter permissions by category (derived from name).
     */
    public function scopeInCategory(Builder $query, string $category): Builder
    {
        return $query->where('name', 'like', "{$category}:%");
    }
}
