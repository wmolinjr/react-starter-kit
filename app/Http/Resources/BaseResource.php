<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BaseResource
 *
 * Base class for all API Resources providing common functionality.
 * All Resources should extend this class for consistency.
 */
abstract class BaseResource extends JsonResource
{
    /**
     * Disable the 'data' wrapper for Inertia compatibility.
     *
     * Inertia expects flat arrays, not wrapped in 'data'.
     */
    public static $wrap = null;

    /**
     * Get translated value for current locale.
     *
     * Works with models that use Spatie\Translatable\HasTranslations.
     */
    protected function trans(string $key): ?string
    {
        if (method_exists($this->resource, 'getTranslation')) {
            return $this->resource->getTranslation($key, app()->getLocale());
        }

        return $this->resource->{$key} ?? null;
    }

    /**
     * Get all translations for a field.
     *
     * Returns array with locale => value pairs for translatable fields.
     */
    protected function translations(string $key): array
    {
        if (method_exists($this->resource, 'getTranslations')) {
            return $this->resource->getTranslations($key);
        }

        return [];
    }

    /**
     * Format date for frontend display.
     *
     * @param  Carbon|string|null  $date
     */
    protected function formatDate($date, string $format = 'Y-m-d H:i'): ?string
    {
        if ($date === null) {
            return null;
        }

        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return $date->format($format);
    }

    /**
     * Format date as ISO 8601 string.
     *
     * Best for JavaScript Date parsing.
     *
     * @param  Carbon|string|null  $date
     */
    protected function formatIso($date): ?string
    {
        if ($date === null) {
            return null;
        }

        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return $date->toISOString();
    }

    /**
     * Format date as human-readable relative string.
     *
     * @param  Carbon|string|null  $date
     */
    protected function formatDiff($date): ?string
    {
        if ($date === null) {
            return null;
        }

        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return $date->diffForHumans();
    }

    /**
     * Format date as date only (no time).
     *
     * @param  Carbon|string|null  $date
     */
    protected function formatDateOnly($date): ?string
    {
        return $this->formatDate($date, 'Y-m-d');
    }

    /**
     * Format currency value.
     */
    protected function formatCurrency(int $cents, string $currency = 'BRL'): string
    {
        $value = $cents / 100;

        return number_format($value, 2, ',', '.').' '.strtoupper($currency);
    }

    /**
     * Include field only when condition is true.
     *
     * Wrapper around whenLoaded for non-relationship conditionals.
     */
    protected function includeWhen(bool $condition, callable $callback): mixed
    {
        return $this->when($condition, $callback);
    }

    /**
     * Get count from loaded relationship or compute it.
     *
     * Useful for _count fields that may or may not be eager loaded.
     */
    protected function countOrCompute(string $relation): int
    {
        $countAttribute = "{$relation}_count";

        if (isset($this->resource->{$countAttribute})) {
            return $this->resource->{$countAttribute};
        }

        if ($this->resource->relationLoaded($relation)) {
            return $this->resource->{$relation}->count();
        }

        return $this->resource->{$relation}()->count();
    }
}
