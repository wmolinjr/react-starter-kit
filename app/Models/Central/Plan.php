<?php

namespace App\Models\Central;

use App\Models\Shared\Permission;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Plan Model
 *
 * Represents subscription plans with features and limits.
 * Supports multi-language translations via Spatie Translatable.
 *
 * @property string $id
 * @property array $name
 * @property string $slug
 * @property array|null $description
 * @property int $price
 * @property string $currency
 * @property string $billing_period
 * @property array|null $features
 * @property array|null $limits
 * @property array|null $permission_map
 * @property bool $is_active
 * @property bool $is_featured
 * @property int $sort_order
 */
class Plan extends Model
{
    use CentralConnection, HasFactory, HasTranslations, HasUuids;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Database\Factories\PlanFactory
    {
        return \Database\Factories\PlanFactory::new();
    }

    /**
     * Fields that support translations (Spatie Translatable).
     */
    public array $translatable = [
        'name',
        'description',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_period',
        'provider_product_ids',
        'provider_price_ids',
        'features',
        'limits',
        'permission_map',
        'is_active',
        'is_featured',
        'badge',
        'icon',
        'icon_color',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'provider_product_ids' => 'array',
        'provider_price_ids' => 'array',
        'features' => 'array',
        'limits' => 'array',
        'permission_map' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Addons available for this plan
     */
    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'addon_plan')
            ->withPivot([
                'price_override_monthly',
                'price_override_yearly',
                'price_override_one_time',
                'discount_percent',
                'included',
                'active',
            ])
            ->withTimestamps();
    }

    /**
     * Get active addons available for this plan
     */
    public function availableAddons(): BelongsToMany
    {
        return $this->addons()->wherePivot('active', true);
    }

    /**
     * Check if plan has a specific feature
     */
    public function hasFeature(string $feature): bool
    {
        return $this->features[$feature] ?? false;
    }

    /**
     * Get limit for a resource (-1 = unlimited)
     */
    public function getLimit(string $resource): int
    {
        return $this->limits[$resource] ?? 0;
    }

    /**
     * Check if limit is unlimited
     */
    public function isUnlimited(string $resource): bool
    {
        return $this->getLimit($resource) === -1;
    }

    /**
     * ⭐ Get permissions that should be enabled for a feature
     */
    public function getPermissionsForFeature(string $feature): array
    {
        return $this->permission_map[$feature] ?? [];
    }

    /**
     * ⭐ Get all permissions enabled by this plan
     */
    public function getAllEnabledPermissions(): array
    {
        $permissions = [];

        foreach ($this->features ?? [] as $feature => $enabled) {
            if ($enabled) {
                $featurePermissions = $this->getPermissionsForFeature($feature);
                $permissions = array_merge($permissions, $featurePermissions);
            }
        }

        return array_unique($permissions);
    }

    /**
     * ⭐ Expand wildcard permissions
     * "tenant.roles:*" → all roles permissions
     */
    public function expandPermissions(array $permissions): array
    {
        $expanded = [];

        foreach ($permissions as $permission) {
            if (str_ends_with($permission, ':*')) {
                // Wildcard: get all permissions for this category
                $category = str_replace(':*', '', $permission);
                $categoryPermissions = Permission::where('name', 'like', "{$category}:%")
                    ->pluck('name')
                    ->toArray();
                $expanded = array_merge($expanded, $categoryPermissions);
            } else {
                $expanded[] = $permission;
            }
        }

        return array_unique($expanded);
    }

    /**
     * Get formatted price using configured currency
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->price === 0) {
            return 'Custom';
        }

        return format_stripe_price($this->price, $this->currency);
    }

    /**
     * Get the price ID for a specific payment provider.
     */
    public function getProviderPriceId(string $provider): ?string
    {
        return $this->provider_price_ids[$provider] ?? null;
    }

    /**
     * Set the price ID for a specific payment provider.
     */
    public function setProviderPriceId(string $provider, ?string $priceId): void
    {
        $priceIds = $this->provider_price_ids ?? [];

        if ($priceId === null) {
            unset($priceIds[$provider]);
        } else {
            $priceIds[$provider] = $priceId;
        }

        $this->update(['provider_price_ids' => $priceIds]);
    }

    /**
     * Get the product ID for a specific payment provider.
     */
    public function getProviderProductId(string $provider): ?string
    {
        return $this->provider_product_ids[$provider] ?? null;
    }

    /**
     * Set the product ID for a specific payment provider.
     */
    public function setProviderProductId(string $provider, ?string $productId): void
    {
        $productIds = $this->provider_product_ids ?? [];

        if ($productId === null) {
            unset($productIds[$provider]);
        } else {
            $productIds[$provider] = $productId;
        }

        $this->update(['provider_product_ids' => $productIds]);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
