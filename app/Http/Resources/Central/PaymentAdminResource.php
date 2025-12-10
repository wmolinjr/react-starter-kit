<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

class PaymentAdminResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->customer_id,
            'payment_method_id' => $this->payment_method_id,

            'provider' => $this->provider,
            'provider_payment_id' => $this->provider_payment_id,
            'provider_data' => $this->provider_data,

            'amount' => $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'currency' => $this->currency,

            'refunded_amount' => $this->refunded_amount,
            'formatted_refunded_amount' => $this->formatMoney($this->refunded_amount),

            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->getPaymentMethodLabel(),

            'description' => $this->description,
            'metadata' => $this->metadata,

            'paid_at' => $this->formatIso($this->paid_at),
            'failed_at' => $this->formatIso($this->failed_at),
            'refunded_at' => $this->formatIso($this->refunded_at),
            'created_at' => $this->formatIso($this->created_at),

            // Relations
            'tenant' => $this->when($this->relationLoaded('tenant'), fn () => [
                'id' => $this->tenant?->id,
                'name' => $this->tenant?->id, // Tenants don't have names, use ID
            ]),
            'customer' => $this->when($this->relationLoaded('customer'), fn () => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
                'email' => $this->customer?->email,
            ]),
            'payment_method_details' => $this->when($this->relationLoaded('paymentMethod'), fn () => [
                'type' => $this->paymentMethod?->type,
                'brand' => $this->paymentMethod?->brand,
                'last_four' => $this->paymentMethod?->last_four,
            ]),

            // Computed
            'can_refund' => $this->canRefund(),
            'refundable_amount' => $this->getRefundableAmount(),
            'formatted_refundable_amount' => $this->formatMoney($this->getRefundableAmount()),
        ];
    }

    /**
     * Get status label.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => __('payments.status.pending'),
            'processing' => __('payments.status.processing'),
            'succeeded' => __('payments.status.succeeded'),
            'failed' => __('payments.status.failed'),
            'canceled' => __('payments.status.canceled'),
            'refunded' => __('payments.status.refunded'),
            'partially_refunded' => __('payments.status.partially_refunded'),
            default => ucfirst($this->status),
        };
    }

    /**
     * Get status color for UI.
     */
    private function getStatusColor(): string
    {
        return match ($this->status) {
            'succeeded' => 'green',
            'pending', 'processing' => 'yellow',
            'failed', 'canceled' => 'red',
            'refunded', 'partially_refunded' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get payment method label.
     */
    private function getPaymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'card' => __('payments.methods.card'),
            'pix' => __('payments.methods.pix'),
            'boleto' => __('payments.methods.boleto'),
            default => ucfirst($this->payment_method ?? 'Unknown'),
        };
    }

    /**
     * Check if payment can be refunded.
     */
    private function canRefund(): bool
    {
        return $this->status === 'succeeded'
            && $this->refunded_amount < $this->amount
            && $this->provider_payment_id !== null;
    }

    /**
     * Get the amount that can still be refunded.
     */
    private function getRefundableAmount(): int
    {
        return max(0, $this->amount - ($this->refunded_amount ?? 0));
    }

    /**
     * Format money value.
     */
    private function formatMoney(?int $amount): string
    {
        if ($amount === null) {
            return 'R$0,00';
        }

        return 'R$'.number_format($amount / 100, 2, ',', '.');
    }

    /**
     * Get the TypeScript type definition.
     *
     * @return array<string, string>
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string | null',
            'customer_id' => 'string | null',
            'payment_method_id' => 'string | null',
            'provider' => 'string',
            'provider_payment_id' => 'string | null',
            'provider_data' => 'Record<string, unknown> | null',
            'amount' => 'number',
            'formatted_amount' => 'string',
            'currency' => 'string',
            'refunded_amount' => 'number',
            'formatted_refunded_amount' => 'string',
            'status' => "'pending' | 'processing' | 'succeeded' | 'failed' | 'canceled' | 'refunded' | 'partially_refunded'",
            'status_label' => 'string',
            'status_color' => 'string',
            'payment_method' => 'string | null',
            'payment_method_label' => 'string',
            'description' => 'string | null',
            'metadata' => 'Record<string, unknown> | null',
            'paid_at' => 'string | null',
            'failed_at' => 'string | null',
            'refunded_at' => 'string | null',
            'created_at' => 'string',
            'tenant' => '{ id: string; name: string } | undefined',
            'customer' => '{ id: string; name: string; email: string } | undefined',
            'payment_method_details' => '{ type: string; brand: string | null; last_four: string | null } | undefined',
            'can_refund' => 'boolean',
            'refundable_amount' => 'number',
            'formatted_refundable_amount' => 'string',
        ];
    }
}
