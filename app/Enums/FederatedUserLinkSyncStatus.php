<?php

namespace App\Enums;

/**
 * Federated User Link Sync Status Enum
 *
 * Single source of truth for sync status of tenant links.
 * Contains all metadata (name, description, icon, color, badge variant).
 *
 * Usage:
 * - FederatedUserLinkSyncStatus::values() - Get all values as array
 * - FederatedUserLinkSyncStatus::SYNCED->value - Get value string
 * - FederatedUserLinkSyncStatus::SYNCED->label() - Get translatable label
 * - FederatedUserLinkSyncStatus::toFrontendArray() - Get data for frontend
 */
enum FederatedUserLinkSyncStatus: string
{
    case SYNCED = 'synced';
    case PENDING_SYNC = 'pending_sync';
    case SYNC_FAILED = 'sync_failed';
    case CONFLICT = 'conflict';
    case DISABLED = 'disabled';

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        return match ($this) {
            self::SYNCED => ['en' => 'Synced', 'pt_BR' => 'Sincronizado'],
            self::PENDING_SYNC => ['en' => 'Pending Sync', 'pt_BR' => 'Sincronização Pendente'],
            self::SYNC_FAILED => ['en' => 'Sync Failed', 'pt_BR' => 'Falha na Sincronização'],
            self::CONFLICT => ['en' => 'Conflict', 'pt_BR' => 'Conflito'],
            self::DISABLED => ['en' => 'Disabled', 'pt_BR' => 'Desabilitado'],
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
            self::SYNCED => [
                'en' => 'User data is synchronized with this tenant',
                'pt_BR' => 'Dados do usuário estão sincronizados com este tenant',
            ],
            self::PENDING_SYNC => [
                'en' => 'User data needs to be synchronized',
                'pt_BR' => 'Dados do usuário precisam ser sincronizados',
            ],
            self::SYNC_FAILED => [
                'en' => 'Last synchronization attempt failed',
                'pt_BR' => 'Última tentativa de sincronização falhou',
            ],
            self::CONFLICT => [
                'en' => 'Data conflict detected, requires resolution',
                'pt_BR' => 'Conflito de dados detectado, requer resolução',
            ],
            self::DISABLED => [
                'en' => 'Synchronization is disabled for this link',
                'pt_BR' => 'Sincronização está desabilitada para este link',
            ],
        };
    }

    /**
     * Get Lucide icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::SYNCED => 'CheckCircle',
            self::PENDING_SYNC => 'Clock',
            self::SYNC_FAILED => 'AlertTriangle',
            self::CONFLICT => 'AlertOctagon',
            self::DISABLED => 'XCircle',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::SYNCED => 'green',
            self::PENDING_SYNC => 'blue',
            self::SYNC_FAILED => 'red',
            self::CONFLICT => 'yellow',
            self::DISABLED => 'gray',
        };
    }

    /**
     * Get badge variant for shadcn/ui Badge component.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::SYNCED => 'default',
            self::PENDING_SYNC => 'secondary',
            self::SYNC_FAILED => 'destructive',
            self::CONFLICT => 'secondary',
            self::DISABLED => 'outline',
        };
    }

    /**
     * Check if status indicates sync is needed.
     */
    public function needsSync(): bool
    {
        return match ($this) {
            self::PENDING_SYNC, self::SYNC_FAILED => true,
            default => false,
        };
    }

    /**
     * Check if status indicates a problem.
     */
    public function hasIssue(): bool
    {
        return match ($this) {
            self::SYNC_FAILED, self::CONFLICT => true,
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
            'needs_sync' => $this->needsSync(),
            'has_issue' => $this->hasIssue(),
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
