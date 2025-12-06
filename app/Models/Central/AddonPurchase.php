<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * AddonPurchase Model
 *
 * Stores one-time purchase history for add-ons in central database.
 * Renamed from TenantAddonPurchase for clarity - this is a central table, not a tenant table.
 */
class AddonPurchase extends Model
{
    use CentralConnection, HasFactory, HasUuids, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Database\Factories\AddonPurchaseFactory
    {
        return \Database\Factories\AddonPurchaseFactory::new();
    }

    protected $table = 'addon_purchases';

    protected $fillable = [
        'tenant_id',
        'addon_subscription_id',
        'addon_slug',
        'addon_type',
        'quantity',
        'amount_paid',
        'currency',
        'payment_method',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'stripe_invoice_id',
        'status',
        'purchased_at',
        'refunded_at',
        'valid_from',
        'valid_until',
        'is_consumed',
        'metadata',
        'failure_reason',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'amount_paid' => 'integer',
        'purchased_at' => 'datetime',
        'refunded_at' => 'datetime',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_consumed' => 'boolean',
        'metadata' => 'array',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * Get the tenant that owns this purchase.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the subscription associated with this purchase.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(AddonSubscription::class, 'addon_subscription_id');
    }

    // ==========================================
    // Status Checks
    // ==========================================

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isValid(): bool
    {
        $now = now();

        if ($this->valid_from && $now->isBefore($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $now->isAfter($this->valid_until)) {
            return false;
        }

        return true;
    }

    // ==========================================
    // Pricing Attributes
    // ==========================================

    public function getFormattedAmountAttribute(): string
    {
        return format_stripe_price($this->amount_paid, $this->currency);
    }

    // ==========================================
    // Actions
    // ==========================================

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'purchased_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    public function refund(): void
    {
        $this->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);
    }

    public function consume(): void
    {
        $this->update(['is_consumed' => true]);
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeValid($query)
    {
        $now = now();

        return $query->where(function ($q) use ($now) {
            $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('valid_until')->orWhere('valid_until', '>', $now);
        });
    }

    public function scopeUnconsumed($query)
    {
        return $query->where('is_consumed', false);
    }
}
