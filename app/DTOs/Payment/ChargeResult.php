<?php

declare(strict_types=1);

namespace App\DTOs\Payment;

/**
 * Result of a charge/payment operation.
 */
final readonly class ChargeResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $providerPaymentId = null,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
        public ?array $providerData = null,
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(
        string $providerPaymentId,
        string $status = 'paid',
        ?array $providerData = null
    ): self {
        return new self(
            success: true,
            status: $status,
            providerPaymentId: $providerPaymentId,
            providerData: $providerData,
        );
    }

    /**
     * Create a pending result (e.g., PIX awaiting payment).
     */
    public static function pending(
        string $providerPaymentId,
        array $providerData = []
    ): self {
        return new self(
            success: true,
            status: 'pending',
            providerPaymentId: $providerPaymentId,
            providerData: $providerData,
        );
    }

    /**
     * Create a failed result.
     */
    public static function failed(
        string $failureMessage,
        ?string $failureCode = null,
        ?array $providerData = null
    ): self {
        return new self(
            success: false,
            status: 'failed',
            failureCode: $failureCode,
            failureMessage: $failureMessage,
            providerData: $providerData,
        );
    }

    /**
     * Check if payment requires action (e.g., 3D Secure, PIX QR code).
     */
    public function requiresAction(): bool
    {
        return $this->status === 'requires_action' || $this->status === 'pending';
    }

    /**
     * Get action data (QR code, redirect URL, etc.).
     */
    public function getActionData(): ?array
    {
        if (! $this->requiresAction()) {
            return null;
        }

        return $this->providerData;
    }
}
