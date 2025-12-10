<?php

declare(strict_types=1);

namespace App\DTOs\Payment;

/**
 * Result of a setup intent operation.
 *
 * Used for securely collecting payment method details on frontend.
 */
final readonly class SetupIntentResult
{
    public function __construct(
        public bool $success,
        public ?string $clientSecret = null,
        public ?string $providerIntentId = null,
        public ?string $publishableKey = null,
        public ?string $failureMessage = null,
        public ?array $providerData = null,
    ) {}

    /**
     * Create a successful setup intent result.
     */
    public static function success(
        string $clientSecret,
        string $providerIntentId,
        ?string $publishableKey = null,
        ?array $providerData = null
    ): self {
        return new self(
            success: true,
            clientSecret: $clientSecret,
            providerIntentId: $providerIntentId,
            publishableKey: $publishableKey,
            providerData: $providerData,
        );
    }

    /**
     * Create a failed setup intent result.
     */
    public static function failed(string $failureMessage): self
    {
        return new self(
            success: false,
            failureMessage: $failureMessage,
        );
    }

    /**
     * Get data needed for frontend payment form.
     */
    public function getClientData(): array
    {
        return [
            'client_secret' => $this->clientSecret,
            'publishable_key' => $this->publishableKey,
        ];
    }
}
