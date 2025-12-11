<?php

namespace App\Enums;

/**
 * Billing Period Enum
 *
 * Single source of truth for billing periods.
 * Contains all metadata (name, description, icon, color).
 *
 * Usage:
 * - BillingPeriod::values() - Get all values as array
 * - BillingPeriod::MONTHLY->value - Get value string
 * - BillingPeriod::MONTHLY->label() - Get translatable label
 * - BillingPeriod::toFrontendArray() - Get data for frontend
 */
enum BillingPeriod: string
{
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';
    case ONE_TIME = 'one_time';
    case METERED = 'metered';
    case MANUAL = 'manual';

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        return match ($this) {
            self::MONTHLY => ['en' => 'Monthly', 'pt_BR' => 'Mensal', 'es' => 'Mensual'],
            self::YEARLY => ['en' => 'Yearly', 'pt_BR' => 'Anual', 'es' => 'Anual'],
            self::ONE_TIME => ['en' => 'One-time', 'pt_BR' => 'Único', 'es' => 'Único'],
            self::METERED => ['en' => 'Metered', 'pt_BR' => 'Medido', 'es' => 'Medido'],
            self::MANUAL => ['en' => 'Manual', 'pt_BR' => 'Manual', 'es' => 'Manual'],
        };
    }

    /**
     * Get translatable description.
     *
     * @return array<string, string>
     */
    public function description(): array
    {
        return match ($this) {
            self::MONTHLY => [
                'en' => 'Billed monthly',
                'pt_BR' => 'Cobrado mensalmente',
                'es' => 'Facturado mensualmente',
            ],
            self::YEARLY => [
                'en' => 'Billed annually with discount',
                'pt_BR' => 'Cobrado anualmente com desconto',
                'es' => 'Facturado anualmente con descuento',
            ],
            self::ONE_TIME => [
                'en' => 'One-time payment, no recurring charges',
                'pt_BR' => 'Pagamento único, sem cobrança recorrente',
                'es' => 'Pago único, sin cargos recurrentes',
            ],
            self::METERED => [
                'en' => 'Usage-based billing',
                'pt_BR' => 'Cobrança baseada em uso',
                'es' => 'Facturación basada en uso',
            ],
            self::MANUAL => [
                'en' => 'Manually managed billing',
                'pt_BR' => 'Cobrança gerenciada manualmente',
                'es' => 'Facturación gestionada manualmente',
            ],
        };
    }

    /**
     * Get Lucide icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::MONTHLY => 'Calendar',
            self::YEARLY => 'CalendarRange',
            self::ONE_TIME => 'CreditCard',
            self::METERED => 'Gauge',
            self::MANUAL => 'HandCoins',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::MONTHLY => 'blue',
            self::YEARLY => 'green',
            self::ONE_TIME => 'purple',
            self::METERED => 'orange',
            self::MANUAL => 'gray',
        };
    }

    /**
     * Get badge variant for UI display.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::MONTHLY => 'default',
            self::YEARLY => 'default',
            self::ONE_TIME => 'secondary',
            self::METERED => 'outline',
            self::MANUAL => 'outline',
        };
    }

    /**
     * Get Stripe interval name.
     */
    public function interval(): ?string
    {
        return match ($this) {
            self::MONTHLY => 'month',
            self::YEARLY => 'year',
            default => null,
        };
    }

    /**
     * Check if this is a recurring billing period.
     */
    public function isRecurring(): bool
    {
        return match ($this) {
            self::MONTHLY, self::YEARLY => true,
            default => false,
        };
    }

    /**
     * Get short label for price display (e.g., "/mo", "/yr").
     *
     * @return array<string, string>
     */
    public function shortLabel(): array
    {
        return match ($this) {
            self::MONTHLY => ['en' => '/mo', 'pt_BR' => '/mês', 'es' => '/mes'],
            self::YEARLY => ['en' => '/yr', 'pt_BR' => '/ano', 'es' => '/año'],
            self::ONE_TIME => ['en' => 'one-time', 'pt_BR' => 'único', 'es' => 'único'],
            self::METERED => ['en' => '/use', 'pt_BR' => '/uso', 'es' => '/uso'],
            self::MANUAL => ['en' => 'manual', 'pt_BR' => 'manual', 'es' => 'manual'],
        };
    }

    /**
     * Get translated short label for current locale.
     */
    public function translatedShortLabel(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $labels = $this->shortLabel();

        return $labels[$locale] ?? $labels['en'] ?? '';
    }

    /**
     * Get translated label for current locale.
     */
    public function label(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $names = $this->name();

        return $names[$locale] ?? $names['en'] ?? $this->value;
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
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all cases as options for select inputs.
     *
     * @return array<string, string>
     */
    public static function options(?string $locale = null): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label($locale);
        }

        return $options;
    }

    /**
     * Convert single case to frontend format.
     *
     * @return array<string, mixed>
     */
    public function toFrontend(?string $locale = null): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label($locale),
            'description' => $this->translatedDescription($locale),
            'icon' => $this->icon(),
            'color' => $this->color(),
            'badge_variant' => $this->badgeVariant(),
            'is_recurring' => $this->isRecurring(),
        ];
    }

    /**
     * Convert all cases to frontend array format.
     *
     * @return array<int, array<string, mixed>>
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
