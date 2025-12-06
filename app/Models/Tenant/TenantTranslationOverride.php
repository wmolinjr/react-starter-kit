<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Tenant Translation Override
 *
 * MULTI-DATABASE TENANCY:
 * - Lives in tenant database (no tenant_id column needed)
 * - Isolation is at database level, not row level
 * - Uses UUID for consistency across all models
 *
 * Stores tenant-specific translation overrides for white-label customization.
 * Works with any model that uses the HasTenantTranslations trait.
 *
 * @property string $id
 * @property string $translatable_type
 * @property int $translatable_id
 * @property string $field
 * @property array $translations
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TenantTranslationOverride extends Model
{
    use HasUuids;

    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'field',
        'translations',
    ];

    protected $casts = [
        'translations' => 'array',
    ];

    /**
     * Get the translatable model.
     */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get translation for specific locale with optional fallback.
     */
    public function getTranslation(string $locale, ?string $fallbackLocale = null): ?string
    {
        $translations = $this->translations ?? [];

        return $translations[$locale]
            ?? ($fallbackLocale ? ($translations[$fallbackLocale] ?? null) : null);
    }

    /**
     * Set translation for a specific locale.
     */
    public function setTranslation(string $locale, string $value): self
    {
        $translations = $this->translations ?? [];
        $translations[$locale] = $value;
        $this->translations = $translations;

        return $this;
    }

    /**
     * Remove translation for a specific locale.
     */
    public function forgetTranslation(string $locale): self
    {
        $translations = $this->translations ?? [];
        unset($translations[$locale]);
        $this->translations = $translations;

        return $this;
    }

    /**
     * Check if a locale has a translation.
     */
    public function hasTranslation(string $locale): bool
    {
        return isset($this->translations[$locale]);
    }

    /**
     * Get all available locales for this override.
     */
    public function getAvailableLocales(): array
    {
        return array_keys($this->translations ?? []);
    }
}
