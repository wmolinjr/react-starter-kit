<?php

namespace App\Traits;

use App\Models\Tenant\TenantTranslationOverride;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;
use Spatie\Translatable\HasTranslations;

/**
 * HasTenantTranslations Trait
 *
 * MULTI-DATABASE TENANCY:
 * - Translation overrides are stored per-tenant database
 * - NO tenant_id column - isolation is at database level
 *
 * Extends Spatie's HasTranslations with tenant-specific override support.
 * Enables white-label customization of translations per tenant.
 *
 * Resolution order:
 * 1. Tenant override (if in tenant context and customizable)
 * 2. Base translation (Spatie Translatable)
 * 3. Fallback locale
 *
 * Usage:
 * - $model->trans('name')           // Get with tenant override
 * - $model->getTranslation('name')  // Get base translation only
 * - $model->setTenantTranslation('name', 'en', 'Custom Name')
 */
trait HasTenantTranslations
{
    use HasTranslations;

    /**
     * Relationship to tenant translation overrides.
     */
    public function translationOverrides(): MorphMany
    {
        return $this->morphMany(TenantTranslationOverride::class, 'translatable');
    }

    /**
     * Get translation with tenant override support.
     *
     * Priority: Tenant Override > Base Translation > Fallback Locale
     *
     * @param  string  $key  The translatable field name
     * @param  string|null  $locale  The locale (defaults to app locale)
     * @param  bool  $useFallbackLocale  Whether to use fallback locale
     */
    public function trans(string $key, ?string $locale = null, bool $useFallbackLocale = true): string
    {
        $locale = $locale ?? app()->getLocale();
        $fallbackLocale = $useFallbackLocale ? config('app.fallback_locale') : null;

        // 1. Check tenant override (if in tenant context and field is customizable)
        if ($this->canHaveTenantOverride($key) && tenancy()->initialized) {
            $override = $this->getTenantOverride($key);

            if ($override) {
                $translation = $override->getTranslation($locale, $fallbackLocale);
                if ($translation !== null) {
                    return $translation;
                }
            }
        }

        // 2. Fall back to base translation (Spatie)
        // Return empty string if no translation exists to satisfy string return type
        return $this->getTranslation($key, $locale, $useFallbackLocale) ?? '';
    }

    /**
     * Check if field can have tenant override.
     */
    protected function canHaveTenantOverride(string $key): bool
    {
        // Check if model has is_customizable field
        if (! property_exists($this, 'attributes') || ! isset($this->attributes['is_customizable'])) {
            // If no is_customizable field, check for customizableFields property
            if (property_exists($this, 'customizableFields')) {
                return in_array($key, $this->customizableFields);
            }

            // Default: allow if is_customizable attribute exists and is true
            return $this->is_customizable ?? true;
        }

        return (bool) $this->is_customizable;
    }

    /**
     * Get tenant override for a specific field.
     *
     * MULTI-DATABASE TENANCY:
     * - No tenant_id filter needed - data is isolated per database
     * - Translation overrides use UUID morphs - skip if model has non-UUID ID
     */
    protected function getTenantOverride(string $field): ?TenantTranslationOverride
    {
        // Skip override lookup if model doesn't have a UUID ID
        // The tenant_translation_overrides table uses uuidMorphs
        if (!$this->hasUuidId()) {
            return null;
        }

        // When cache doesn't support tagging, skip caching entirely
        if (! $this->cacheSupportsTagging()) {
            return TenantTranslationOverride::where([
                'translatable_type' => static::class,
                'translatable_id' => $this->id,
                'field' => $field,
            ])->first();
        }

        // Cache key includes model info - tenant isolation is handled by RedisTenancyBootstrapper
        $cacheKey = "trans_override:".static::class.":{$this->id}:{$field}";

        return Cache::tags(['tenant_translations'])
            ->remember($cacheKey, 3600, function () use ($field) {
                return TenantTranslationOverride::where([
                    'translatable_type' => static::class,
                    'translatable_id' => $this->id,
                    'field' => $field,
                ])->first();
            });
    }

