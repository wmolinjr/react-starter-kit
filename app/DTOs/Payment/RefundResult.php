<?php

declare(strict_types=1);

namespace App\DTOs\Payment;

/**
 * Result of a refund operation.
 */
final readonly class RefundResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $providerRefundId = null,
        public ?int $amountRefunded = null,
        public ?string $failureMessage = null,
        public ?array $providerData = null,
    ) {}

    /**
     * Create a successful refund result.
     */
    public static function success(
        string $providerRefundId,
        int $amountRefunded,
        ?array $providerData = null
    ): self {
        return new self(
            success: true,
            status: 'refunded',
            providerRefundId: $providerRefundId,
            amountRefunded: $amountRefunded,
            providerData: $providerData,
        );
    }

    /**
     * Create a pending refund result.
     */
    public static function pending(
        string $providerRefundId,
        int $amountRefunded
    ): self {
        return new self(
            success: true,
            status: 'pending',
            providerRefundId: $providerRefundId,
            amountRefunded: $amountRefunded,
        );
    }

    /**
     * Create a failed refund result.
     */
    public static function failed(string $failureMessage): self
    {
        return new self(
            success: false,
            status: 'failed',
            failureMessage: $failureMessage,
        );
    }
}
