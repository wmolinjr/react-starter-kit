<?php

declare(strict_types=1);

namespace App\Events\Payment;

use App\Models\Central\AddonPurchase;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Payment Confirmed Event
 *
 * Fired when an async payment (PIX, Boleto) is confirmed.
 * This event triggers the activation of associated subscriptions/addons.
 */
class PaymentConfirmed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  AddonPurchase  $purchase  The purchase that was paid
     * @param  string  $provider  The payment provider (stripe, asaas, etc.)
     * @param  string  $paymentIntentId  The provider's payment intent/transaction ID
     * @param  array  $metadata  Additional metadata from the payment
     */
    public function __construct(
        public AddonPurchase $purchase,
        public string $provider,
        public string $paymentIntentId,
        public array $metadata = []
    ) {}

    /**
     * Get the tenant associated with this payment.
     */
    public function getTenantId(): string
    {
        return $this->purchase->tenant_id;
    }

    /**
     * Get the amount paid in cents.
     */
    public function getAmount(): int
    {
        return $this->purchase->amount_paid;
    }
}
