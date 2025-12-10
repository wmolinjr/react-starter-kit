<?php

declare(strict_types=1);

namespace App\Models\Central;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Subscription Model - Provider-Agnostic
 *
 * Represents a recurring billing subscription that can be managed
 * by any payment provider (Stripe, Asaas, PagSeguro, etc.).
 *
 * @property string $id UUID primary key
 * @property string $customer_id
 * @property string|null $tenant_id
 * @property string $provider Payment provider (stripe, asaas, etc.)
 * @property string|null $provider_subscription_id ID in the provider
 * @property string|null $provider_customer_id Customer ID in the provider
 * @property string|null $provider_price_id Price/Plan ID in the provider
 * @property string $type Subscription type (default, addon, etc.)
 * @property string $status Status (active, canceled, past_due, trialing, paused)
 * @property int $quantity
 * @property string $billing_period monthly or yearly
 * @property int $amount Amount in cents
 * @property string $currency 3-letter currency code
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $canceled_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $paused_at
 * @property array|null $provider_data Provider-specific data
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Subscription extends Model
{
    use CentralConnection;
    use HasFactory;
    use HasUuids;

    protected $table = 'subscriptions';

    protected $fillable = [
        'customer_id',
        'tenant_id',
        'provider',
        'provider_subscription_id',
        'provider_customer_id',
        'provider_price_id',
        'type',
        'status',
        'quantity',
        'billing_period',
        'amount',
        'currency',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'ends_at',
        'paused_at',
        'provider_data',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount' => 'integer',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'ends_at' => 'datetime',
            'paused_at' => 'datetime',
            'provider_data' => 'array',
            'metadata' => 'array',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * Get the customer that owns this subscription.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the tenant this subscription is for.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get all payments for this subscription.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payable_id')
            ->where('payable_type', 'subscription');
    }

    /**
     * Get subscription items (for multi-item subscriptions).
     */
    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    // =========================================================================
    // Status Checks
    // =========================================================================

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }

    /**
     * Check if subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->status === 'trialing'
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if subscription has been canceled.
     */
    public function canceled(): bool
    {
        return $this->canceled_at !== null;
    }

    /**
     * Check if subscription is on grace period (canceled but not ended).
     */
    public function onGracePeriod(): bool
    {
        return $this->canceled()
            && $this->ends_at !== null
            && $this->ends_at->isFuture();
    }

    /**
     * Check if subscription has ended.
     */
    public function ended(): bool
    {
        return $this->canceled()
            && $this->ends_at !== null
            && $this->ends_at->isPast();
    }

    /**
     * Check if subscription is past due.
     */
    public function pastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Check if subscription is paused.
     */
    public function paused(): bool
    {
        return $this->status === 'paused' && $this->paused_at !== null;
    }

    /**
     * Check if subscription is incomplete.
     */
    public function incomplete(): bool
    {
        return $this->status === 'incomplete';
    }

    // =========================================================================
    // Provider Helpers
    // =========================================================================

    /**
     * Check if subscription is from a specific provider.
     */
    public function isFromProvider(string $provider): bool
    {
        return $this->provider === $provider;
    }

    /**
     * Get provider-specific data value.
     */
    public function getProviderData(string $key, mixed $default = null): mixed
    {
        return data_get($this->provider_data, $key, $default);
    }

    /**
     * Set provider-specific data value.
     */
    public function setProviderData(string $key, mixed $value): void
    {
        $data = $this->provider_data ?? [];
        data_set($data, $key, $value);
        $this->update(['provider_data' => $data]);
    }

    // =========================================================================
    // Actions
    // =========================================================================

    /**
     * Mark subscription as canceled.
     */
    public function markAsCanceled(?Carbon $endsAt = null): void
    {
        $this->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'ends_at' => $endsAt ?? $this->current_period_end,
        ]);
    }

    /**
     * Mark subscription as active.
     */
    public function markAsActive(): void
    {
        $this->update([
            'status' => 'active',
            'canceled_at' => null,
            'ends_at' => null,
            'paused_at' => null,
        ]);
    }

    /**
     * Mark subscription as paused.
     */
    public function markAsPaused(): void
    {
        $this->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);
    }

    /**
     * Resume a paused subscription.
     */
    public function resume(): void
    {
        $this->update([
            'status' => 'active',
            'paused_at' => null,
        ]);
    }

    /**
     * Update billing period dates.
     */
    public function updatePeriod(Carbon $start, Carbon $end): void
    {
        $this->update([
            'current_period_start' => $start,
            'current_period_end' => $end,
        ]);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Scope to active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'trialing']);
    }

    /**
     * Scope to subscriptions by provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to subscriptions by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
