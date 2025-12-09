<?php

namespace App\Enums;

/**
 * Plan Feature Enum
 *
 * Single source of truth for all plan features.
 * Contains all metadata (name, description, permissions, etc.)
 *
 * Usage:
 * - PlanFeature::values() - Get all feature keys as array
 * - PlanFeature::CUSTOM_ROLES->value - Get feature key string
 * - PlanFeature::CUSTOM_ROLES->name() - Get translatable name
 * - PlanFeature::toFrontendArray() - Get data for frontend
 * - PlanFeature::keyed() - Get keyed array for lookups
 */
enum PlanFeature: string
{
    case BASE = 'base';
    case PROJECTS = 'projects';
    case CUSTOM_ROLES = 'customRoles';
    case API_ACCESS = 'apiAccess';
    case ADVANCED_REPORTS = 'advancedReports';
    case SSO = 'sso';
    case WHITE_LABEL = 'whiteLabel';
    case AUDIT_LOG = 'auditLog';
    case PRIORITY_SUPPORT = 'prioritySupport';
    case MULTI_LANGUAGE = 'multiLanguage';
    case FEDERATION = 'federation';

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        return match ($this) {
            self::BASE => ['en' => 'Base Features', 'pt_BR' => 'Recursos Base', 'es' => 'Funciones Base'],
            self::PROJECTS => ['en' => 'Projects', 'pt_BR' => 'Projetos', 'es' => 'Proyectos'],
            self::CUSTOM_ROLES => ['en' => 'Custom Roles', 'pt_BR' => 'Roles Personalizados', 'es' => 'Roles Personalizados'],
            self::API_ACCESS => ['en' => 'API Access', 'pt_BR' => 'Acesso à API', 'es' => 'Acceso a API'],
            self::ADVANCED_REPORTS => ['en' => 'Advanced Reports', 'pt_BR' => 'Relatórios Avançados', 'es' => 'Informes Avanzados'],
            self::SSO => ['en' => 'Single Sign-On (SSO)', 'pt_BR' => 'Single Sign-On (SSO)', 'es' => 'Inicio de Sesión Único (SSO)'],
            self::WHITE_LABEL => ['en' => 'White Label', 'pt_BR' => 'White Label', 'es' => 'Marca Blanca'],
            self::AUDIT_LOG => ['en' => 'Audit Log', 'pt_BR' => 'Log de Auditoria', 'es' => 'Registro de Auditoría'],
            self::PRIORITY_SUPPORT => ['en' => 'Priority Support', 'pt_BR' => 'Suporte Prioritário', 'es' => 'Soporte Prioritario'],
            self::MULTI_LANGUAGE => ['en' => 'Multi-Language', 'pt_BR' => 'Multi-Idioma', 'es' => 'Multi-Idioma'],
            self::FEDERATION => ['en' => 'User Federation', 'pt_BR' => 'Federação de Usuários', 'es' => 'Federación de Usuarios'],
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
            self::BASE => [
                'en' => 'Core team and settings management features',
                'pt_BR' => 'Recursos básicos de gerenciamento de equipe e configurações',
                'es' => 'Funciones básicas de gestión de equipo y configuración',
            ],
            self::PROJECTS => [
                'en' => 'Create and manage projects with your team',
                'pt_BR' => 'Crie e gerencie projetos com sua equipe',
                'es' => 'Cree y gestione proyectos con su equipo',
            ],
            self::CUSTOM_ROLES => [
                'en' => 'Create and manage custom roles with granular permissions',
                'pt_BR' => 'Crie e gerencie roles personalizados com permissões granulares',
                'es' => 'Cree y gestione roles personalizados con permisos granulares',
            ],
            self::API_ACCESS => [
                'en' => 'Generate API tokens for external integrations',
                'pt_BR' => 'Gere tokens de API para integrações externas',
                'es' => 'Genere tokens de API para integraciones externas',
            ],
            self::ADVANCED_REPORTS => [
                'en' => 'Access advanced analytics and custom report builder',
                'pt_BR' => 'Acesse análises avançadas e construtor de relatórios personalizados',
                'es' => 'Acceda a análisis avanzados y constructor de informes personalizados',
            ],
            self::SSO => [
                'en' => 'Enable SAML/OIDC authentication for enterprise security',
                'pt_BR' => 'Habilite autenticação SAML/OIDC para segurança empresarial',
                'es' => 'Habilite autenticación SAML/OIDC para seguridad empresarial',
            ],
            self::WHITE_LABEL => [
                'en' => 'Customize branding, colors, and remove platform branding',
                'pt_BR' => 'Personalize marca, cores e remova a marca da plataforma',
                'es' => 'Personalice marca, colores y elimine la marca de la plataforma',
            ],
            self::AUDIT_LOG => [
                'en' => 'Track all user actions and system events',
                'pt_BR' => 'Rastreie todas as ações de usuários e eventos do sistema',
                'es' => 'Rastree todas las acciones de usuarios y eventos del sistema',
            ],
            self::PRIORITY_SUPPORT => [
                'en' => '24/7 priority support with dedicated account manager',
                'pt_BR' => 'Suporte prioritário 24/7 com gerente de conta dedicado',
                'es' => 'Soporte prioritario 24/7 con gerente de cuenta dedicado',
            ],
            self::MULTI_LANGUAGE => [
                'en' => 'Enable multiple language support for your users',
                'pt_BR' => 'Habilite suporte a múltiplos idiomas para seus usuários',
                'es' => 'Habilite soporte a múltiples idiomas para sus usuarios',
            ],
            self::FEDERATION => [
                'en' => 'Sync users across multiple tenants in a federation group',
                'pt_BR' => 'Sincronize usuários entre múltiplos tenants em um grupo de federação',
                'es' => 'Sincronice usuarios entre múltiples tenants en un grupo de federación',
            ],
        };
    }

    /**
     * Get feature category key.
     */
    public function category(): string
    {
        return match ($this) {
            self::BASE => 'other',
            self::PROJECTS => 'modules',
            self::CUSTOM_ROLES, self::SSO, self::AUDIT_LOG, self::FEDERATION => 'security',
            self::API_ACCESS => 'integration',
            self::ADVANCED_REPORTS => 'analytics',
            self::WHITE_LABEL, self::MULTI_LANGUAGE => 'customization',
            self::PRIORITY_SUPPORT => 'support',
        };
    }

    /**
     * Get category label (translated).
     */
    public static function categoryLabel(string $category): array
    {
        return match ($category) {
            'modules' => ['en' => 'Modules', 'pt_BR' => 'Módulos', 'es' => 'Módulos'],
            'security' => ['en' => 'Security', 'pt_BR' => 'Segurança', 'es' => 'Seguridad'],
            'integration' => ['en' => 'Integration', 'pt_BR' => 'Integração', 'es' => 'Integración'],
            'analytics' => ['en' => 'Analytics', 'pt_BR' => 'Análises', 'es' => 'Análisis'],
            'customization' => ['en' => 'Customization', 'pt_BR' => 'Personalização', 'es' => 'Personalización'],
            'support' => ['en' => 'Support', 'pt_BR' => 'Suporte', 'es' => 'Soporte'],
            'collaboration' => ['en' => 'Collaboration', 'pt_BR' => 'Colaboração', 'es' => 'Colaboración'],
            'other' => ['en' => 'Other', 'pt_BR' => 'Outros', 'es' => 'Otros'],
            default => ['en' => ucfirst($category), 'pt_BR' => ucfirst($category), 'es' => ucfirst($category)],
        };
    }

    /**
     * Get translated category label.
     */
    public static function categoryTrans(string $category, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $labels = self::categoryLabel($category);
        return $labels[$locale] ?? $labels['en'];
    }

    /**
     * Get all unique categories with labels.
     */
    public static function categories(): array
    {
        $categories = [];
        foreach (self::cases() as $feature) {
            $cat = $feature->category();
            if (!isset($categories[$cat])) {
                $categories[$cat] = [
                    'value' => $cat,
                    'label' => self::categoryTrans($cat),
                ];
            }
        }
        return array_values($categories);
    }

    /**
     * Get Lucide icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::BASE => 'Settings',
            self::PROJECTS => 'Folder',
            self::CUSTOM_ROLES => 'Shield',
            self::API_ACCESS => 'Key',
            self::ADVANCED_REPORTS => 'BarChart3',
            self::SSO => 'KeyRound',
            self::WHITE_LABEL => 'Palette',
            self::AUDIT_LOG => 'FileText',
            self::PRIORITY_SUPPORT => 'Headphones',
            self::MULTI_LANGUAGE => 'Globe',
            self::FEDERATION => 'Network',
        };
    }

    /**
     * Get color for UI display (based on category).
     */
    public function color(): string
    {
        return match ($this->category()) {
            'modules' => 'blue',
            'security' => 'red',
            'integration' => 'purple',
            'analytics' => 'orange',
            'customization' => 'pink',
            'support' => 'green',
            'collaboration' => 'cyan',
            default => 'gray',
        };
    }

    /**
     * Get badge variant for UI display.
     */
    public function badgeVariant(): string
    {
        return match ($this->category()) {
            'security' => 'destructive',
            'modules' => 'default',
            'integration' => 'secondary',
            default => 'outline',
        };
    }

    /**
     * Get permissions enabled by this feature.
     *
     * @return string[]
     */
    public function permissions(): array
    {
        return match ($this) {
            self::BASE => [
                TenantPermission::TEAM_VIEW->value,
                TenantPermission::TEAM_INVITE->value,
                TenantPermission::TEAM_REMOVE->value,
                TenantPermission::TEAM_MANAGE_ROLES->value,
                TenantPermission::TEAM_ACTIVITY->value,
                TenantPermission::SETTINGS_VIEW->value,
                TenantPermission::SETTINGS_EDIT->value,
                TenantPermission::SETTINGS_DANGER->value,
                TenantPermission::BILLING_VIEW->value,
                TenantPermission::BILLING_MANAGE->value,
                TenantPermission::BILLING_INVOICES->value,
            ],
            self::PROJECTS => [
                TenantPermission::PROJECTS_VIEW->value,
                TenantPermission::PROJECTS_CREATE->value,
                TenantPermission::PROJECTS_EDIT->value,
                TenantPermission::PROJECTS_EDIT_OWN->value,
                TenantPermission::PROJECTS_DELETE->value,
                TenantPermission::PROJECTS_UPLOAD->value,
                TenantPermission::PROJECTS_DOWNLOAD->value,
                TenantPermission::PROJECTS_ARCHIVE->value,
            ],
            self::CUSTOM_ROLES => [
                TenantPermission::ROLES_VIEW->value,
                TenantPermission::ROLES_CREATE->value,
                TenantPermission::ROLES_EDIT->value,
                TenantPermission::ROLES_DELETE->value,
            ],
            self::API_ACCESS => [
                TenantPermission::API_TOKENS_VIEW->value,
                TenantPermission::API_TOKENS_CREATE->value,
                TenantPermission::API_TOKENS_DELETE->value,
            ],
            self::ADVANCED_REPORTS => [
                TenantPermission::REPORTS_VIEW->value,
                TenantPermission::REPORTS_EXPORT->value,
                TenantPermission::REPORTS_SCHEDULE->value,
                TenantPermission::REPORTS_CUSTOMIZE->value,
            ],
            self::SSO => [
                TenantPermission::SSO_CONFIGURE->value,
                TenantPermission::SSO_MANAGE->value,
                TenantPermission::SSO_TEST_CONNECTION->value,
            ],
            self::WHITE_LABEL => [
                TenantPermission::BRANDING_VIEW->value,
                TenantPermission::BRANDING_EDIT->value,
                TenantPermission::BRANDING_PREVIEW->value,
                TenantPermission::BRANDING_PUBLISH->value,
            ],
            self::AUDIT_LOG => [
                TenantPermission::AUDIT_VIEW->value,
                TenantPermission::AUDIT_EXPORT->value,
            ],
            self::PRIORITY_SUPPORT => [],
            self::MULTI_LANGUAGE => [
                TenantPermission::LOCALES_VIEW->value,
                TenantPermission::LOCALES_MANAGE->value,
            ],
            self::FEDERATION => [
                TenantPermission::FEDERATION_VIEW->value,
                TenantPermission::FEDERATION_MANAGE->value,
                TenantPermission::FEDERATION_INVITE->value,
                TenantPermission::FEDERATION_LEAVE->value,
            ],
        };
    }

    /**
     * Whether this feature can be customized by tenants.
     */
    public function isCustomizable(): bool
    {
        return match ($this) {
            self::BASE, self::SSO, self::WHITE_LABEL => false,
            default => true,
        };
    }

    /**
     * Sort order for display.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::BASE => -1,
            self::PROJECTS => 0,
            self::CUSTOM_ROLES => 1,
            self::API_ACCESS => 2,
            self::ADVANCED_REPORTS => 3,
            self::SSO => 4,
            self::WHITE_LABEL => 5,
            self::AUDIT_LOG => 6,
            self::PRIORITY_SUPPORT => 7,
            self::MULTI_LANGUAGE => 8,
            self::FEDERATION => 9,
        };
    }

    /**
     * Get all feature keys as array.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get features that should be shared with frontend.
     * Excludes 'base' which is always true.
     *
     * @return string[]
     */
    public static function frontendFeatures(): array
    {
        return array_filter(
            self::values(),
            fn ($feature) => $feature !== 'base'
        );
    }

    /**
     * Check if a feature key exists.
     */
    public static function exists(string $key): bool
    {
        return self::tryFrom($key) !== null;
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
     * Convert single feature to frontend format.
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
            'category' => $this->category(),
            'permissions' => $this->permissions(),
            'is_customizable' => $this->isCustomizable(),
        ];
    }

    /**
     * Convert all features to frontend array format.
     * Sorted by sortOrder.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toFrontendArray(?string $locale = null): array
    {
        $features = array_map(
            fn (self $f) => $f->toFrontend($locale) + ['sort_order' => $f->sortOrder()],
            self::cases()
        );

        usort($features, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return array_map(function ($f) {
            unset($f['sort_order']);

            return $f;
        }, $features);
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
     * Get all features as a keyed collection (by feature key).
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
