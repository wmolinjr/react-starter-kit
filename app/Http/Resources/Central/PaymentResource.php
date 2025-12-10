<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;

/**
 * Payment Resource
 *
 * Used for listing customer payments/invoices in the billing portal.
 */
class PaymentResource extends BaseResource
{
    use HasTypescriptType;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->generateInvoiceNumber(),
            'date' => $this->formatIso($this->created_at),
            'paid_at' => $this->formatIso($this->paid_at),
            'amount' => $this->amount,
            'amount_formatted' => $this->getFormattedAmount(),
            'currency' => $this->currency,
            'status' => $this->mapStatus(),
            'payment_type' => $this->payment_type,
            'provider' => $this->provider,
            'description' => $this->description,
            'payable_type' => $this->getPayableTypeLabel(),
            'is_refundable' => $this->isRefundable(),
            'failure_message' => $this->failure_message,
        ];
    }

    /**
     * Generate a display invoice number.
     */
    protected function generateInvoiceNumber(): string
    {
        $date = $this->created_at?->format('Ymd') ?? date('Ymd');
        $shortId = strtoupper(substr($this->id, 0, 8));

        return "INV-{$date}-{$shortId}";
    }

    /**
     * Map internal status to display status.
     */
    protected function mapStatus(): string
    {
        return match ($this->status) {
            'paid' => 'paid',
            'pending', 'processing' => 'open',
            'failed' => 'failed',
            'refunded' => 'refunded',
            'expired' => 'void',
            'canceled' => 'void',
            default => $this->status,
        };
    }

    /**
     * Get human-readable payable type.
     */
    protected function getPayableTypeLabel(): ?string
    {
        return match ($this->payable_type) {
            'subscription' => __('billing.subscription'),
            'addon_purchase' => __('billing.addon_purchase'),
            'addon_subscription' => __('billing.addon_subscription'),
            default => $this->payable_type,
        };
    }

    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'number' => 'string',
            'date' => 'string',
            'paid_at' => 'string | null',
            'amount' => 'number',
            'amount_formatted' => 'string',
            'currency' => 'string',
            'status' => "'paid' | 'open' | 'failed' | 'refunded' | 'void'",
            'payment_type' => "'card' | 'pix' | 'boleto'",
            'provider' => 'string',
            'description' => 'string | null',
            'payable_type' => 'string | null',
            'is_refundable' => 'boolean',
            'failure_message' => 'string | null',
        ];
    }
}
