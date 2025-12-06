<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * AddonBundle Model
 *
 * Represents a package of multiple addons sold together with an optional discount.
 * When a tenant purchases a bundle, individual AddonSubscription records are created
 * for each addon in the bundle.
 *
 * @property string $id
 * @property string $slug
 * @property array $name
 * @property array|null $description
 * @property bool $active
 * @property int $discount_percent
 * @property int|null $price_monthly
 * @property int|null $price_yearly
 * @property string|null $stripe_product_id
 * @property string|null $stripe_price_monthly_id
 * @property string|null $stripe_price_yearly_id
 * @property string|null $badge
 * @property string|null $icon
 * @property array|null $features
 * @property int $sort_order
 * @property array|null $metadata
 */
class AddonBundle extends Model
{
    use CentralConnection;
    use HasFactory;
    use HasTranslations;
    use HasUuids;
    use SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Database\Factories\AddonBundleFactory
    {
        return \Database\Factories\AddonBundleFactory::new();
    }

    protected $fillable = [
        'slug',
        'name',
        'description',
        'active',
        'discount_percent',
        'price_monthly',
        'price_yearly',
        'currency',
        'stripe_product_id',
        'stripe_price_monthly_id',
        'stripe_price_yearly_id',
        'badge',
        'icon',
        'icon_color',
        'features',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'active' => 'boolean',
        'discount_percent' => 'integer',
        'price_monthly' => 'integer',
        'price_yearly' => 'integer',
        'features' => 'array',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public array $translatable = ['name', 'description'];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Addons included in this bundle
     */
    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'addon_bundle_items', 'bundle_id', 'addon_id')
            ->withPivot(['quantity', 'billing_period', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Plans that can purchase this bundle
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'addon_bundle_plan', 'bundle_id', 'plan_id')
            ->withPivot('active')
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Only active bundles
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Available for a specific plan
     */
    public function scopeForPlan($query, Plan|string $plan)
    {
        $planId = $plan instanceof Plan ? $plan->id : $plan;

        return $query->whereHas('plans', function ($q) use ($planId) {
            $q->where('plans.id', $planId)->where('addon_bundle_plan.active', true);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Pricing Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate base price from addons (before discount)
     */
    public function getBasePriceMonthly(): int
    {
        return $this->addons->sum(function ($addon) {
            $quantity = $addon->pivot->quantity ?? 1;

            return ($addon->price_monthly ?? 0) * $quantity;
        });
    }

    /**
     * Calculate base price from addons (before discount)
     */
    public function getBasePriceYearly(): int
    {
        return $this->addons->sum(function ($addon) {
            $quantity = $addon->pivot->quantity ?? 1;

            return ($addon->price_yearly ?? $addon->price_monthly * 10 ?? 0) * $quantity;
        });
    }

    /**
     * Get effective monthly price (with discount or override)
     */
    public function getEffectivePriceMonthly(): int
    {
        // Use override if set
        if ($this->price_monthly !== null) {
            return $this->price_monthly;
        }

        // Calculate with discount
        $base = $this->getBasePriceMonthly();

        return (int) round($base * (1 - $this->discount_percent / 100));
    }

    /**
     * Get effective yearly price (with discount or override)
     */
    public function getEffectivePriceYearly(): int
    {
        // Use override if set
        if ($this->price_yearly !== null) {
            return $this->price_yearly;
        }

        // Calculate with discount
        $base = $this->getBasePriceYearly();

        return (int) round($base * (1 - $this->discount_percent / 100));
    }

    /**
     * Get savings amount (monthly)
     */
    public function getSavingsMonthly(): int
    {
        return $this->getBasePriceMonthly() - $this->getEffectivePriceMonthly();
    }

    /**
     * Get savings amount (yearly)
     */
    public function getSavingsYearly(): int
    {
        return $this->getBasePriceYearly() - $this->getEffectivePriceYearly();
    }

    /*
    |--------------------------------------------------------------------------
    | Attribute Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Formatted effective monthly price
     */
    public function getFormattedPriceMonthlyAttribute(): string
    {
        return format_stripe_price($this->getEffectivePriceMonthly(), $this->currency);
    }

    /**
     * Formatted effective yearly price
     */
    public function getFormattedPriceYearlyAttribute(): string
    {
        return format_stripe_price($this->getEffectivePriceYearly(), $this->currency);
    }

    /**
     * Formatted savings (monthly)
     */
    public function getFormattedSavingsMonthlyAttribute(): string
    {
        return format_stripe_price($this->getSavingsMonthly(), $this->currency);
    }

    /**
     * Count of addons in bundle
     */
    public function getAddonCountAttribute(): int
    {
        return $this->addons->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if bundle is available for a plan
     */
    public function isAvailableForPlan(Plan|string $plan): bool
    {
        $planId = $plan instanceof Plan ? $plan->id : $plan;

        return $this->plans()
            ->where('plans.id', $planId)
            ->wherePivot('active', true)
            ->exists();
    }

    /**
     * Get all addon slugs in this bundle
     */
    public function getAddonSlugs(): array
    {
        return $this->addons->pluck('slug')->toArray();
    }

    /**
     * Get items with quantities for purchase
     */
    public function getItemsForPurchase(): array
    {
        return $this->addons->map(function ($addon) {
            return [
                'addon' => $addon,
                'slug' => $addon->slug,
                'quantity' => $addon->pivot->quantity ?? 1,
                'billing_period' => $addon->pivot->billing_period,
            ];
        })->toArray();
    }

    /**
     * Convert to frontend array
     */
    public function toFrontend(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->getTranslation('name', $locale),
            'description' => $this->getTranslation('description', $locale),
            'discount_percent' => $this->discount_percent,
            'price_monthly' => $this->getEffectivePriceMonthly(),
            'price_yearly' => $this->getEffectivePriceYearly(),
            'formatted_price_monthly' => $this->formatted_price_monthly,
            'formatted_price_yearly' => $this->formatted_price_yearly,
            'base_price_monthly' => $this->getBasePriceMonthly(),
            'savings_monthly' => $this->getSavingsMonthly(),
            'formatted_savings_monthly' => $this->formatted_savings_monthly,
            'badge' => $this->badge,
            'icon' => $this->icon ?? 'Package',
            'features' => $this->getTranslatedFeatures($locale),
            'addon_count' => $this->addon_count,
            'addons' => $this->addons->map(fn ($addon) => [
                'slug' => $addon->slug,
                'name' => $addon->getTranslation('name', $locale),
                'type' => $addon->type->value,
                'quantity' => $addon->pivot->quantity ?? 1,
            ])->toArray(),
        ];
    }

    /**
     * Get features translated for a specific locale.
     * Features are stored as array of translation objects: [{en: "...", pt_BR: "..."}, ...]
     */
    public function getTranslatedFeatures(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $features = $this->features ?? [];

        return collect($features)->map(function ($feature) use ($locale) {
            // If feature is already a string (legacy format), return as-is
            if (is_string($feature)) {
                return $feature;
            }

            // If feature is an array of translations, get the correct locale
            if (is_array($feature)) {
                return $feature[$locale] ?? $feature['en'] ?? array_values($feature)[0] ?? '';
            }

            return '';
        })->filter()->values()->toArray();
    }
}
