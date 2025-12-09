<?php

namespace App\Enums;

/**
 * Federated User Status Enum
 *
 * Single source of truth for federated user statuses.
 * Contains all metadata (name, description, icon, color, badge variant).
 *
 * Usage:
 * - FederatedUserStatus::values() - Get all values as array
 * - FederatedUserStatus::ACTIVE->value - Get value string
 * - FederatedUserStatus::ACTIVE->label() - Get translatable label
 * - FederatedUserStatus::toFrontendArray() - Get data for frontend
 */
enum FederatedUserStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case PENDING_REVIEW = 'pending_review';
    case PENDING_MASTER_SYNC = 'pending_master_sync';

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        return match ($this) {
            self::ACTIVE => ['en' => 'Active', 'pt_BR' => 'Ativo', 'es' => 'Activo'],
            self::SUSPENDED => ['en' => 'Suspended', 'pt_BR' => 'Suspenso', 'es' => 'Suspendido'],
            self::PENDING_REVIEW => ['en' => 'Pending Review', 'pt_BR' => 'Aguardando Revisão', 'es' => 'Pendiente de Revisión'],
            self::PENDING_MASTER_SYNC => ['en' => 'Pending Master Sync', 'pt_BR' => 'Aguardando Sincronização do Mestre', 'es' => 'Pendiente de Sincronización Maestra'],
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
            self::ACTIVE => [
                'en' => 'User is active and synchronized across tenants',
                'pt_BR' => 'Usuário está ativo e sincronizado entre tenants',
                'es' => 'El usuario está activo y sincronizado entre tenants',
            ],
            self::SUSPENDED => [
                'en' => 'User is suspended and not syncing',
                'pt_BR' => 'Usuário está suspenso e não sincronizando',
                'es' => 'El usuario está suspendido y no se sincroniza',
            ],
            self::PENDING_REVIEW => [
                'en' => 'User has conflicts that need manual review',
                'pt_BR' => 'Usuário possui conflitos que precisam de revisão manual',
                'es' => 'El usuario tiene conflictos que necesitan revisión manual',
            ],
            self::PENDING_MASTER_SYNC => [
                'en' => 'Awaiting creation in new master tenant after master change',
                'pt_BR' => 'Aguardando criação no novo tenant mestre após troca de mestre',
                'es' => 'Esperando creación en nuevo tenant maestro después del cambio de maestro',
            ],
        };
    }

    /**
     * Get Lucide icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::ACTIVE => 'CheckCircle',
            self::SUSPENDED => 'XCircle',
            self::PENDING_REVIEW => 'AlertTriangle',
            self::PENDING_MASTER_SYNC => 'Clock',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::SUSPENDED => 'red',
            self::PENDING_REVIEW => 'yellow',
            self::PENDING_MASTER_SYNC => 'blue',
        };
    }

    /**
     * Get badge variant for shadcn/ui Badge component.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::ACTIVE => 'default',
            self::SUSPENDED => 'destructive',
            self::PENDING_REVIEW => 'secondary',
            self::PENDING_MASTER_SYNC => 'outline',
        };
    }

    /**
     * Check if status allows sync operations.
     */
    public function canSync(): bool
    {
        return match ($this) {
            self::ACTIVE, self::PENDING_MASTER_SYNC => true,
            default => false,
        };
    }

    /**
     * Check if status is pending (requires action).
     */
    public function isPending(): bool
    {
        return match ($this) {
            self::PENDING_REVIEW, self::PENDING_MASTER_SYNC => true,
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
            'can_sync' => $this->canSync(),
            'is_pending' => $this->isPending(),
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
