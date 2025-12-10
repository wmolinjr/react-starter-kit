<?php

declare(strict_types=1);

namespace App\DTOs\Payment;

/**
 * Result of a payment method operation.
 */
final readonly class PaymentMethodResult
{
    public function __construct(
        public bool $success,
        public ?string $providerMethodId = null,
        public ?string $type = null,
        public ?string $brand = null,
        public ?string $last4 = null,
        public ?int $expMonth = null,
        public ?int $expYear = null,
        public ?string $bankName = null,
        public bool $isDefault = false,
        public ?string $failureMessage = null,
        public ?array $providerData = null,
    ) {}

    /**
     * Create a successful card payment method result.
     */
    public static function card(
        string $providerMethodId,
        string $brand,
        string $last4,
        int $expMonth,
        int $expYear,
        bool $isDefault = false,
        ?array $providerData = null
    ): self {
        return new self(
            success: true,
            providerMethodId: $providerMethodId,
            type: 'card',
            brand: $brand,
            last4: $last4,
            expMonth: $expMonth,
            expYear: $expYear,
            isDefault: $isDefault,
            providerData: $providerData,
        );
    }

    /**
     * Create a successful PIX payment method result.
     */
    public static function pix(
        string $providerMethodId,
        bool $isDefault = false,
        ?array $providerData = null
    ): self {
        return new self(
            success: true,
            providerMethodId: $providerMethodId,
            type: 'pix',
            isDefault: $isDefault,
            providerData: $providerData,
        );
    }

    /**
     * Create a successful boleto payment method result.
     */
    public static function boleto(
        string $providerMethodId,
        bool $isDefault = false,
        ?array $providerData = null
    ): self {
        return new self(
            success: true,
            providerMethodId: $providerMethodId,
            type: 'boleto',
            isDefault: $isDefault,
            providerData: $providerData,
        );
    }

    /**
     * Create a failed result.
     */
    public static function failed(string $failureMessage): self
    {
        return new self(
            success: false,
            failureMessage: $failureMessage,
        );
    }

    /**
     * Get display label for the payment method.
     */
    public function getDisplayLabel(): string
    {
        return match ($this->type) {
            'card' => "{$this->brand} •••• {$this->last4}",
            'pix' => 'PIX',
            'boleto' => 'Boleto Bancário',
            default => $this->type ?? 'Unknown',
        };
    }
}
