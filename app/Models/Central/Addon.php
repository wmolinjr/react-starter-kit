<?php

namespace App\Models\Central;

use App\Enums\AddonType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Addon Model
 *
 * Represents purchasable add-ons for plans.
 * Supports multi-language translations via Spatie Translatable.
 *
 * @property string $id
 * @property string $slug
 * @property array $name
 * @property array|null $description
 * @property array|null $unit_label
 * @property string $type
 * @property string $category
 * @property bool $active
 * @property int $sort_order
 */
class Addon extends Model
{
    use CentralConnection, HasFactory, HasTranslations, HasUuids;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Database\Factories\AddonFactory
    {
        return \Database\Factories\AddonFactory::new();
    }

    /**
     * Fields that support translations (Spatie Translatable).
     */
    public array $translatable = [
        'name',
        'description',
        'unit_label',
    ];

    protected $fillable = [
        'slug',
        'name',
        'description',
        'type',
        'active',
        'sort_order',
        'limit_key', // For QUOTA type: which plan limit to increase
        'unit_value',
        'unit_label',
        'min_quantity',
        'max_quantity',
        'stackable',
        'price_monthly',
        'price_yearly',
        'price_one_time',
        'price_metered',
        'currency',
        'free_tier',
        'validity_months',
        'provider_product_ids',
        'provider_price_ids',
        'provider_meter_ids',
        'features',
        'icon',
        'icon_color',
        'badge',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => AddonType::class,
            'active' => 'boolean',
            'stackable' => 'boolean',
            'provider_product_ids' => 'array',
            'provider_price_ids' => 'array',
            'provider_meter_ids' => 'array',
            'features' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Plans that have access to this addon
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'addon_plan')
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
     * Bundles that include this addon
     */
    public function bundles(): BelongsToMany
    {
        return $this->belongsToMany(AddonBundle::class, 'addon_bundle_items', 'addon_id', 'bundle_id')
            ->withPivot(['quantity', 'billing_period', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * Get price for a specific plan and billing period
     */
    public function getPriceForPlan(Plan $plan, string $billingPeriod): int
    {
        $pivot = $this->plans()->where('plan_id', $plan->id)->first()?->pivot;

        $priceField = match ($billingPeriod) {
            'monthly' => 'price_monthly',
            'yearly' => 'price_yearly',
            'one_time' => 'price_one_time',
            default => 'price_monthly',
        };

        $overrideField = "price_override_{$billingPeriod}";
        $basePrice = $this->{$priceField} ?? 0;

        if (! $pivot) {
            return $basePrice;
        }

        // If included in plan, it's free
        if ($pivot->included) {
            return 0;
        }

        // Check for price override
        if ($pivot->{$overrideField}) {
            return $pivot->{$overrideField};
        }

        // Apply discount if set
        if ($pivot->discount_percent) {
            return (int) ($basePrice * (100 - $pivot->discount_percent) / 100);
        }

        return $basePrice;
    }

    /**
     * Check if addon is available for a plan
     */
    public function isAvailableForPlan(Plan $plan): bool
    {
        return $this->plans()
            ->where('plan_id', $plan->id)
            ->wherePivot('active', true)
            ->exists();
    }

    /**
     * Check if addon is included free in a plan
     */
    public function isIncludedInPlan(Plan $plan): bool
    {
        return $this->plans()
            ->where('plan_id', $plan->id)
            ->wherePivot('included', true)
            ->exists();
    }

    /**
     * Scope to get addons available for a specific plan
     */
    public function scopeAvailableFor($query, Plan $plan)
    {
        return $query->where('active', true)
            ->whereHas('plans', function ($q) use ($plan) {
                $q->where('plan_id', $plan->id)
                    ->where('addon_plan.active', true);
            });
    }

    /**
     * Scope to get active addons
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope by category (filters by types that belong to the category)
     */
    public function scopeCategory($query, string $category)
    {
        $types = collect(AddonType::cases())
            ->filter(fn (AddonType $type) => $type->category() === $category)
            ->map(fn (AddonType $type) => $type->value)
            ->values()
            ->toArray();

        return $query->whereIn('type', $types);
    }

    /**
     * Scope by type
     */
    public function scopeOfType($query, AddonType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get formatted price for display
     */
    public function getFormattedPrice(string $billingPeriod): string
    {
        $priceField = match ($billingPeriod) {
            'monthly' => 'price_monthly',
            'yearly' => 'price_yearly',
            'one_time' => 'price_one_time',
            default => 'price_monthly',
        };

        $price = $this->{$priceField};

        if (! $price) {
            return 'N/A';
        }

        return format_stripe_price($price, $this->currency);
    }

    /**
     * Check if addon supports a billing period
     */
    public function supportsBillingPeriod(string $period): bool
    {
        return match ($period) {
            'monthly' => $this->price_monthly !== null,
            'yearly' => $this->price_yearly !== null,
            'one_time' => $this->price_one_time !== null,
            'metered' => $this->price_metered !== null,
            default => false,
        };
    }

    /**
     * Get provider product ID.
     */
    public function getProviderProductId(string $provider): ?string
    {
        return $this->provider_product_ids[$provider] ?? null;
    }

    /**
     * Set provider product ID.
     */
    public function setProviderProductId(string $provider, string $productId): void
    {
        $this->update([
            'provider_product_ids' => array_merge($this->provider_product_ids ?? [], [$provider => $productId]),
        ]);
    }

    /**
     * Get provider price ID for billing period.
     */
    public function getProviderPriceId(string $provider, string $billingPeriod): ?string
    {
        return $this->provider_price_ids[$provider][$billingPeriod] ?? null;
    }

    /**
     * Set provider price ID for billing period.
     */
    public function setProviderPriceId(string $provider, string $billingPeriod, string $priceId): void
    {
        $priceIds = $this->provider_price_ids ?? [];
        if (! isset($priceIds[$provider])) {
            $priceIds[$provider] = [];
        }
        $priceIds[$provider][$billingPeriod] = $priceId;

        $this->update(['provider_price_ids' => $priceIds]);
    }

    /**
     * Get provider meter ID.
     */
    public function getProviderMeterId(string $provider): ?string
    {
        return $this->provider_meter_ids[$provider] ?? null;
    }

    /**
     * Set provider meter ID.
     */
    public function setProviderMeterId(string $provider, string $meterId): void
    {
        $this->update([
            'provider_meter_ids' => array_merge($this->provider_meter_ids ?? [], [$provider => $meterId]),
        ]);
    }

    /**
     * Check if this is a metered (usage-based) addon.
     */
    public function isMetered(): bool
    {
        return $this->price_metered !== null && ! empty($this->provider_meter_ids);
    }

    /**
     * Scope to get metered addons.
     */
    public function scopeMetered($query)
    {
        return $query->whereNotNull('price_metered')
            ->whereNotNull('provider_meter_ids');
    }

    /**
     * Check if this addon increases a plan limit.
     */
    public function increasesLimit(): bool
    {
        return $this->limit_key !== null;
    }

    /**
     * Get the category from the addon type.
     * Derived from AddonType enum (single source of truth).
     */
    public function getCategoryAttribute(): string
    {
        return $this->type?->category() ?? 'general';
    }
}
