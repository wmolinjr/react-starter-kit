<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * UsageRecord Model
 *
 * Tracks metered usage for tenants (storage, bandwidth, API calls, etc.)
 * Used for billing, historical data, and auditing purposes.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $addon_id
 * @property string $usage_type
 * @property int $quantity
 * @property string $unit
 * @property int|null $plan_limit
 * @property int $overage
 * @property int $unit_price
 * @property int $total_cost
 * @property bool $reported_to_provider
 * @property \Carbon\Carbon|null $reported_at
 * @property string|null $provider_reference_id
 * @property \Carbon\Carbon $billing_period_start
 * @property \Carbon\Carbon $billing_period_end
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Addon|null $addon
 */
class UsageRecord extends Model
{
    use CentralConnection, HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'addon_id',
        'usage_type',
        'quantity',
        'unit',
        'plan_limit',
        'overage',
        'unit_price',
        'total_cost',
        'reported_to_provider',
        'reported_at',
        'provider_reference_id',
        'billing_period_start',
        'billing_period_end',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'plan_limit' => 'integer',
        'overage' => 'integer',
        'unit_price' => 'integer',
        'total_cost' => 'integer',
        'reported_to_provider' => 'boolean',
        'reported_at' => 'datetime',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'metadata' => 'array',
    ];

    /**
     * Get the tenant that owns this usage record.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the addon associated with this usage record.
     */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    /**
     * Mark this record as reported to the billing provider.
     */
    public function markAsReported(?string $referenceId = null): void
    {
        $this->update([
            'reported_to_provider' => true,
            'reported_at' => now(),
            'provider_reference_id' => $referenceId,
        ]);
    }

    /**
     * Calculate the total cost based on overage and unit price.
     */
    public function calculateCost(): int
    {
        if ($this->unit === 'MB') {
            // Convert MB overage to GB for pricing
            $overageGB = $this->overage / 1024;

            return (int) round($overageGB * $this->unit_price);
        }

        return $this->overage * $this->unit_price;
    }

    /**
     * Get formatted quantity with unit.
     */
    public function getFormattedQuantityAttribute(): string
    {
        return number_format($this->quantity).' '.$this->unit;
    }

    /**
     * Get formatted overage with unit.
     */
    public function getFormattedOverageAttribute(): string
    {
        return number_format($this->overage).' '.$this->unit;
    }

    /**
     * Get formatted cost.
     */
    public function getFormattedCostAttribute(): string
    {
        return 'R$'.number_format($this->total_cost / 100, 2, ',', '.');
    }

    /**
     * Scope for unreported records.
     */
    public function scopeUnreported($query)
    {
        return $query->where('reported_to_provider', false);
    }

    /**
     * Scope for a specific billing period.
     */
    public function scopeForBillingPeriod($query, $start, $end)
    {
        return $query->where('billing_period_start', '>=', $start)
            ->where('billing_period_end', '<=', $end);
    }

    /**
     * Scope for a specific usage type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('usage_type', $type);
    }

    /**
     * Scope for records with overage.
     */
    public function scopeWithOverage($query)
    {
        return $query->where('overage', '>', 0);
    }
}
