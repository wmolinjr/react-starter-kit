<?php

namespace App\Enums;

/**
 * Payment Gateway Enum
 *
 * Single source of truth for supported payment gateways.
 * Contains metadata for credentials, payment types, and configuration.
 */
enum PaymentGateway: string
{
    case STRIPE = 'stripe';
    case ASAAS = 'asaas';
    case PAGSEGURO = 'pagseguro';
    case MERCADOPAGO = 'mercadopago';

    /**
     * Get display name.
     */
    public function displayName(): string
    {
        return match ($this) {
            self::STRIPE => 'Stripe',
            self::ASAAS => 'Asaas',
            self::PAGSEGURO => 'PagSeguro',
            self::MERCADOPAGO => 'Mercado Pago',
        };
    }

    /**
     * Get translatable description.
     */
    public function description(): array
    {
        return match ($this) {
            self::STRIPE => [
                'en' => 'Global payment platform for card payments',
                'pt_BR' => 'Plataforma de pagamentos global para cartões',
            ],
            self::ASAAS => [
                'en' => 'Brazilian payment platform with PIX and Boleto',
                'pt_BR' => 'Plataforma de pagamentos brasileira com PIX e Boleto',
            ],
            self::PAGSEGURO => [
                'en' => 'Brazilian payment gateway by UOL',
                'pt_BR' => 'Gateway de pagamentos brasileiro da UOL',
            ],
            self::MERCADOPAGO => [
                'en' => 'Latin American payment platform by Mercado Libre',
                'pt_BR' => 'Plataforma de pagamentos do Mercado Livre',
            ],
        };
    }

    /**
     * Get Lucide icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::STRIPE => 'CreditCard',
            self::ASAAS => 'Landmark',
            self::PAGSEGURO => 'ShieldCheck',
            self::MERCADOPAGO => 'Store',
        };
    }

    /**
     * Get brand color for UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::STRIPE => '#635BFF',
            self::ASAAS => '#00C853',
            self::PAGSEGURO => '#FFC107',
            self::MERCADOPAGO => '#009EE3',
        };
    }

    /**
     * Get supported payment types.
     */
    public function supportedPaymentTypes(): array
    {
        return match ($this) {
            self::STRIPE => ['card'],
            self::ASAAS => ['card', 'pix', 'boleto'],
            self::PAGSEGURO => ['card', 'pix', 'boleto'],
            self::MERCADOPAGO => ['card', 'pix', 'boleto'],
        };
    }

    /**
     * Get default available countries.
     */
    public function defaultCountries(): array
    {
        return match ($this) {
            self::STRIPE => ['US', 'CA', 'GB', 'EU', 'AU'],
            self::ASAAS => ['BR'],
            self::PAGSEGURO => ['BR'],
            self::MERCADOPAGO => ['BR', 'AR', 'MX', 'CO', 'CL'],
        };
    }

    /**
     * Get credential field definitions.
     *
     * @return array<array{key: string, label: string, type: string, required: bool, prefix?: string, help?: string}>
     */
    public function credentialFields(): array
    {
        return match ($this) {
            self::STRIPE => [
                [
                    'key' => 'key',
                    'label' => 'Public Key',
                    'type' => 'text',
                    'required' => true,
                    'prefix' => 'pk_',
                    'help' => 'Starts with pk_live_ or pk_test_',
                ],
                [
                    'key' => 'secret',
                    'label' => 'Secret Key',
                    'type' => 'password',
                    'required' => true,
                    'prefix' => 'sk_',
                    'help' => 'Starts with sk_live_ or sk_test_',
                ],
                [
                    'key' => 'webhook_secret',
                    'label' => 'Webhook Secret',
                    'type' => 'password',
                    'required' => false,
                    'prefix' => 'whsec_',
                    'help' => 'From Stripe Dashboard > Webhooks',
                ],
            ],
            self::ASAAS => [
                [
                    'key' => 'api_key',
                    'label' => 'API Key',
                    'type' => 'password',
                    'required' => true,
                    'help' => 'From Asaas Dashboard > API Keys',
                ],
                [
                    'key' => 'webhook_token',
                    'label' => 'Webhook Token',
                    'type' => 'password',
                    'required' => false,
                    'help' => 'Optional: For webhook verification',
                ],
            ],
            self::PAGSEGURO => [
                [
                    'key' => 'api_key',
                    'label' => 'Bearer Token',
                    'type' => 'password',
                    'required' => true,
                    'help' => 'From PagSeguro Dashboard > API',
                ],
                [
                    'key' => 'public_key',
                    'label' => 'Public Key',
                    'type' => 'text',
                    'required' => true,
                    'help' => 'For frontend encryption',
                ],
                [
                    'key' => 'receiver_email',
                    'label' => 'Receiver Email',
                    'type' => 'email',
                    'required' => true,
                    'help' => 'PagSeguro account email',
                ],
                [
                    'key' => 'webhook_token',
                    'label' => 'Webhook Token',
                    'type' => 'password',
                    'required' => false,
                    'help' => 'Optional: For webhook verification',
                ],
            ],
            self::MERCADOPAGO => [
                [
                    'key' => 'public_key',
                    'label' => 'Public Key',
                    'type' => 'text',
                    'required' => true,
                    'help' => 'From Mercado Pago Dashboard',
                ],
                [
                    'key' => 'access_token',
                    'label' => 'Access Token',
                    'type' => 'password',
                    'required' => true,
                    'help' => 'From Mercado Pago Dashboard > Credentials',
                ],
                [
                    'key' => 'webhook_secret',
                    'label' => 'Webhook Secret',
                    'type' => 'password',
                    'required' => false,
                    'help' => 'For webhook signature verification',
                ],
            ],
        };
    }

