<?php

declare(strict_types=1);

namespace App\Models\Central;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Payment Model - Provider-Agnostic
 *
 * Records all payment transactions. Supports multiple payment providers
 * and payment types (card, PIX, boleto, bank transfer).
 *
 * @property string $id UUID primary key
 * @property string $customer_id
 * @property string|null $tenant_id
 * @property string $provider Payment provider (stripe, asaas, etc.)
 * @property string|null $provider_payment_id ID in the provider
 * @property string|null $payment_method_id
 * @property string $payment_type Type (card, pix, boleto)
 * @property int $amount Amount in cents
 * @property string $currency 3-letter currency code
 * @property int $fee Provider fee in cents
 * @property int $net_amount Net amount (amount - fee)
 * @property string $status Status (pending, processing, paid, failed, refunded, expired, canceled)
 * @property Carbon|null $paid_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $refunded_at
 * @property int $amount_refunded Amount refunded in cents
 * @property string $payable_type Polymorphic type (subscription, addon_purchase, invoice)
 * @property string $payable_id Polymorphic ID
 * @property string|null $description
 * @property array|null $provider_data Provider-specific data (QR code, linha digitável, etc.)
 * @property array|null $metadata
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Payment extends Model
{
    use CentralConnection;
    use HasFactory;
    use HasUuids;

    protected $table = 'payments';

    protected $fillable = [
        'customer_id',
        'tenant_id',
        'provider',
        'provider_payment_id',
        'payment_method_id',
        'payment_type',
        'amount',
        'currency',
        'fee',
        'status',
        'paid_at',
        'expires_at',
        'refunded_at',
        'amount_refunded',
        'payable_type',
        'payable_id',
        'description',
        'provider_data',
        'metadata',
        'failure_code',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fee' => 'integer',
            'net_amount' => 'integer',
            'amount_refunded' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'refunded_at' => 'datetime',
            'provider_data' => 'array',
            'metadata' => 'array',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * Get the customer that made this payment.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the tenant this payment is for.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the payment method used.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Get the payable entity (subscription, addon_purchase, invoice).
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    // =========================================================================
    // Status Checks
    // =========================================================================

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if payment is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if payment failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if payment is refunded (fully).
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    /**
     * Check if payment is partially refunded.
     */
    public function isPartiallyRefunded(): bool
    {
        return $this->amount_refunded > 0 && $this->amount_refunded < $this->amount;
    }

    /**
     * Check if payment is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    /**
     * Check if payment is canceled.
     */
    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    /**
     * Check if payment is successful (paid or partially refunded).
     */
    public function isSuccessful(): bool
    {
        return $this->isPaid() || $this->isPartiallyRefunded();
    }

    /**
     * Check if payment is refundable.
     */
    public function isRefundable(): bool
    {
        return $this->isPaid() && $this->amount_refunded < $this->amount;
    }

    // =========================================================================
    // Payment Type Checks
    // =========================================================================

    /**
     * Check if this is a card payment.
     */
    public function isCardPayment(): bool
    {
        return $this->payment_type === 'card';
    }

    /**
     * Check if this is a PIX payment.
     */
    public function isPixPayment(): bool
    {
        return $this->payment_type === 'pix';
    }

    /**
     * Check if this is a boleto payment.
     */
    public function isBoletoPayment(): bool
    {
        return $this->payment_type === 'boleto';
    }

    // =========================================================================
    // Amount Helpers
    // =========================================================================

    /**
     * Get amount in decimal format.
     */
    public function getAmountDecimal(): float
    {
        return $this->amount / 100;
    }

    /**
     * Get fee in decimal format.
     */
    public function getFeeDecimal(): float
    {
        return $this->fee / 100;
    }

    /**
     * Get net amount in decimal format.
     */
    public function getNetAmountDecimal(): float
    {
        return ($this->amount - $this->fee) / 100;
    }

    /**
     * Get refundable amount in cents.
     */
    public function getRefundableAmount(): int
    {
        return $this->amount - $this->amount_refunded;
    }

    /**
     * Get formatted amount for display.
     */
    public function getFormattedAmount(): string
    {
        return $this->formatMoney($this->amount);
    }

    /**
     * Format money for display.
     */
    protected function formatMoney(int $cents): string
    {
        $value = $cents / 100;
        $symbol = match ($this->currency) {
            'BRL' => 'R$',
            'USD' => '$',
            'EUR' => '€',
            default => $this->currency.' ',
        };

        return $symbol.' '.number_format($value, 2, ',', '.');
    }

    // =========================================================================
    // Provider Helpers
    // =========================================================================

    /**
     * Check if payment is from a specific provider.
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

    /**
     * Get PIX QR code if available.
     */
    public function getPixQrCode(): ?string
    {
        return $this->getProviderData('pix.qr_code');
    }

    /**
     * Get PIX copy-paste code if available.
     */
    public function getPixCopyPaste(): ?string
    {
        return $this->getProviderData('pix.copy_paste');
    }

    /**
     * Get boleto barcode if available.
     */
    public function getBoletoBarcode(): ?string
    {
        return $this->getProviderData('boleto.barcode');
    }

    /**
     * Get boleto linha digitável if available.
     */
    public function getBoletoLinhaDigitavel(): ?string
    {
        return $this->getProviderData('boleto.linha_digitavel');
    }

    /**
     * Get boleto PDF URL if available.
     */
    public function getBoletoPdfUrl(): ?string
    {
        return $this->getProviderData('boleto.pdf_url');
    }

    // =========================================================================
    // Actions
    // =========================================================================

    /**
     * Mark payment as paid.
     */
    public function markAsPaid(?Carbon $paidAt = null): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => $paidAt ?? now(),
        ]);
    }

    /**
     * Mark payment as failed.
     */
    public function markAsFailed(?string $code = null, ?string $message = null): void
    {
        $this->update([
            'status' => 'failed',
            'failure_code' => $code,
            'failure_message' => $message,
        ]);
    }

    /**
     * Mark payment as refunded.
     */
    public function markAsRefunded(int $amount, ?Carbon $refundedAt = null): void
    {
        $newRefundedAmount = $this->amount_refunded + $amount;
        $isFullRefund = $newRefundedAmount >= $this->amount;

        $this->update([
            'amount_refunded' => $newRefundedAmount,
            'status' => $isFullRefund ? 'refunded' : $this->status,
            'refunded_at' => $isFullRefund ? ($refundedAt ?? now()) : $this->refunded_at,
        ]);
    }

    /**
     * Mark payment as expired.
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Mark payment as canceled.
     */
    public function markAsCanceled(): void
    {
        $this->update(['status' => 'canceled']);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Scope to paid payments.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope to pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to failed payments.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope by provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope by payment type.
     */
    public function scopeByPaymentType($query, string $type)
    {
        return $query->where('payment_type', $type);
    }

    /**
     * Scope to payments for a specific payable.
     */
    public function scopeForPayable($query, string $type, string $id)
    {
        return $query->where('payable_type', $type)->where('payable_id', $id);
    }
}
