<?php

declare(strict_types=1);

namespace App\Events\Payment;

use App\Models\Central\AddonPurchase;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Payment Failed Event
 *
 * Fired when an async payment (PIX, Boleto) fails or expires.
 * This event triggers cleanup and customer notification.
 */
class PaymentFailed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  AddonPurchase  $purchase  The purchase that failed
     * @param  string  $provider  The payment provider (stripe, asaas, etc.)
     * @param  string  $reason  The failure reason
     * @param  array  $metadata  Additional metadata from the payment
     */
    public function __construct(
        public AddonPurchase $purchase,
        public string $provider,
        public string $reason,
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
     * Get the failure reason.
     */
    public function getFailureReason(): string
    {
        return $this->reason;
    }
}
