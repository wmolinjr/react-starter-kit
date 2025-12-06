<?php

namespace App\Enums;

/**
 * Addon Status Enum
 *
 * Single source of truth for addon subscription statuses.
 * Contains all metadata (name, description, icon, color).
 *
 * Usage:
 * - AddonStatus::values() - Get all values as array
 * - AddonStatus::ACTIVE->value - Get value string
 * - AddonStatus::ACTIVE->label() - Get translatable label
 * - AddonStatus::toFrontendArray() - Get data for frontend
 */
enum AddonStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case CANCELED = 'canceled';
    case EXPIRED = 'expired';
    case FAILED = 'failed';

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        return match ($this) {
            self::PENDING => ['en' => 'Pending', 'pt_BR' => 'Pendente'],
            self::ACTIVE => ['en' => 'Active', 'pt_BR' => 'Ativo'],
            self::CANCELED => ['en' => 'Canceled', 'pt_BR' => 'Cancelado'],
            self::EXPIRED => ['en' => 'Expired', 'pt_BR' => 'Expirado'],
            self::FAILED => ['en' => 'Failed', 'pt_BR' => 'Falhou'],
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
                'en' => 'Awaiting activation',
                'pt_BR' => 'Aguardando ativação',
            ],
            self::ACTIVE => [
                'en' => 'Currently active and in use',
                'pt_BR' => 'Atualmente ativo e em uso',
            ],
            self::CANCELED => [
                'en' => 'Subscription was canceled',
                'pt_BR' => 'Assinatura foi cancelada',
            ],
            self::EXPIRED => [
                'en' => 'Subscription period ended',
                'pt_BR' => 'Período de assinatura encerrado',
            ],
            self::FAILED => [
                'en' => 'Payment or activation failed',
                'pt_BR' => 'Pagamento ou ativação falhou',
            ],
        };
    }

    /**
     * Get Lucide icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'Clock',
            self::ACTIVE => 'CheckCircle',
            self::CANCELED => 'XCircle',
            self::EXPIRED => 'CalendarX',
            self::FAILED => 'AlertTriangle',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::ACTIVE => 'green',
            self::CANCELED => 'gray',
            self::EXPIRED => 'orange',
            self::FAILED => 'red',
        };
    }

    /**
     * Check if status allows addon usage.
     */
    public function isUsable(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Check if status is terminal (cannot change).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::CANCELED, self::EXPIRED, self::FAILED => true,
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
            'is_usable' => $this->isUsable(),
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
}