    /**
     * Check if this model uses UUID as its primary key.
     */
    protected function hasUuidId(): bool
    {
        // Check if the ID is a valid UUID format
        $id = $this->id;

        if (!is_string($id)) {
            return false;
        }

        // UUID pattern: 8-4-4-4-12 hex characters
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) === 1;
    }

    /**
     * Set tenant-specific translation override.
     *
     * MULTI-DATABASE TENANCY:
     * - No tenant_id needed - stored in tenant's dedicated database
     * - Translation overrides use UUID morphs - requires model with UUID ID
     *
     * @param  string  $field  The translatable field name
     * @param  string  $locale  The locale
     * @param  string  $value  The translation value
     *
     * @throws \Exception If field is not customizable or model has non-UUID ID
     */
    public function setTenantTranslation(string $field, string $locale, string $value): self
    {
        if (!$this->hasUuidId()) {
            throw new \Exception('Tenant translation overrides require models with UUID IDs.');
        }

        if (! $this->canHaveTenantOverride($field)) {
            throw new \Exception("Field '{$field}' is not customizable for this model.");
        }

        $override = TenantTranslationOverride::firstOrNew([
            'translatable_type' => static::class,
            'translatable_id' => $this->id,
            'field' => $field,
        ]);

        $translations = $override->translations ?? [];
        $translations[$locale] = $value;
        $override->translations = $translations;
        $override->save();

        // Clear cache
        $this->clearTenantTranslationCache();

        return $this;
    }

    /**
     * Set multiple tenant translations at once.
     *
     * @param  string  $field  The translatable field name
     * @param  array  $translations  Array of locale => value pairs
     *
     * @throws \Exception If field is not customizable or model has non-UUID ID
     */
    public function setTenantTranslations(string $field, array $translations): self
    {
        if (!$this->hasUuidId()) {
            throw new \Exception('Tenant translation overrides require models with UUID IDs.');
        }

        if (! $this->canHaveTenantOverride($field)) {
            throw new \Exception("Field '{$field}' is not customizable for this model.");
        }

        TenantTranslationOverride::updateOrCreate(
            [
                'translatable_type' => static::class,
                'translatable_id' => $this->id,
                'field' => $field,
            ],
            ['translations' => $translations]
        );

        $this->clearTenantTranslationCache();

        return $this;
    }

    /**
     * Remove tenant-specific translation override.
     *
     * @param  string  $field  The translatable field name
     * @param  string|null  $locale  Specific locale to remove (null = all)
     */
    public function removeTenantTranslation(string $field, ?string $locale = null): self
    {
        $override = TenantTranslationOverride::where([
            'translatable_type' => static::class,
            'translatable_id' => $this->id,
            'field' => $field,
        ])->first();

        if ($override) {
            if ($locale) {
                // Remove specific locale
                $override->forgetTranslation($locale);

                if (empty($override->translations)) {
                    $override->delete();
                } else {
                    $override->save();
                }
            } else {
                // Remove all overrides for this field
                $override->delete();
            }

            $this->clearTenantTranslationCache();
        }

        return $this;
    }

    /**
     * Check if tenant has override for field.
     */
    public function hasTenantOverride(string $field): bool
    {
        if (! tenancy()->initialized) {
            return false;
        }

        return $this->getTenantOverride($field) !== null;
    }

    /**
     * Get tenant override translations for a field (if exists).
     */
    public function getTenantTranslations(string $field): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        $override = $this->getTenantOverride($field);

        return $override?->translations;
    }

    /**
     * Get all translations including tenant overrides for API/frontend.
     *
     * Returns merged array where tenant overrides take precedence.
     */
    public function getAllTranslationsWithOverrides(string $field): array
    {
        $base = $this->getTranslations($field);

        if (tenancy()->initialized && $this->canHaveTenantOverride($field)) {
            $override = $this->getTenantOverride($field);
            if ($override) {
                // Merge: override takes precedence
                return array_merge($base, $override->translations ?? []);
            }
        }

        return $base;
    }

    /**
     * Get translation data for frontend (includes override info).
     */
    public function getTranslationData(string $field): array
    {
        $inTenantContext = tenancy()->initialized;

        return [
            'base' => $this->getTranslations($field),
            'override' => $inTenantContext ? $this->getTenantTranslations($field) : null,
            'current' => $this->getAllTranslationsWithOverrides($field),
            'has_override' => $inTenantContext ? $this->hasTenantOverride($field) : false,
            'is_customizable' => $this->canHaveTenantOverride($field),
        ];
    }

    /**
     * Clear tenant translation cache.
     *
     * Tenant isolation is handled by RedisTenancyBootstrapper - no need for tenant-specific tags.
     */
    protected function clearTenantTranslationCache(): void
    {
        if ($this->cacheSupportsTagging()) {
            Cache::tags(['tenant_translations'])->flush();
        } else {
            // Without tags, we can't selectively clear - clear the entire cache
            // In production, use Redis which supports tagging
            Cache::flush();
        }
    }

    /**
     * Clear all translation caches for this model.
     */
    public function clearAllTranslationCaches(): void
    {
        if ($this->cacheSupportsTagging()) {
            Cache::tags(['tenant_translations'])->flush();
        } else {
            Cache::flush();
        }
    }

    /**
     * Check if the current cache store supports tagging.
     */
    protected function cacheSupportsTagging(): bool
    {
        try {
            $store = Cache::getStore();

            return $store instanceof \Illuminate\Cache\TaggableStore;
        } catch (\Exception) {
            return false;
        }
    }
}
