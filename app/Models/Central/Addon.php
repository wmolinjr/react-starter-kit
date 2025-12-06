<?php

namespace App\Models\Central;

use App\Enums\AddonType;
use App\Traits\HasTenantTranslations;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Addon Model
 *
 * Represents purchasable add-ons for plans.
 * Supports multi-language translations with tenant customization.
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
    use CentralConnection, HasFactory, HasUuids;
    use HasTenantTranslations;

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
        'stripe_product_id',
        'stripe_price_monthly_id',
        'stripe_price_yearly_id',
        'stripe_price_one_time_id',
        'stripe_price_metered_id',
        'stripe_meter_id',
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
     * Get Stripe price ID for billing period
     */
    public function getStripePriceId(string $billingPeriod): ?string
    {
        return match ($billingPeriod) {
            'monthly' => $this->stripe_price_monthly_id,
            'yearly' => $this->stripe_price_yearly_id,
            'one_time' => $this->stripe_price_one_time_id,
            'metered' => $this->stripe_price_metered_id,
            default => null,
        };
    }

    /**
     * Check if this is a metered (usage-based) addon
     */
    public function isMetered(): bool
    {
        return $this->price_metered !== null && $this->stripe_meter_id !== null;
    }

    /**
     * Scope to get metered addons
     */
    public function scopeMetered($query)
    {
        return $query->whereNotNull('price_metered')
            ->whereNotNull('stripe_meter_id');
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

    /**
     * Get addon data for API/frontend with translations.
     */
    public function toTranslatedArray(?int $tenantId = null): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->trans('name'),
            'description' => $this->trans('description'),
            'unit_label' => $this->trans('unit_label'),
            'type' => $this->type,
            'category' => $this->category,
            'active' => $this->active,
            'sort_order' => $this->sort_order,
            'limit_key' => $this->limit_key,
            'unit_value' => $this->unit_value,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'stackable' => $this->stackable,
            'price_monthly' => $this->price_monthly,
            'price_yearly' => $this->price_yearly,
            'price_one_time' => $this->price_one_time,
            'badge' => $this->badge,
            'has_override' => $tenantId ? $this->hasTenantOverride('name', $tenantId) : false,
        ];
    }
}
