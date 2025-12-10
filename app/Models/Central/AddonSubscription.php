<?php

namespace App\Models\Central;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
use App\Enums\BillingPeriod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * AddonSubscription Model
 *
 * Stores active add-on subscriptions for tenants in central database.
 * Renamed from TenantAddon for clarity - this is a central table, not a tenant table.
 */
class AddonSubscription extends Model
{
    use CentralConnection, HasFactory, HasUuids, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Database\Factories\AddonSubscriptionFactory
    {
        return \Database\Factories\AddonSubscriptionFactory::new();
    }

    protected $table = 'addon_subscriptions';

    protected $fillable = [
        'tenant_id',
        'addon_slug',
        'addon_type',
        'name',
        'description',
        'quantity',
        'price',
        'currency',
        'billing_period',
        'status',
        'started_at',
        'expires_at',
        'canceled_at',
        'provider',
        'provider_item_id',
        'provider_price_id',
        'metered_usage',
        'metered_reset_at',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'integer',
        'metered_usage' => 'integer',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'canceled_at' => 'datetime',
        'metered_reset_at' => 'datetime',
        'metadata' => 'array',
        'addon_type' => AddonType::class,
        'status' => AddonStatus::class,
        'billing_period' => BillingPeriod::class,
    ];

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * Get the tenant that owns this addon subscription.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get purchases associated with this subscription.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(AddonPurchase::class);
    }

    // ==========================================
    // Status Checks
    // ==========================================

    public function isActive(): bool
    {
        return $this->status === AddonStatus::ACTIVE
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isCanceled(): bool
    {
        return $this->status === AddonStatus::CANCELED;
    }

    public function isRecurring(): bool
    {
        return in_array($this->billing_period, [
            BillingPeriod::MONTHLY,
            BillingPeriod::YEARLY,
        ]);
    }

    public function isMetered(): bool
    {
        return $this->billing_period === BillingPeriod::METERED;
    }

    public function isOneTime(): bool
    {
        return $this->billing_period === BillingPeriod::ONE_TIME;
    }

    public function isManual(): bool
    {
        return $this->billing_period === BillingPeriod::MANUAL;
    }

    // ==========================================
    // Pricing Attributes
    // ==========================================

    public function getFormattedPriceAttribute(): string
    {
        return format_stripe_price($this->price, $this->currency);
    }

    public function getTotalPriceAttribute(): int
    {
        return $this->price * $this->quantity;
    }

    public function getFormattedTotalPriceAttribute(): string
    {
        return format_stripe_price($this->total_price, $this->currency);
    }

    // ==========================================
    // Actions
    // ==========================================

    /**
     * Mark this addon as canceled.
     *
     * Note: Provider subscription item removal should be handled by AddonService,
     * not by the model directly. This method only updates the local record.
     */
    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status' => AddonStatus::CANCELED,
            'canceled_at' => now(),
            'notes' => $reason ? "Canceled: {$reason}" : $this->notes,
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => AddonStatus::EXPIRED]);
    }

    public function activate(): void
    {
        $this->update([
            'status' => AddonStatus::ACTIVE,
            'started_at' => now(),
            'canceled_at' => null,
        ]);
    }

    // ==========================================
    // Metered Usage
    // ==========================================

    public function incrementMeteredUsage(int $amount = 1): void
    {
        $this->increment('metered_usage', $amount);
    }

    public function resetMeteredUsage(): void
    {
        $this->update([
            'metered_usage' => 0,
            'metered_reset_at' => now(),
        ]);
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('status', AddonStatus::ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
            ->where('status', '!=', AddonStatus::EXPIRED);
    }

    public function scopeRecurring($query)
    {
        return $query->whereIn('billing_period', [
            BillingPeriod::MONTHLY->value,
            BillingPeriod::YEARLY->value,
        ]);
    }

    public function scopeMetered($query)
    {
        return $query->where('billing_period', BillingPeriod::METERED);
    }

    public function scopeByType($query, AddonType|string $type)
    {
        $type = $type instanceof AddonType ? $type->value : $type;

        return $query->where('addon_type', $type);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('addon_slug', $slug);
    }
}
