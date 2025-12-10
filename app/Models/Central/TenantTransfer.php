<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * TenantTransfer Model
 *
 * Tracks tenant ownership transfers between customers.
 *
 * FLOW:
 * 1. Owner initiates transfer (status: pending)
 * 2. Recipient receives email with token
 * 3. Recipient accepts or rejects
 * 4. If accepted, ownership changes (status: completed)
 *
 * @property string $id UUID primary key
 * @property string $tenant_id
 * @property string $from_customer_id
 * @property string|null $to_customer_id
 * @property string $to_email
 * @property float $transfer_fee
 * @property string $transfer_fee_currency
 * @property float $remaining_subscription_value
 * @property string $token
 * @property \Carbon\Carbon $expires_at
 * @property string $status
 * @property string|null $notes
 * @property \Carbon\Carbon|null $accepted_at
 * @property \Carbon\Carbon|null $completed_at
 */
class TenantTransfer extends Model
{
    use CentralConnection;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'from_customer_id',
        'to_customer_id',
        'to_email',
        'transfer_fee',
        'transfer_fee_currency',
        'remaining_subscription_value',
        'token',
        'expires_at',
        'status',
        'notes',
        'accepted_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'transfer_fee' => 'decimal:2',
            'remaining_subscription_value' => 'decimal:2',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function fromCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'from_customer_id');
    }

    public function toCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'to_customer_id');
    }

    // =========================================================================
    // Status Helpers
    // =========================================================================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
               ($this->isPending() && $this->expires_at->isPast());
    }

    public function canBeAccepted(): bool
    {
        return $this->isPending() && !$this->isExpired();
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending();
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '<=', now());
    }

    // =========================================================================
    // Boot
    // =========================================================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $transfer) {
            $transfer->token = $transfer->token ?? Str::random(64);
            $transfer->expires_at = $transfer->expires_at ?? now()->addDays(7);
        });
    }
}
