<?php

declare(strict_types=1);

namespace App\Models\Central;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * SubscriptionItem Model - Provider-Agnostic
 *
 * Represents a line item in a subscription (e.g., base plan, add-on).
 * Supports metered billing for usage-based pricing.
 *
 * @property string $id UUID primary key
 * @property string $subscription_id
 * @property string|null $provider_item_id ID in the provider
 * @property string $provider_price_id Price ID in the provider
 * @property string|null $provider_product_id Product ID in the provider
 * @property int $quantity
 * @property string|null $meter_id For metered billing
 * @property string|null $meter_event_name For metered billing
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SubscriptionItem extends Model
{
    use CentralConnection;
    use HasFactory;
    use HasUuids;

    protected $table = 'subscription_items';

    protected $fillable = [
        'subscription_id',
        'provider_item_id',
        'provider_price_id',
        'provider_product_id',
        'quantity',
        'meter_id',
        'meter_event_name',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * Get the subscription this item belongs to.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    // =========================================================================
    // Metered Billing
    // =========================================================================

    /**
     * Check if this item uses metered billing.
     */
    public function isMetered(): bool
    {
        return $this->meter_id !== null;
    }

    /**
     * Get the meter ID for usage reporting.
     */
    public function getMeterId(): ?string
    {
        return $this->meter_id;
    }

    /**
     * Get the meter event name for usage reporting.
     */
    public function getMeterEventName(): ?string
    {
        return $this->meter_event_name;
    }

    // =========================================================================
    // Quantity Management
    // =========================================================================

    /**
     * Update the quantity.
     */
    public function updateQuantity(int $quantity): void
    {
        $this->update(['quantity' => $quantity]);
    }

    /**
     * Increment the quantity.
     */
    public function incrementQuantity(int $amount = 1): void
    {
        $this->increment('quantity', $amount);
    }

    /**
     * Decrement the quantity.
     */
    public function decrementQuantity(int $amount = 1): void
    {
        $this->decrement('quantity', max(0, $amount));
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Scope to items with metered billing.
     */
    public function scopeMetered($query)
    {
        return $query->whereNotNull('meter_id');
    }

    /**
     * Scope by price ID.
     */
    public function scopeByPriceId($query, string $priceId)
    {
        return $query->where('provider_price_id', $priceId);
    }
}
