<?php

namespace Tests\Concerns;

use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Resources\Json\JsonResource;
use PHPUnit\Framework\Assert;

/**
 * Trait for validating that API Resource output matches TypeScript schema.
 *
 * This helps ensure type safety between PHP Resources and generated TypeScript types.
 */
trait ValidatesResourceSchema
{
    /**
     * Assert that a Resource's toArray() output matches its TypeScript schema.
     *
     * @param  JsonResource&HasTypescriptType  $resource
     */
    protected function assertResourceMatchesSchema(JsonResource $resource): void
    {
        $schema = $resource::typescriptSchema();
        $output = $resource->resolve(request());

        $this->validateAgainstSchema($output, $schema, $resource::typescriptName());

        // Explicit assertion for PHPUnit to count
        Assert::assertTrue(true, "Resource {$resource::typescriptName()} matches its TypeScript schema");
    }

    /**
     * Validate an array against a TypeScript schema definition.
     */
    protected function validateAgainstSchema(array $output, array $schema, string $context = 'root'): void
    {
        foreach ($schema as $key => $type) {
            $isOptional = $this->isOptionalType($type);
            $isNullable = $this->isNullableType($type);

            // Check key existence
            if (! array_key_exists($key, $output)) {
                if ($isOptional) {
                    continue; // Optional fields can be missing
                }
                Assert::fail("Missing required key '{$key}' in {$context}. Schema expects: {$type}");
            }

            $value = $output[$key];

            // Null check for nullable types
            if ($value === null) {
                if (! $isNullable) {
                    Assert::fail("Key '{$key}' in {$context} is null but schema type '{$type}' is not nullable");
                }

                continue;
            }

            // Validate type
            $this->validateType($value, $type, "{$context}.{$key}");
        }

        // Warn about extra keys not in schema (but don't fail - allows for backwards compatibility)
        $extraKeys = array_diff(array_keys($output), array_keys($schema));
        if (! empty($extraKeys) && property_exists($this, 'warnOnExtraKeys') && $this->warnOnExtraKeys) {
            fwrite(STDERR, "Warning: Extra keys in {$context} not in schema: ".implode(', ', $extraKeys)."\n");
        }
    }

    /**
     * Validate a value against a TypeScript type string.
     */
    protected function validateType(mixed $value, string $type, string $context): void
    {
        // Remove nullable part for type checking
        $baseType = $this->getBaseType($type);

        // Handle union types (e.g., "string | number", "DomainResource[] | undefined")
        if (str_contains($baseType, ' | ') || str_contains($baseType, '|')) {
            $types = array_map('trim', preg_split('/\s*\|\s*/', $baseType));
            // Filter out 'undefined' from types - it just means field can be missing
            $types = array_filter($types, fn ($t) => $t !== 'undefined');

            $matchesAny = false;
            foreach ($types as $unionType) {
                // For array types in unions, recursively validate
                if (str_ends_with($unionType, '[]')) {
                    if ($this->isArrayLike($value)) {
                        $matchesAny = true;
                        break;
                    }
                } elseif ($this->valueMatchesType($value, $unionType)) {
                    $matchesAny = true;
                    break;
                }
            }
            if (! $matchesAny) {
                Assert::fail("Value at '{$context}' does not match any type in union '{$type}'. Got: ".gettype($value));
            }

            return;
        }

        // Handle array types (e.g., "string[]", "UserResource[]")
        if (str_ends_with($baseType, '[]')) {
            Assert::assertTrue(
                $this->isArrayLike($value),
                "Expected array at '{$context}' for type '{$type}', got ".gettype($value)
            );
            // Convert collections to array for iteration
            $items = $this->toArray($value);
            $elementType = substr($baseType, 0, -2);
            foreach ($items as $index => $element) {
                $this->validateType($element, $elementType, "{$context}[{$index}]");
            }

            return;
        }

        // Handle Record<K, V> types
        if (str_starts_with($baseType, 'Record<')) {
            Assert::assertIsArray($value, "Expected array/object at '{$context}' for type '{$type}'");

            return; // Record types are flexible, just ensure it's an array
        }

        // Handle object types { key: type }
        if (str_starts_with($baseType, '{')) {
            Assert::assertIsArray($value, "Expected object at '{$context}' for type '{$type}'");

            return;
        }

        // Validate primitive types
        if (! $this->valueMatchesType($value, $baseType)) {
            Assert::fail(
                "Type mismatch at '{$context}': expected '{$type}', got '".
                gettype($value)."' with value ".json_encode($value)
            );
        }
    }

