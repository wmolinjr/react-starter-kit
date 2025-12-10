<?php

declare(strict_types=1);

namespace App\Models\Central;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * PaymentMethod Model - Provider-Agnostic
 *
 * Stores payment method details for cards, PIX, boleto, bank transfers, etc.
 * Can be managed by any payment provider (Stripe, Asaas, PagSeguro, etc.).
 *
 * @property string $id UUID primary key
 * @property string $customer_id
 * @property string $provider Payment provider (stripe, asaas, etc.)
 * @property string|null $provider_method_id ID in the provider
 * @property string $type Type (card, pix, boleto, bank_transfer)
 * @property string|null $brand Card brand (visa, mastercard, etc.)
 * @property string|null $last4 Last 4 digits of card
 * @property int|null $exp_month Card expiration month
 * @property int|null $exp_year Card expiration year
 * @property string|null $bank_name Bank name for bank transfers
 * @property array|null $details Additional encrypted details
 * @property bool $is_default Whether this is the default payment method
 * @property bool $is_verified Whether the payment method is verified
 * @property Carbon|null $verified_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class PaymentMethod extends Model
{
    use CentralConnection;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'payment_methods';

    protected $fillable = [
        'customer_id',
        'provider',
        'provider_method_id',
        'type',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'bank_name',
        'details',
        'is_default',
        'is_verified',
        'verified_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'exp_month' => 'integer',
            'exp_year' => 'integer',
            'details' => 'encrypted:array',
            'is_default' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * Get the customer that owns this payment method.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all payments made with this payment method.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // =========================================================================
    // Type Checks
    // =========================================================================

    /**
     * Check if this is a card payment method.
     */
    public function isCard(): bool
    {
        return $this->type === 'card';
    }

    /**
     * Check if this is a PIX payment method.
     */
    public function isPix(): bool
    {
        return $this->type === 'pix';
    }

    /**
     * Check if this is a boleto payment method.
     */
    public function isBoleto(): bool
    {
        return $this->type === 'boleto';
    }

    /**
     * Check if this is a bank transfer payment method.
     */
    public function isBankTransfer(): bool
    {
        return $this->type === 'bank_transfer';
    }

    // =========================================================================
    // Status Checks
    // =========================================================================

    /**
     * Check if payment method is expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at !== null) {
            return $this->expires_at->isPast();
        }

        // For cards, check expiration date
        if ($this->isCard() && $this->exp_month && $this->exp_year) {
            $expiry = Carbon::createFromDate($this->exp_year, $this->exp_month, 1)
                ->endOfMonth()
                ->endOfDay();

            return $expiry->isPast();
        }

        return false;
    }

    /**
     * Check if payment method is usable.
     */
    public function isUsable(): bool
    {
        return ! $this->isExpired() && $this->deleted_at === null;
    }

    // =========================================================================
    // Display Helpers
    // =========================================================================

    /**
     * Get a display label for the payment method.
     */
    public function getDisplayLabel(): string
    {
        return match ($this->type) {
            'card' => $this->getCardLabel(),
            'pix' => 'PIX',
            'boleto' => 'Boleto Bancário',
            'bank_transfer' => $this->bank_name ?? 'Transferência Bancária',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get card display label.
     */
    protected function getCardLabel(): string
    {
        $brand = $this->brand ? ucfirst($this->brand) : 'Card';
        $last4 = $this->last4 ? " •••• {$this->last4}" : '';

        return "{$brand}{$last4}";
    }

    /**
     * Get expiration display string for cards.
     */
    public function getExpirationDisplay(): ?string
    {
        if (! $this->isCard() || ! $this->exp_month || ! $this->exp_year) {
            return null;
        }

        $month = str_pad((string) $this->exp_month, 2, '0', STR_PAD_LEFT);
        $year = substr((string) $this->exp_year, -2);

        return "{$month}/{$year}";
    }

    // =========================================================================
    // Provider Helpers
    // =========================================================================

    /**
     * Check if payment method is from a specific provider.
     */
    public function isFromProvider(string $provider): bool
    {
        return $this->provider === $provider;
    }

    /**
     * Get detail value.
     */
    public function getDetail(string $key, mixed $default = null): mixed
    {
        return data_get($this->details, $key, $default);
    }

    /**
     * Set detail value.
     */
    public function setDetail(string $key, mixed $value): void
    {
        $details = $this->details ?? [];
        data_set($details, $key, $value);
        $this->update(['details' => $details]);
    }

    // =========================================================================
    // Actions
    // =========================================================================

    /**
     * Mark as verified.
     */
    public function markAsVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    /**
     * Set as default payment method.
     */
    public function setAsDefault(): void
    {
        // Remove default from other payment methods
        static::where('customer_id', $this->customer_id)
            ->where('id', '!=', $this->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true]);

        // Update customer's default_payment_method_id
        $this->customer->update(['default_payment_method_id' => $this->id]);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Scope to active (non-deleted, non-expired) payment methods.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to default payment methods.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope by provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to cards only.
     */
    public function scopeCards($query)
    {
        return $query->where('type', 'card');
    }
}
