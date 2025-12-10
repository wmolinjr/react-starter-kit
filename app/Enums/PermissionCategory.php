<?php

namespace App\Enums;

/**
 * Permission Category Enum
 *
 * Single source of truth for permission categories.
 * Used to categorize permissions and provide type-safe category references.
 *
 * Usage:
 * - PermissionCategory::PROJECTS->value // 'projects'
 * - PermissionCategory::PROJECTS->prefix() // 'projects:'
 * - PermissionCategory::fromPermission('projects:view') // PermissionCategory::PROJECTS
 */
enum PermissionCategory: string
{
    case PROJECTS = 'projects';
    case TEAM = 'team';
    case SETTINGS = 'settings';
    case BILLING = 'billing';
    case API_TOKENS = 'apiTokens';
    case ROLES = 'roles';
    case REPORTS = 'reports';
    case SSO = 'sso';
    case BRANDING = 'branding';
    case AUDIT = 'audit';
    case LOCALES = 'locales';
    case FEDERATION = 'federation';

    /**
     * Get the prefix for permission matching (e.g., 'projects:').
     */
    public function prefix(): string
    {
        return $this->value.':';
    }

    /**
     * Check if a permission string belongs to this category.
     */
    public function matches(string $permission): bool
    {
        return str_starts_with($permission, $this->prefix());
    }

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        return match ($this) {
            self::PROJECTS => ['en' => 'Projects', 'pt_BR' => 'Projetos', 'es' => 'Proyectos'],
            self::TEAM => ['en' => 'Team', 'pt_BR' => 'Equipe', 'es' => 'Equipo'],
            self::SETTINGS => ['en' => 'Settings', 'pt_BR' => 'Configurações', 'es' => 'Configuración'],
            self::BILLING => ['en' => 'Billing', 'pt_BR' => 'Faturamento', 'es' => 'Facturación'],
            self::API_TOKENS => ['en' => 'API Tokens', 'pt_BR' => 'Tokens de API', 'es' => 'Tokens de API'],
            self::ROLES => ['en' => 'Custom Roles', 'pt_BR' => 'Papéis Personalizados', 'es' => 'Roles Personalizados'],
            self::REPORTS => ['en' => 'Reports', 'pt_BR' => 'Relatórios', 'es' => 'Informes'],
            self::SSO => ['en' => 'Single Sign-On', 'pt_BR' => 'Login Único (SSO)', 'es' => 'Inicio de Sesión Único (SSO)'],
            self::BRANDING => ['en' => 'Branding', 'pt_BR' => 'Marca', 'es' => 'Marca'],
            self::AUDIT => ['en' => 'Audit Log', 'pt_BR' => 'Log de Auditoria', 'es' => 'Registro de Auditoría'],
            self::LOCALES => ['en' => 'Languages', 'pt_BR' => 'Idiomas', 'es' => 'Idiomas'],
            self::FEDERATION => ['en' => 'Federation', 'pt_BR' => 'Federação', 'es' => 'Federación'],
        };
    }

    /**
     * Get translated label for current locale.
     */
    public function label(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $names = $this->name();

        return $names[$locale] ?? $names['en'];
    }

    /**
     * Get Lucide icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::PROJECTS => 'Folder',
            self::TEAM => 'Users',
            self::SETTINGS => 'Settings',
            self::BILLING => 'CreditCard',
            self::API_TOKENS => 'Key',
            self::ROLES => 'Shield',
            self::REPORTS => 'BarChart3',
            self::SSO => 'Lock',
            self::BRANDING => 'Palette',
            self::AUDIT => 'FileText',
            self::LOCALES => 'Globe',
            self::FEDERATION => 'Network',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::PROJECTS => 'blue',
            self::TEAM => 'purple',
            self::SETTINGS => 'gray',
            self::BILLING => 'green',
            self::API_TOKENS => 'orange',
            self::ROLES => 'yellow',
            self::REPORTS => 'cyan',
            self::SSO => 'red',
            self::BRANDING => 'pink',
            self::AUDIT => 'gray',
            self::LOCALES => 'blue',
            self::FEDERATION => 'cyan',
        };
    }

    /**
     * Get badge variant for UI display.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::PROJECTS, self::TEAM, self::FEDERATION => 'default',
            self::BILLING, self::API_TOKENS => 'secondary',
            self::ROLES, self::SSO => 'destructive',
            default => 'outline',
        };
    }

    /**
     * Extract category from a permission string.
     *
     * @return self|null Returns null if category not found
     */
    public static function fromPermission(string $permission): ?self
    {
        $categoryValue = explode(':', $permission)[0];

        return self::tryFrom($categoryValue);
    }

    /**
     * Get all category values as strings.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Convert single category to frontend format.
     *
     * @return array<string, mixed>
     */
    public function toFrontend(?string $locale = null): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label($locale),
            'icon' => $this->icon(),
            'color' => $this->color(),
            'badge_variant' => $this->badgeVariant(),
        ];
    }

    /**
     * Convert all categories to frontend array format.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toFrontendArray(?string $locale = null): array
    {
        return array_map(
            fn (self $category) => $category->toFrontend($locale),
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
