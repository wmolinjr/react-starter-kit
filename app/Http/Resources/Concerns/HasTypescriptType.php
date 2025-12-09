<?php

namespace App\Http\Resources\Concerns;

/**
 * Trait for API Resources that export TypeScript types.
 *
 * Resources using this trait will have their types auto-generated
 * by `php artisan types:generate`.
 *
 * Usage:
 * ```php
 * class ProjectResource extends BaseResource
 * {
 *     use HasTypescriptType;
 *
 *     public static function typescriptSchema(): array
 *     {
 *         return [
 *             'id' => 'string',
 *             'name' => 'string',
 *             'description' => 'string | null',
 *             'status' => 'string',
 *             'created_at' => 'string',
 *             'user' => 'UserSummary | null',
 *         ];
 *     }
 * }
 * ```
 */
trait HasTypescriptType
{
    /**
     * Define the TypeScript interface for this Resource.
     *
     * Return an associative array where:
     * - Keys are property names
     * - Values are TypeScript types
     *
     * Supported types:
     * - Primitives: 'string', 'number', 'boolean', 'null'
     * - Nullable: 'string | null'
     * - Arrays: 'string[]', 'number[]', 'ProjectResource[]'
     * - Objects: 'Record<string, boolean>', '{ [key: string]: number }'
     * - References: 'UserSummary', 'ProjectResource' (other generated types)
     * - Unions: 'string | number'
     * - Enums: 'BadgePreset' (from @/types/enums)
     *
     * @return array<string, string>
     */
    abstract public static function typescriptSchema(): array;

    /**
     * Get the TypeScript interface name for this Resource.
     *
     * Defaults to class name without 'Resource' suffix.
     * Override if you need a different name.
     */
    public static function typescriptName(): string
    {
        $className = class_basename(static::class);

        // Remove 'Resource' suffix if present
        if (str_ends_with($className, 'Resource')) {
            return $className;
        }

        return $className;
    }

    /**
     * Get the context (central, tenant, shared) for organizing types.
     *
     * Returns null for auto-detection based on namespace.
     */
    public static function typescriptContext(): ?string
    {
        $namespace = (new \ReflectionClass(static::class))->getNamespaceName();

        if (str_contains($namespace, '\\Central\\')) {
            return 'central';
        }

        if (str_contains($namespace, '\\Tenant\\')) {
            return 'tenant';
        }

        if (str_contains($namespace, '\\Shared\\')) {
            return 'shared';
        }

        return null;
    }
}
