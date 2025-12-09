<?php

namespace App\Enums;

/**
 * Federation Sync Strategy Enum
 *
 * Single source of truth for federation sync strategies.
 * Contains all metadata (name, description, icon, color).
 *
 * Usage:
 * - FederationSyncStrategy::values() - Get all values as array
 * - FederationSyncStrategy::MASTER_WINS->value - Get value string
 * - FederationSyncStrategy::MASTER_WINS->label() - Get translatable label
 * - FederationSyncStrategy::toFrontendArray() - Get data for frontend
 */
enum FederationSyncStrategy: string
{
    case MASTER_WINS = 'master_wins';
    case LAST_WRITE_WINS = 'last_write_wins';
    case MANUAL_REVIEW = 'manual_review';

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        return match ($this) {
            self::MASTER_WINS => ['en' => 'Master Wins', 'pt_BR' => 'Mestre Vence'],
            self::LAST_WRITE_WINS => ['en' => 'Last Write Wins', 'pt_BR' => 'Última Escrita Vence'],
            self::MANUAL_REVIEW => ['en' => 'Manual Review', 'pt_BR' => 'Revisão Manual'],
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
            self::MASTER_WINS => [
                'en' => 'Master tenant data always takes precedence in conflicts',
                'pt_BR' => 'Dados do tenant mestre sempre têm precedência em conflitos',
            ],
            self::LAST_WRITE_WINS => [
                'en' => 'Most recent change wins in case of conflicts',
                'pt_BR' => 'Alteração mais recente vence em caso de conflitos',
            ],
            self::MANUAL_REVIEW => [
                'en' => 'Conflicts are stored for manual resolution',
                'pt_BR' => 'Conflitos são armazenados para resolução manual',
            ],
        };
    }

    /**
     * Get Lucide icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::MASTER_WINS => 'Crown',
            self::LAST_WRITE_WINS => 'Clock',
            self::MANUAL_REVIEW => 'UserCheck',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::MASTER_WINS => 'yellow',
            self::LAST_WRITE_WINS => 'blue',
            self::MANUAL_REVIEW => 'purple',
        };
    }

    /**
     * Check if strategy creates conflicts for manual resolution.
     */
    public function createsConflicts(): bool
    {
        return $this === self::MANUAL_REVIEW;
    }

    /**
     * Check if strategy auto-resolves conflicts.
     */
    public function autoResolves(): bool
    {
        return match ($this) {
            self::MASTER_WINS, self::LAST_WRITE_WINS => true,
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
            'creates_conflicts' => $this->createsConflicts(),
            'auto_resolves' => $this->autoResolves(),
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
