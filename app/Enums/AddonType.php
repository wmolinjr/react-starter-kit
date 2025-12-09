<?php

namespace App\Enums;

/**
 * Addon Type Enum - By Billing Model
 *
 * Single source of truth for addon types based on how they're billed/used.
 *
 * Types:
 * - QUOTA: Increases a plan limit (storage, users, projects, api_calls)
 * - FEATURE: Unlocks a boolean feature (advanced_reports, custom_roles)
 * - METERED: Usage-based billing (bandwidth overage, storage overage)
 * - CREDIT: One-time purchase with validity (storage credits)
 */
enum AddonType: string
{
    case QUOTA = 'quota';       // Increases plan limits
    case FEATURE = 'feature';   // Unlocks features
    case METERED = 'metered';   // Usage-based billing
    case CREDIT = 'credit';     // One-time purchase

    /**
     * Get translatable name.
     */
    public function name(): array
    {
        return match ($this) {
            self::QUOTA => ['en' => 'Quota Increase', 'pt_BR' => 'Aumento de Cota'],
            self::FEATURE => ['en' => 'Feature', 'pt_BR' => 'Funcionalidade'],
            self::METERED => ['en' => 'Usage-Based', 'pt_BR' => 'Baseado em Uso'],
            self::CREDIT => ['en' => 'Credit Pack', 'pt_BR' => 'Pacote de Créditos'],
        };
    }

    /**
     * Get translatable description.
     */
    public function description(): array
    {
        return match ($this) {
            self::QUOTA => [
                'en' => 'Increase your plan limits (storage, users, etc.)',
                'pt_BR' => 'Aumente os limites do seu plano (armazenamento, usuários, etc.)',
            ],
            self::FEATURE => [
                'en' => 'Unlock additional features',
                'pt_BR' => 'Desbloqueie funcionalidades adicionais',
            ],
            self::METERED => [
                'en' => 'Pay only for what you use',
                'pt_BR' => 'Pague apenas pelo que usar',
            ],
            self::CREDIT => [
                'en' => 'One-time purchase with validity period',
                'pt_BR' => 'Compra única com período de validade',
            ],
        };
    }

    /**
     * Get Lucide icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::QUOTA => 'TrendingUp',
            self::FEATURE => 'Sparkles',
            self::METERED => 'Activity',
            self::CREDIT => 'CreditCard',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::QUOTA => 'blue',
            self::FEATURE => 'purple',
            self::METERED => 'orange',
            self::CREDIT => 'green',
        };
    }

    /**
     * Get badge variant for UI display.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::QUOTA => 'default',
            self::FEATURE => 'secondary',
            self::METERED => 'outline',
            self::CREDIT => 'default',
        };
    }

    /**
     * Get default unit label (can be overridden per addon).
     */
    public function unitLabel(): array
    {
        return match ($this) {
            self::QUOTA => ['en' => 'units', 'pt_BR' => 'unidades'],
            self::FEATURE => ['en' => 'feature', 'pt_BR' => 'recurso'],
            self::METERED => ['en' => 'units', 'pt_BR' => 'unidades'],
            self::CREDIT => ['en' => 'credits', 'pt_BR' => 'créditos'],
        };
    }

    /**
     * Check if this type is metered (usage-based billing).
     */
    public function isMetered(): bool
    {
        return $this === self::METERED;
    }

    /**
     * Check if this type is stackable (can purchase multiple quantities).
     */
    public function isStackable(): bool
    {
        return match ($this) {
            self::QUOTA, self::METERED, self::CREDIT => true,
            self::FEATURE => false,
        };
    }

    /**
     * Check if this type has a validity period.
     */
    public function hasValidity(): bool
    {
        return $this === self::CREDIT;
    }

    /**
     * Check if this type is a one-time purchase.
     */
    public function isOneTime(): bool
    {
        return $this === self::CREDIT;
    }

    /**
     * Check if this type is recurring (subscription).
     */
    public function isRecurring(): bool
    {
        return match ($this) {
            self::QUOTA, self::FEATURE => true,
            self::METERED, self::CREDIT => false,
        };
    }

    /**
     * Get the category for grouping in UI.
     */
    public function category(): string
    {
        return match ($this) {
            self::QUOTA => 'limits',
            self::FEATURE => 'features',
            self::METERED => 'usage',
            self::CREDIT => 'credits',
        };
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
     * Get translated unit label for current locale.
     */
    public function translatedUnitLabel(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $labels = $this->unitLabel();

        return $labels[$locale] ?? $labels['en'] ?? 'units';
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
            'category' => $this->category(),
            'unit_label' => $this->translatedUnitLabel($locale),
            'is_metered' => $this->isMetered(),
            'is_stackable' => $this->isStackable(),
            'is_recurring' => $this->isRecurring(),
            'is_one_time' => $this->isOneTime(),
            'has_validity' => $this->hasValidity(),
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
