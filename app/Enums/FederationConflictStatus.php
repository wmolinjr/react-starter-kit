<?php

namespace App\Enums;

/**
 * Federation Conflict Status Enum
 *
 * Single source of truth for federation conflict statuses.
 * Contains all metadata (name, description, icon, color, badge variant).
 *
 * Usage:
 * - FederationConflictStatus::values() - Get all values as array
 * - FederationConflictStatus::PENDING->value - Get value string
 * - FederationConflictStatus::PENDING->label() - Get translatable label
 * - FederationConflictStatus::toFrontendArray() - Get data for frontend
 */
enum FederationConflictStatus: string
{
    case PENDING = 'pending';
    case RESOLVED = 'resolved';
    case DISMISSED = 'dismissed';

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        return match ($this) {
            self::PENDING => ['en' => 'Pending', 'pt_BR' => 'Pendente'],
            self::RESOLVED => ['en' => 'Resolved', 'pt_BR' => 'Resolvido'],
            self::DISMISSED => ['en' => 'Dismissed', 'pt_BR' => 'Descartado'],
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
            self::PENDING => [
                'en' => 'Conflict needs to be reviewed and resolved',
                'pt_BR' => 'Conflito precisa ser revisado e resolvido',
            ],
            self::RESOLVED => [
                'en' => 'Conflict was resolved with a chosen value',
                'pt_BR' => 'Conflito foi resolvido com um valor escolhido',
            ],
            self::DISMISSED => [
                'en' => 'Conflict was dismissed without resolution',
                'pt_BR' => 'Conflito foi descartado sem resolução',
            ],
        };
    }

    /**
     * Get Lucide icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'AlertTriangle',
            self::RESOLVED => 'CheckCircle',
            self::DISMISSED => 'XCircle',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::RESOLVED => 'green',
            self::DISMISSED => 'gray',
        };
    }

    /**
     * Get badge variant for shadcn/ui Badge component.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::PENDING => 'secondary',
            self::RESOLVED => 'default',
            self::DISMISSED => 'outline',
        };
    }

    /**
     * Check if status requires action.
     */
    public function requiresAction(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Check if status is terminal (no more actions possible).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::RESOLVED, self::DISMISSED => true,
            default => false,
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
            'requires_action' => $this->requiresAction(),
            'is_terminal' => $this->isTerminal(),
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
