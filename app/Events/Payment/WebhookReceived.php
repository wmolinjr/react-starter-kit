<?php

declare(strict_types=1);

namespace App\Events\Payment;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Webhook Received Event
 *
 * Fired when a payment provider webhook is received.
 * Replaces Laravel\Cashier\Events\WebhookReceived with a provider-agnostic event.
 */
class WebhookReceived
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $provider  The payment provider (stripe, asaas, etc.)
     * @param  array  $payload  The webhook payload
     * @param  array  $headers  The request headers
     */
    public function __construct(
        public string $provider,
        public array $payload,
        public array $headers = []
    ) {}

    /**
     * Get the webhook event type.
     */
    public function getType(): string
    {
        return $this->payload['type'] ?? '';
    }

    /**
     * Get data from the payload.
     */
    public function getData(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->payload['data'] ?? [];
        }

        return data_get($this->payload['data'], $key, $default);
    }

    /**
     * Get the object from the payload (Stripe convention).
     */
    public function getObject(): array
    {
        return $this->payload['data']['object'] ?? [];
    }

    /**
     * Check if this is a specific event type.
     */
    public function isType(string $type): bool
    {
        return $this->getType() === $type;
    }

    /**
     * Check if webhook is from a specific provider.
     */
    public function isFromProvider(string $provider): bool
    {
        return $this->provider === $provider;
    }
}
