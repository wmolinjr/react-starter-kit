<?php

namespace App\Models\Central;

use App\Enums\BusinessSector;
use Database\Factories\PendingSignupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * PendingSignup Model
 *
 * Customer-First Flow: Stores workspace/payment data while waiting for payment.
 * Customer is created FIRST (Step 1), then workspace configured, then payment.
 *
 * Status Flow:
 * - pending: Initial state, Customer created, collecting workspace data
 * - processing: Payment initiated, waiting for confirmation
 * - completed: Payment confirmed, Tenant created
 * - failed: Payment failed or declined
 * - expired: Signup expired (no payment within 24h)
 *
 * @property string $id UUID primary key
 * @property string $customer_id Customer ID (required - created in Step 1)
 * @property string|null $workspace_name
 * @property string|null $workspace_slug
 * @property string|null $business_sector
 * @property string|null $plan_id
 * @property string $billing_period monthly|yearly
 * @property string|null $payment_method card|pix|boleto
 * @property string $payment_provider stripe|asaas
 * @property string|null $provider_session_id Stripe checkout session ID
 * @property string|null $provider_payment_id PIX/Boleto payment ID
 * @property string $status pending|processing|completed|failed|expired
 * @property string|null $tenant_id Created tenant ID (after payment)
 * @property string|null $failure_reason
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon|null $paid_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string $email Via customer relationship
 * @property-read string $name Via customer relationship
 * @property-read Customer $customer
 * @property-read Tenant|null $tenant
 * @property-read Plan|null $plan
 */
class PendingSignup extends Model
{
    use CentralConnection;

    /** @use HasFactory<PendingSignupFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'pending_signups';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PendingSignupFactory
    {
        return PendingSignupFactory::new();
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const PAYMENT_METHOD_CARD = 'card';

    public const PAYMENT_METHOD_PIX = 'pix';

    public const PAYMENT_METHOD_BOLETO = 'boleto';

    public const PROVIDER_STRIPE = 'stripe';

    public const PROVIDER_ASAAS = 'asaas';

    protected $fillable = [
        'customer_id',
        'workspace_name',
        'workspace_slug',
        'business_sector',
        'plan_id',
        'billing_period',
        'payment_method',
        'payment_provider',
        'provider_session_id',
        'provider_payment_id',
        'status',
        'tenant_id',
        'failure_reason',
        'metadata',
        'expires_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'business_sector' => BusinessSector::class,
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Scope to pending signups.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to processing signups.
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Scope to completed signups.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to expired signups.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_EXPIRED)
            ->orWhere(function ($q) {
                $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING])
                    ->where('expires_at', '<', now());
            });
    }

    /**
     * Scope to find by provider session ID.
     */
    public function scopeBySession(Builder $query, string $sessionId, string $provider = 'stripe'): Builder
    {
        return $query->where('provider_session_id', $sessionId)
            ->where('payment_provider', $provider);
    }

    /**
     * Scope to find by provider payment ID.
     */
    public function scopeByPaymentId(Builder $query, string $paymentId, string $provider = 'stripe'): Builder
    {
        return $query->where('provider_payment_id', $paymentId)
            ->where('payment_provider', $provider);
    }

    // =========================================================================
    // Status Helpers
    // =========================================================================

    /**
     * Check if signup is expired.
     */
    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * Check if signup is pending payment.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if signup is processing payment.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if signup is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if signup has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if workspace data is complete.
     */
    public function hasWorkspaceData(): bool
    {
        return ! empty($this->workspace_name)
            && ! empty($this->workspace_slug)
            && ! empty($this->plan_id);
    }

    // =========================================================================
    // Status Transitions
    // =========================================================================

    /**
     * Mark signup as processing (payment initiated).
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
        ]);
    }

    /**
     * Mark signup as paid (before creating customer/tenant).
     */
    public function markAsPaid(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark signup as completed (after tenant created).
     *
     * Customer-First: Customer already exists, only tenant_id is set here.
     */
    public function markAsCompleted(string $tenantId): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'tenant_id' => $tenantId,
            'paid_at' => $this->paid_at ?? now(),
        ]);
    }

    /**
     * Mark signup as failed.
     */
    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Mark signup as expired.
     */
    public function markAsExpired(): void
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
        ]);
    }

    // =========================================================================
    // Payment Helpers
    // =========================================================================

    /**
     * Set payment session (for Stripe Checkout).
     */
    public function setPaymentSession(string $sessionId, string $provider = 'stripe'): void
    {
        $this->update([
            'provider_session_id' => $sessionId,
            'payment_provider' => $provider,
            'status' => self::STATUS_PROCESSING,
        ]);
    }

    /**
     * Set payment ID (for PIX/Boleto).
     */
    public function setPaymentId(string $paymentId, string $provider = 'stripe'): void
    {
        $this->update([
            'provider_payment_id' => $paymentId,
            'payment_provider' => $provider,
            'status' => self::STATUS_PROCESSING,
        ]);
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    /**
     * Get email from customer (customer-first flow).
     */
    public function getEmailAttribute(): string
    {
        return $this->customer->email;
    }

    /**
     * Get name from customer (customer-first flow).
     */
    public function getNameAttribute(): string
    {
        return $this->customer->name;
    }

    /**
     * Get locale from customer (customer-first flow).
     */
    public function getLocaleAttribute(): string
    {
        return $this->customer->locale ?? config('app.locale', 'pt_BR');
    }

    /**
     * Get tenant URL after completion.
     */
    public function getTenantUrlAttribute(): ?string
    {
        if (! $this->tenant) {
            return null;
        }

        return $this->tenant->route('tenant.home');
    }
}