    /**
     * Get webhook endpoint path.
     */
    public function webhookPath(): string
    {
        return match ($this) {
            self::STRIPE => '/stripe/webhook',
            self::ASAAS => '/asaas/webhook',
            self::PAGSEGURO => '/pagseguro/webhook',
            self::MERCADOPAGO => '/mercadopago/webhook',
        };
    }

    /**
     * Get documentation URL.
     */
    public function docsUrl(): string
    {
        return match ($this) {
            self::STRIPE => 'https://stripe.com/docs',
            self::ASAAS => 'https://docs.asaas.com',
            self::PAGSEGURO => 'https://dev.pagseguro.uol.com.br',
            self::MERCADOPAGO => 'https://www.mercadopago.com.br/developers',
        };
    }

    /**
     * Get sandbox/test environment URL.
     */
    public function sandboxUrl(): ?string
    {
        return match ($this) {
            self::STRIPE => 'https://dashboard.stripe.com/test',
            self::ASAAS => 'https://sandbox.asaas.com',
            self::PAGSEGURO => 'https://sandbox.pagseguro.uol.com.br',
            self::MERCADOPAGO => null, // Uses same dashboard with test credentials
        };
    }

    /**
     * Check if gateway supports subscriptions.
     */
    public function supportsSubscriptions(): bool
    {
        return match ($this) {
            self::STRIPE => true,
            self::ASAAS => true,
            self::PAGSEGURO => false,
            self::MERCADOPAGO => true,
        };
    }

    /**
     * Check if gateway supports refunds.
     */
    public function supportsRefunds(): bool
    {
        return match ($this) {
            self::STRIPE => true,
            self::ASAAS => true,
            self::PAGSEGURO => true,
            self::MERCADOPAGO => true,
        };
    }

    /**
     * Get translated description for current locale.
     */
    public function translatedDescription(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $descriptions = $this->description();

        return $descriptions[$locale] ?? $descriptions['en'] ?? '';
    }

    /**
     * Get all values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all cases as options for select inputs.
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->displayName();
        }

        return $options;
    }

    /**
     * Convert single case to frontend format.
     */
    public function toFrontend(?string $locale = null): array
    {
        return [
            'value' => $this->value,
            'displayName' => $this->displayName(),
            'description' => $this->translatedDescription($locale),
            'icon' => $this->icon(),
            'color' => $this->color(),
            'supportedPaymentTypes' => $this->supportedPaymentTypes(),
            'defaultCountries' => $this->defaultCountries(),
            'credentialFields' => $this->credentialFields(),
            'webhookPath' => $this->webhookPath(),
            'docsUrl' => $this->docsUrl(),
            'sandboxUrl' => $this->sandboxUrl(),
            'supportsSubscriptions' => $this->supportsSubscriptions(),
            'supportsRefunds' => $this->supportsRefunds(),
        ];
    }

    /**
     * Convert all cases to frontend array format.
     */
    public static function toFrontendArray(?string $locale = null): array
    {
        return array_map(
            fn (self $case) => $case->toFrontend($locale),
            self::cases()
        );
    }

    /**
     * Convert all cases to frontend map format (keyed by value).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function toFrontendMap(?string $locale = null): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->toFrontend($locale);
        }

        return $map;
    }
}
