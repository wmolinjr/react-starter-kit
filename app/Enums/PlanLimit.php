<?php

namespace App\Enums;

/**
 * Plan Limit Enum
 *
 * Single source of truth for all plan limits.
 * Contains all metadata (name, description, unit, etc.)
 *
 * Usage:
 * - PlanLimit::values() - Get all limit keys as array
 * - PlanLimit::USERS->value - Get limit key string
 * - PlanLimit::USERS->name() - Get translatable name
 * - PlanLimit::USERS->pennantFeatureName() - Get 'maxUsers'
 * - PlanLimit::toFrontendArray() - Get data for frontend
 * - PlanLimit::keyed() - Get keyed array for lookups
 */
enum PlanLimit: string
{
    case USERS = 'users';
    case PROJECTS = 'projects';
    case STORAGE = 'storage';
    case API_CALLS = 'apiCalls';
    case LOG_RETENTION = 'logRetention';
    case FILE_UPLOAD_SIZE = 'fileUploadSize';
    case CUSTOM_ROLES = 'customRoles';
    case LOCALES = 'locales';

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        return match ($this) {
            self::USERS => ['en' => 'User Seats', 'pt_BR' => 'Vagas de Usuário', 'es' => 'Puestos de Usuario'],
            self::PROJECTS => ['en' => 'Projects', 'pt_BR' => 'Projetos', 'es' => 'Proyectos'],
            self::STORAGE => ['en' => 'Storage', 'pt_BR' => 'Armazenamento', 'es' => 'Almacenamiento'],
            self::API_CALLS => ['en' => 'API Calls', 'pt_BR' => 'Chamadas de API', 'es' => 'Llamadas de API'],
            self::LOG_RETENTION => ['en' => 'Log Retention', 'pt_BR' => 'Retenção de Logs', 'es' => 'Retención de Logs'],
            self::FILE_UPLOAD_SIZE => ['en' => 'Max File Size', 'pt_BR' => 'Tamanho Máximo de Arquivo', 'es' => 'Tamaño Máximo de Archivo'],
            self::CUSTOM_ROLES => ['en' => 'Custom Roles', 'pt_BR' => 'Roles Personalizados', 'es' => 'Roles Personalizados'],
            self::LOCALES => ['en' => 'Languages', 'pt_BR' => 'Idiomas', 'es' => 'Idiomas'],
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
            self::USERS => [
                'en' => 'Maximum number of team members',
                'pt_BR' => 'Número máximo de membros da equipe',
                'es' => 'Número máximo de miembros del equipo',
            ],
            self::PROJECTS => [
                'en' => 'Maximum number of active projects',
                'pt_BR' => 'Número máximo de projetos ativos',
                'es' => 'Número máximo de proyectos activos',
            ],
            self::STORAGE => [
                'en' => 'Total storage space available',
                'pt_BR' => 'Espaço total de armazenamento disponível',
                'es' => 'Espacio total de almacenamiento disponible',
            ],
            self::API_CALLS => [
                'en' => 'Monthly API request limit',
                'pt_BR' => 'Limite mensal de requisições de API',
                'es' => 'Límite mensual de solicitudes de API',
            ],
            self::LOG_RETENTION => [
                'en' => 'How long activity logs are kept',
                'pt_BR' => 'Por quanto tempo os logs de atividade são mantidos',
                'es' => 'Por cuánto tiempo se mantienen los logs de actividad',
            ],
            self::FILE_UPLOAD_SIZE => [
                'en' => 'Maximum size per file upload',
                'pt_BR' => 'Tamanho máximo por upload de arquivo',
                'es' => 'Tamaño máximo por carga de archivo',
            ],
            self::CUSTOM_ROLES => [
                'en' => 'Maximum number of custom roles that can be created',
                'pt_BR' => 'Número máximo de roles personalizados que podem ser criados',
                'es' => 'Número máximo de roles personalizados que se pueden crear',
            ],
            self::LOCALES => [
                'en' => 'Maximum number of languages that can be enabled',
                'pt_BR' => 'Número máximo de idiomas que podem ser habilitados',
                'es' => 'Número máximo de idiomas que se pueden habilitar',
            ],
        };
    }

    /**
     * Get unit type (for internal use).
     */
    public function unit(): string
    {
        return match ($this) {
            self::USERS => 'seats',
            self::PROJECTS => 'projects',
            self::STORAGE, self::FILE_UPLOAD_SIZE => 'MB',
            self::API_CALLS => 'requests',
            self::LOG_RETENTION => 'days',
            self::CUSTOM_ROLES => 'roles',
            self::LOCALES => 'locales',
        };
    }

    /**
     * Get translatable unit label (for display).
     *
     * @return array<string, string>
     */
    public function unitLabel(): array
    {
        return match ($this) {
            self::USERS => ['en' => 'users', 'pt_BR' => 'usuários', 'es' => 'usuarios'],
            self::PROJECTS => ['en' => 'projects', 'pt_BR' => 'projetos', 'es' => 'proyectos'],
            self::STORAGE, self::FILE_UPLOAD_SIZE => ['en' => 'MB', 'pt_BR' => 'MB', 'es' => 'MB'],
            self::API_CALLS => ['en' => 'calls/month', 'pt_BR' => 'chamadas/mês', 'es' => 'llamadas/mes'],
            self::LOG_RETENTION => ['en' => 'days', 'pt_BR' => 'dias', 'es' => 'días'],
            self::CUSTOM_ROLES => ['en' => 'roles', 'pt_BR' => 'roles', 'es' => 'roles'],
            self::LOCALES => ['en' => 'languages', 'pt_BR' => 'idiomas', 'es' => 'idiomas'],
        };
    }

    /**
     * Get default value for this limit.
     */
    public function defaultValue(): int
    {
        return match ($this) {
            self::USERS, self::LOCALES => 1,
            self::PROJECTS => 10,
            self::STORAGE => 1024,
            self::API_CALLS, self::CUSTOM_ROLES => 0,
            self::LOG_RETENTION => 30,
            self::FILE_UPLOAD_SIZE => 10,
        };
    }

    /**
     * Whether this limit allows unlimited (-1) value.
     */
    public function allowsUnlimited(): bool
    {
        return match ($this) {
            self::LOG_RETENTION, self::FILE_UPLOAD_SIZE => false,
            default => true,
        };
    }

    /**
     * Get Lucide icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::USERS => 'Users',
            self::PROJECTS => 'Folder',
            self::STORAGE => 'HardDrive',
            self::API_CALLS => 'Activity',
            self::LOG_RETENTION => 'Calendar',
            self::FILE_UPLOAD_SIZE => 'Upload',
            self::CUSTOM_ROLES => 'Shield',
            self::LOCALES => 'Globe',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::USERS => 'blue',
            self::PROJECTS => 'green',
            self::STORAGE => 'purple',
            self::API_CALLS => 'orange',
            self::LOG_RETENTION => 'gray',
            self::FILE_UPLOAD_SIZE => 'cyan',
            self::CUSTOM_ROLES => 'red',
            self::LOCALES => 'pink',
        };
    }

    /**
     * Get badge variant for UI display.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::USERS, self::PROJECTS => 'default',
            self::STORAGE, self::API_CALLS => 'secondary',
            default => 'outline',
        };
    }

    /**
     * Whether this limit can be customized by tenants.
     */
    public function isCustomizable(): bool
    {
        return match ($this) {
            self::LOG_RETENTION, self::FILE_UPLOAD_SIZE => false,
            default => true,
        };
    }

    /**
     * Sort order for display.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::USERS => 1,
            self::PROJECTS => 2,
            self::STORAGE => 3,
            self::API_CALLS => 4,
            self::LOG_RETENTION => 5,
            self::FILE_UPLOAD_SIZE => 6,
            self::CUSTOM_ROLES => 7,
            self::LOCALES => 8,
        };
    }

    /**
     * Get the Pennant feature name for this limit.
     * users -> maxUsers, storage -> storageLimit (special case)
     */
    public function pennantFeatureName(): string
    {
        // Storage has a special Pennant name
        if ($this === self::STORAGE) {
            return 'storageLimit';
        }

        return 'max'.ucfirst($this->value);
    }

    /**
     * Get all limit keys as array.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all Pennant feature names for limits.
     *
     * @return string[]
     */
    public static function pennantFeatureNames(): array
    {
        return array_map(
            fn (self $limit) => $limit->pennantFeatureName(),
            self::cases()
        );
    }

    /**
     * Check if a limit key exists.
     */
    public static function exists(string $key): bool
    {
        return self::tryFrom($key) !== null;
    }

    /**
     * Get limit enum from Pennant feature name.
     * maxUsers -> USERS, storageLimit -> STORAGE
     */
    public static function fromPennantName(string $pennantName): ?self
    {
        if ($pennantName === 'storageLimit') {
            return self::STORAGE;
        }

        if (str_starts_with($pennantName, 'max')) {
            $key = lcfirst(substr($pennantName, 3));

            return self::tryFrom($key);
        }

        return null;
    }

    /**
     * Get translated label for current locale.
     * Alias for translatedName() to match standard enum pattern.
     */
    public function label(?string $locale = null): string
    {
        return $this->translatedName($locale);
    }

    /**
     * Get translated name for current locale.
     */
    public function translatedName(?string $locale = null): string
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

        return $labels[$locale] ?? $labels['en'] ?? '';
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
     * Convert single limit to frontend format.
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
            'unit' => $this->unit(),
            'unit_label' => $this->translatedUnitLabel($locale),
            'default_value' => $this->defaultValue(),
            'allows_unlimited' => $this->allowsUnlimited(),
            'is_customizable' => $this->isCustomizable(),
        ];
    }

    /**
     * Convert all limits to frontend array format.
     * Sorted by sortOrder.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toFrontendArray(?string $locale = null): array
    {
        $limits = array_map(
            fn (self $l) => $l->toFrontend($locale) + ['sort_order' => $l->sortOrder()],
            self::cases()
        );

        usort($limits, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return array_map(function ($l) {
            unset($l['sort_order']);

            return $l;
        }, $limits);
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
     * Get all limits as a keyed collection (by limit key).
     *
     * @return array<string, self>
     */
    public static function keyed(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            self::cases()
        );
    }
}