    /**
     * Check if a value matches a single TypeScript type.
     */
    protected function valueMatchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => is_null($value),
            'undefined' => false, // undefined means field can be missing, not a value match
            'any', 'unknown' => true,
            default => $this->isEnumOrResourceType($value, $type),
        };
    }

    /**
     * Check if a value matches an enum or Resource/object type.
     *
     * Enum types (like BillingPeriod, AddonType) are strings at runtime.
     * Resource types are arrays (objects in JSON).
     */
    protected function isEnumOrResourceType(mixed $value, string $type): bool
    {
        // Known enum types are strings at runtime
        $enumTypes = [
            'BillingPeriod', 'AddonType', 'AddonStatus', 'TenantRole',
            'TenantStatus', 'FederationSyncStrategy', 'FederationConflictStatus',
            'PermissionCategory', 'PermissionAction', 'BadgePreset',
        ];

        if (in_array($type, $enumTypes, true)) {
            return is_string($value);
        }

        // Resource types should be arrays (objects in JSON)
        // We can't deeply validate without knowing the referenced Resource's schema
        return is_array($value) || $this->isArrayLike($value);
    }

    /**
     * Check if a value is array-like (array, Collection, etc).
     */
    protected function isArrayLike(mixed $value): bool
    {
        return is_array($value)
            || $value instanceof \Illuminate\Support\Collection
            || $value instanceof \Illuminate\Http\Resources\Json\AnonymousResourceCollection
            || $value instanceof \ArrayAccess;
    }

    /**
     * Convert array-like value to array.
     */
    protected function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof \Illuminate\Support\Collection
            || $value instanceof \Illuminate\Http\Resources\Json\AnonymousResourceCollection) {
            return $value->toArray();
        }

        if ($value instanceof \ArrayAccess) {
            return iterator_to_array($value);
        }

        return (array) $value;
    }

    /**
     * Check if a type is nullable (contains "| null" or "| undefined").
     *
     * In TypeScript, `| undefined` means the field can be missing OR null,
     * so we treat it as nullable for validation purposes.
     */
    protected function isNullableType(string $type): bool
    {
        return str_contains($type, '| null')
            || str_contains($type, '|null')
            || str_contains($type, '| undefined')
            || str_contains($type, '|undefined');
    }

    /**
     * Check if a type is optional (starts with "?" or contains "| undefined").
     *
     * Fields with `| undefined` can be missing from output entirely.
     */
    protected function isOptionalType(string $type): bool
    {
        return str_starts_with($type, '?')
            || str_contains($type, '| undefined')
            || str_contains($type, '|undefined');
    }

    /**
     * Get the base type without nullable modifier.
     */
    protected function getBaseType(string $type): string
    {
        // Remove "| null" suffix
        $type = preg_replace('/\s*\|\s*null\s*$/', '', $type);
        // Remove leading "?"
        $type = ltrim($type, '?');

        return trim($type);
    }

    /**
     * Get all Resource classes that use HasTypescriptType.
     *
     * @return array<class-string<JsonResource>>
     */
    protected function getResourcesWithTypescriptType(): array
    {
        $resourcesPath = app_path('Http/Resources');
        $resources = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($resourcesPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $className = $this->getClassNameFromFile($file->getPathname());
                if ($className && $this->usesTypescriptTrait($className)) {
                    $resources[] = $className;
                }
            }
        }

        return $resources;
    }

    /**
     * Get the fully qualified class name from a PHP file.
     */
    protected function getClassNameFromFile(string $filepath): ?string
    {
        $contents = file_get_contents($filepath);

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $contents, $namespaceMatches)) {
            $namespace = $namespaceMatches[1];
        } else {
            return null;
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/', $contents, $classMatches)) {
            $className = $classMatches[1];
        } else {
            return null;
        }

        return $namespace.'\\'.$className;
    }

    /**
     * Check if a class uses the HasTypescriptType trait.
     */
    protected function usesTypescriptTrait(string $className): bool
    {
        if (! class_exists($className)) {
            return false;
        }

        $traits = class_uses_recursive($className);

        return in_array(HasTypescriptType::class, $traits, true);
    }
}
