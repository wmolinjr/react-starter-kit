<?php

namespace App\Enums;

/**
 * Predefined badge presets for Addons and Bundles
 *
 * Single source of truth for all badge metadata including translations.
 * Each badge has an icon, color scheme, and translated label.
 * Used for marketing display in pricing pages.
 */
enum BadgePreset: string
{
    case MOST_POPULAR = 'most_popular';
    case BEST_VALUE = 'best_value';
    case BEST_FOR_TEAMS = 'best_for_teams';
    case ENTERPRISE = 'enterprise';
    case ONE_TIME = 'one_time';
    case NEW = 'new';
    case LIMITED_TIME = 'limited_time';
    case RECOMMENDED = 'recommended';
    case SALE = 'sale';
    case HOT = 'hot';
    case STARTER = 'starter';
    case PRO = 'pro';

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        return match ($this) {
            self::MOST_POPULAR => ['en' => 'Most Popular', 'pt_BR' => 'Mais Popular'],
            self::BEST_VALUE => ['en' => 'Best Value', 'pt_BR' => 'Melhor Custo-Benefício'],
            self::BEST_FOR_TEAMS => ['en' => 'Best for Teams', 'pt_BR' => 'Melhor para Equipes'],
            self::ENTERPRISE => ['en' => 'Enterprise', 'pt_BR' => 'Empresarial'],
            self::ONE_TIME => ['en' => 'One Time', 'pt_BR' => 'Pagamento Único'],
            self::NEW => ['en' => 'New', 'pt_BR' => 'Novo'],
            self::LIMITED_TIME => ['en' => 'Limited Time', 'pt_BR' => 'Tempo Limitado'],
            self::RECOMMENDED => ['en' => 'Recommended', 'pt_BR' => 'Recomendado'],
            self::SALE => ['en' => 'Sale', 'pt_BR' => 'Promoção'],
            self::HOT => ['en' => 'Hot', 'pt_BR' => 'Em Alta'],
            self::STARTER => ['en' => 'Starter', 'pt_BR' => 'Iniciante'],
            self::PRO => ['en' => 'Pro', 'pt_BR' => 'Profissional'],
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
            self::MOST_POPULAR => ['en' => 'Our most popular choice', 'pt_BR' => 'Nossa escolha mais popular'],
            self::BEST_VALUE => ['en' => 'Best value for money', 'pt_BR' => 'Melhor custo-benefício'],
            self::BEST_FOR_TEAMS => ['en' => 'Ideal for team collaboration', 'pt_BR' => 'Ideal para colaboração em equipe'],
            self::ENTERPRISE => ['en' => 'For large organizations', 'pt_BR' => 'Para grandes organizações'],
            self::ONE_TIME => ['en' => 'Pay once, use forever', 'pt_BR' => 'Pague uma vez, use para sempre'],
            self::NEW => ['en' => 'Recently added feature', 'pt_BR' => 'Recurso adicionado recentemente'],
            self::LIMITED_TIME => ['en' => 'Available for a limited time', 'pt_BR' => 'Disponível por tempo limitado'],
            self::RECOMMENDED => ['en' => 'Recommended by our team', 'pt_BR' => 'Recomendado pela nossa equipe'],
            self::SALE => ['en' => 'Special promotional price', 'pt_BR' => 'Preço promocional especial'],
            self::HOT => ['en' => 'Trending right now', 'pt_BR' => 'Em alta agora'],
            self::STARTER => ['en' => 'Perfect for getting started', 'pt_BR' => 'Perfeito para começar'],
            self::PRO => ['en' => 'For professionals', 'pt_BR' => 'Para profissionais'],
        };
    }

    /**
     * Get the Lucide icon name for this badge.
     */
    public function icon(): string
    {
        return match ($this) {
            self::MOST_POPULAR => 'Star',
            self::BEST_VALUE => 'Trophy',
            self::BEST_FOR_TEAMS => 'Users',
            self::ENTERPRISE => 'Building2',
            self::ONE_TIME => 'Coins',
            self::NEW => 'Sparkles',
            self::LIMITED_TIME => 'Clock',
            self::RECOMMENDED => 'ThumbsUp',
            self::SALE => 'Tag',
            self::HOT => 'Flame',
            self::STARTER => 'Rocket',
            self::PRO => 'Crown',
        };
    }

    /**
     * Get base color for UI display (semantic color name).
     */
    public function color(): string
    {
        return match ($this) {
            self::MOST_POPULAR => 'amber',
            self::BEST_VALUE => 'green',
            self::BEST_FOR_TEAMS => 'blue',
            self::ENTERPRISE => 'purple',
            self::ONE_TIME => 'teal',
            self::NEW => 'cyan',
            self::LIMITED_TIME => 'orange',
            self::RECOMMENDED => 'indigo',
            self::SALE => 'red',
            self::HOT => 'rose',
            self::STARTER => 'sky',
            self::PRO => 'violet',
        };
    }

    /**
     * Get the Tailwind color classes for this badge.
     * Returns [bg, text, border] with dark mode variants.
     *
     * @return array{bg: string, text: string, border: string}
     */
    public function colorClasses(): array
    {
        $color = $this->color();

        return [
            'bg' => "bg-{$color}-100 dark:bg-{$color}-900/30",
            'text' => "text-{$color}-700 dark:text-{$color}-300",
            'border' => "border-{$color}-300 dark:border-{$color}-700",
        ];
    }

    /**
     * Get badge variant for UI display.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::MOST_POPULAR, self::HOT => 'default',
            self::SALE, self::LIMITED_TIME => 'destructive',
            self::NEW, self::RECOMMENDED => 'secondary',
            default => 'outline',
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

        return $descriptions[$locale] ?? $descriptions['en'];
    }

    /**
     * Get all badge values as strings.
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
     * Convert single badge to frontend format.
     *
     * @return array<string, mixed>
     */
    public function toFrontend(?string $locale = null): array
    {
        $colorClasses = $this->colorClasses();

        return [
            'value' => $this->value,
            'label' => $this->label($locale),
            'description' => $this->translatedDescription($locale),
            'icon' => $this->icon(),
            'color' => $this->color(),
            'badge_variant' => $this->badgeVariant(),
            'bg' => $colorClasses['bg'],
            'text' => $colorClasses['text'],
            'border' => $colorClasses['border'],
        ];
    }

    /**
     * Convert all badges to frontend array format.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toFrontendArray(?string $locale = null): array
    {
        return array_map(
            fn (self $badge) => $badge->toFrontend($locale),
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

    /**
     * Convert to array for frontend (alias for toFrontend).
     *
     * @deprecated Use toFrontend() instead
     */
    public function toArray(): array
    {
        return $this->toFrontend();
    }

    /**
     * Get all badges as array for frontend (alias for toFrontendArray).
     *
     * @deprecated Use toFrontendArray() instead
     */
    public static function all(): array
    {
        return self::toFrontendArray();
    }
}
