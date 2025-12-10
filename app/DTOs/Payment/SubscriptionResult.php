<?php

declare(strict_types=1);

namespace App\DTOs\Payment;

use Carbon\Carbon;

/**
 * Result of a subscription operation.
 */
final readonly class SubscriptionResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $providerSubscriptionId = null,
        public ?string $providerCustomerId = null,
        public ?string $providerPriceId = null,
        public ?Carbon $currentPeriodStart = null,
        public ?Carbon $currentPeriodEnd = null,
        public ?Carbon $trialEndsAt = null,
        public ?Carbon $canceledAt = null,
        public ?string $failureMessage = null,
        public ?array $providerData = null,
    ) {}

    /**
     * Create a successful subscription result.
     */
    public static function success(
        string $providerSubscriptionId,
        string $status,
        ?string $providerCustomerId = null,
        ?string $providerPriceId = null,
        ?Carbon $currentPeriodStart = null,
        ?Carbon $currentPeriodEnd = null,
        ?Carbon $trialEndsAt = null,
        ?array $providerData = null
    ): self {
        return new self(
            success: true,
            status: $status,
            providerSubscriptionId: $providerSubscriptionId,
            providerCustomerId: $providerCustomerId,
            providerPriceId: $providerPriceId,
            currentPeriodStart: $currentPeriodStart,
            currentPeriodEnd: $currentPeriodEnd,
            trialEndsAt: $trialEndsAt,
            providerData: $providerData,
        );
    }

    /**
     * Create a failed subscription result.
     */
    public static function failed(
        string $failureMessage,
        ?array $providerData = null
    ): self {
        return new self(
            success: false,
            status: 'failed',
            failureMessage: $failureMessage,
            providerData: $providerData,
        );
    }

    /**
     * Create a canceled subscription result.
     */
    public static function canceled(
        string $providerSubscriptionId,
        Carbon $canceledAt,
        ?Carbon $endsAt = null
    ): self {
        return new self(
            success: true,
            status: 'canceled',
            providerSubscriptionId: $providerSubscriptionId,
            currentPeriodEnd: $endsAt,
            canceledAt: $canceledAt,
        );
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing'], true);
    }

    /**
     * Check if subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->status === 'trialing'
            || ($this->trialEndsAt !== null && $this->trialEndsAt->isFuture());
    }
}
