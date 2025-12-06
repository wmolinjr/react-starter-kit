<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 * Media Model
 *
 * MULTI-DATABASE TENANCY:
 * - Lives in tenant database (no tenant_id column needed)
 * - Isolation is at database level, not row level
 * - Stores tenant_id in custom_properties for consistent path generation
 * - Uses UUID for consistency across all models
 */
class Media extends BaseMedia
{
    use HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'manipulations' => 'array',
        'custom_properties' => 'array',
        'generated_conversions' => 'array',
        'responsive_images' => 'array',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'model_type',
        'model_id',
        'uuid',
        'collection_name',
        'name',
        'file_name',
        'mime_type',
        'disk',
        'conversions_disk',
        'size',
        'manipulations',
        'custom_properties',
        'generated_conversions',
        'responsive_images',
        'order_column',
    ];

    /**
     * Boot the model.
     *
     * Automatically sets tenant_id in custom_properties when media is created.
     * This ensures consistent path generation via TenantPathGenerator.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (Media $media) {
            // Store tenant ID in custom_properties for path generation
            if (tenancy()->initialized && ! $media->hasCustomProperty('tenant_id')) {
                $media->setCustomProperty('tenant_id', tenant('id'));
            }
        });
    }

    /**
     * Get the tenant ID for path generation.
     *
     * Returns the stored tenant_id from custom_properties, or current tenant context,
     * or 'global' as fallback.
     */
    public function getTenantIdForPath(): string|int
    {
        // First, try custom_properties (most reliable)
        if ($this->hasCustomProperty('tenant_id')) {
            return $this->getCustomProperty('tenant_id');
        }

        // Fallback to current tenant context
        if (tenancy()->initialized) {
            return tenant('id');
        }

        // Final fallback
        return 'global';
    }
}
