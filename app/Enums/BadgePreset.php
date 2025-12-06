<?php

namespace App\Enums;

/**
 * Predefined badge presets for Addons and Bundles
 *
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
     * Get the Lucide icon name for this badge
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
     * Get the color scheme for this badge (Tailwind classes)
     * Returns [background, text, border] colors
     */
    public function colors(): array
    {
        return match ($this) {
            self::MOST_POPULAR => [
                'bg' => 'bg-amber-100 dark:bg-amber-900/30',
                'text' => 'text-amber-700 dark:text-amber-300',
                'border' => 'border-amber-300 dark:border-amber-700',
            ],
            self::BEST_VALUE => [
                'bg' => 'bg-green-100 dark:bg-green-900/30',
                'text' => 'text-green-700 dark:text-green-300',
                'border' => 'border-green-300 dark:border-green-700',
            ],
            self::BEST_FOR_TEAMS => [
                'bg' => 'bg-blue-100 dark:bg-blue-900/30',
                'text' => 'text-blue-700 dark:text-blue-300',
                'border' => 'border-blue-300 dark:border-blue-700',
            ],
            self::ENTERPRISE => [
                'bg' => 'bg-purple-100 dark:bg-purple-900/30',
                'text' => 'text-purple-700 dark:text-purple-300',
                'border' => 'border-purple-300 dark:border-purple-700',
            ],
            self::ONE_TIME => [
                'bg' => 'bg-teal-100 dark:bg-teal-900/30',
                'text' => 'text-teal-700 dark:text-teal-300',
                'border' => 'border-teal-300 dark:border-teal-700',
            ],
            self::NEW => [
                'bg' => 'bg-cyan-100 dark:bg-cyan-900/30',
                'text' => 'text-cyan-700 dark:text-cyan-300',
                'border' => 'border-cyan-300 dark:border-cyan-700',
            ],
            self::LIMITED_TIME => [
                'bg' => 'bg-orange-100 dark:bg-orange-900/30',
                'text' => 'text-orange-700 dark:text-orange-300',
                'border' => 'border-orange-300 dark:border-orange-700',
            ],
            self::RECOMMENDED => [
                'bg' => 'bg-indigo-100 dark:bg-indigo-900/30',
                'text' => 'text-indigo-700 dark:text-indigo-300',
                'border' => 'border-indigo-300 dark:border-indigo-700',
            ],
            self::SALE => [
                'bg' => 'bg-red-100 dark:bg-red-900/30',
                'text' => 'text-red-700 dark:text-red-300',
                'border' => 'border-red-300 dark:border-red-700',
            ],
            self::HOT => [
                'bg' => 'bg-rose-100 dark:bg-rose-900/30',
                'text' => 'text-rose-700 dark:text-rose-300',
                'border' => 'border-rose-300 dark:border-rose-700',
            ],
            self::STARTER => [
                'bg' => 'bg-sky-100 dark:bg-sky-900/30',
                'text' => 'text-sky-700 dark:text-sky-300',
                'border' => 'border-sky-300 dark:border-sky-700',
            ],
            self::PRO => [
                'bg' => 'bg-violet-100 dark:bg-violet-900/30',
                'text' => 'text-violet-700 dark:text-violet-300',
                'border' => 'border-violet-300 dark:border-violet-700',
            ],
        };
    }

    /**
     * Get the translation key for this badge label
     */
    public function translationKey(): string
    {
        return "badges.{$this->value}";
    }

    /**
     * Get translated label for this badge
     */
    public function label(): string
    {
        return __($this->translationKey());
    }

    /**
     * Convert to array for frontend
     */
    public function toArray(): array
    {
        $colors = $this->colors();

        return [
            'value' => $this->value,
            'label' => $this->label(),
            'icon' => $this->icon(),
            'bg' => $colors['bg'],
            'text' => $colors['text'],
            'border' => $colors['border'],
        ];
    }

    /**
     * Get all badges as array for frontend
     */
    public static function all(): array
    {
        return array_map(
            fn (self $badge) => $badge->toArray(),
            self::cases()
        );
    }
}
