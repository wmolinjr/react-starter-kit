<?php

namespace App\Enums;

/**
 * Tenant Permissions Enum
 *
 * Single source of truth for all tenant-level permissions.
 * Format: category:action
 *
 * Each permission includes its description in multiple languages.
 */
enum TenantPermission: string
{
    // Projects (8 permissions)
    case PROJECTS_VIEW = 'projects:view';
    case PROJECTS_CREATE = 'projects:create';
    case PROJECTS_EDIT = 'projects:edit';
    case PROJECTS_EDIT_OWN = 'projects:editOwn';
    case PROJECTS_DELETE = 'projects:delete';
    case PROJECTS_UPLOAD = 'projects:upload';
    case PROJECTS_DOWNLOAD = 'projects:download';
    case PROJECTS_ARCHIVE = 'projects:archive';

    // Team (5 permissions)
    case TEAM_VIEW = 'team:view';
    case TEAM_INVITE = 'team:invite';
    case TEAM_REMOVE = 'team:remove';
    case TEAM_MANAGE_ROLES = 'team:manageRoles';
    case TEAM_ACTIVITY = 'team:activity';

    // Settings (3 permissions)
    case SETTINGS_VIEW = 'settings:view';
    case SETTINGS_EDIT = 'settings:edit';
    case SETTINGS_DANGER = 'settings:danger';

    // Billing (3 permissions)
    case BILLING_VIEW = 'billing:view';
    case BILLING_MANAGE = 'billing:manage';
    case BILLING_INVOICES = 'billing:invoices';

    // API Tokens (3 permissions)
    case API_TOKENS_VIEW = 'apiTokens:view';
    case API_TOKENS_CREATE = 'apiTokens:create';
    case API_TOKENS_DELETE = 'apiTokens:delete';

    // Custom Roles - Pro+ (4 permissions)
    case ROLES_VIEW = 'roles:view';
    case ROLES_CREATE = 'roles:create';
    case ROLES_EDIT = 'roles:edit';
    case ROLES_DELETE = 'roles:delete';

    // Advanced Reports - Enterprise (4 permissions)
    case REPORTS_VIEW = 'reports:view';
    case REPORTS_EXPORT = 'reports:export';
    case REPORTS_SCHEDULE = 'reports:schedule';
    case REPORTS_CUSTOMIZE = 'reports:customize';

    // SSO - Enterprise (3 permissions)
    case SSO_CONFIGURE = 'sso:configure';
    case SSO_MANAGE = 'sso:manage';
    case SSO_TEST_CONNECTION = 'sso:testConnection';

    // White Label - Enterprise (4 permissions)
    case BRANDING_VIEW = 'branding:view';
    case BRANDING_EDIT = 'branding:edit';
    case BRANDING_PREVIEW = 'branding:preview';
    case BRANDING_PUBLISH = 'branding:publish';

    // Audit Log - Enterprise (2 permissions)
    case AUDIT_VIEW = 'audit:view';
    case AUDIT_EXPORT = 'audit:export';

    // Multi-Language (2 permissions)
    case LOCALES_VIEW = 'locales:view';
    case LOCALES_MANAGE = 'locales:manage';

    // Federation (4 permissions) - Owner only
    case FEDERATION_VIEW = 'federation:view';
    case FEDERATION_MANAGE = 'federation:manage';
    case FEDERATION_INVITE = 'federation:invite';
    case FEDERATION_LEAVE = 'federation:leave';

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        $categoryEnum = $this->categoryEnum();
        $action = $this->action();

        $catName = $categoryEnum?->name() ?? ['en' => ucfirst($this->category()), 'pt_BR' => ucfirst($this->category())];
        $actName = PermissionAction::tryFrom($action)?->name() ?? ['en' => ucfirst($action), 'pt_BR' => ucfirst($action)];

        return [
            'en' => "{$catName['en']}: {$actName['en']}",
            'pt_BR' => "{$catName['pt_BR']}: {$actName['pt_BR']}",
        ];
    }

    /**
     * Get the description for this permission.
     *
     * @return array{en: string, pt_BR: string}
     */
    public function description(): array
    {
        return match ($this) {
            // Projects
            self::PROJECTS_VIEW => ['en' => 'View all projects', 'pt_BR' => 'Visualizar todos os projetos'],
            self::PROJECTS_CREATE => ['en' => 'Create new projects', 'pt_BR' => 'Criar novos projetos'],
            self::PROJECTS_EDIT => ['en' => 'Edit any project', 'pt_BR' => 'Editar qualquer projeto'],
            self::PROJECTS_EDIT_OWN => ['en' => 'Edit own projects only', 'pt_BR' => 'Editar apenas projetos próprios'],
            self::PROJECTS_DELETE => ['en' => 'Delete projects', 'pt_BR' => 'Excluir projetos'],
            self::PROJECTS_UPLOAD => ['en' => 'Upload files', 'pt_BR' => 'Enviar arquivos'],
            self::PROJECTS_DOWNLOAD => ['en' => 'Download files', 'pt_BR' => 'Baixar arquivos'],
            self::PROJECTS_ARCHIVE => ['en' => 'Archive projects', 'pt_BR' => 'Arquivar projetos'],

            // Team
            self::TEAM_VIEW => ['en' => 'View team members', 'pt_BR' => 'Visualizar membros da equipe'],
            self::TEAM_INVITE => ['en' => 'Invite members', 'pt_BR' => 'Convidar membros'],
            self::TEAM_REMOVE => ['en' => 'Remove members', 'pt_BR' => 'Remover membros'],
            self::TEAM_MANAGE_ROLES => ['en' => 'Manage roles', 'pt_BR' => 'Gerenciar papéis'],
            self::TEAM_ACTIVITY => ['en' => 'View activity logs', 'pt_BR' => 'Visualizar logs de atividade'],

            // Settings
            self::SETTINGS_VIEW => ['en' => 'View settings', 'pt_BR' => 'Visualizar configurações'],
            self::SETTINGS_EDIT => ['en' => 'Edit settings', 'pt_BR' => 'Editar configurações'],
            self::SETTINGS_DANGER => ['en' => 'Danger zone access', 'pt_BR' => 'Acesso à zona de perigo'],

            // Billing
            self::BILLING_VIEW => ['en' => 'View billing', 'pt_BR' => 'Visualizar faturamento'],
            self::BILLING_MANAGE => ['en' => 'Manage subscriptions', 'pt_BR' => 'Gerenciar assinaturas'],
            self::BILLING_INVOICES => ['en' => 'Download invoices', 'pt_BR' => 'Baixar faturas'],

            // API Tokens
            self::API_TOKENS_VIEW => ['en' => 'View API tokens', 'pt_BR' => 'Visualizar tokens de API'],
            self::API_TOKENS_CREATE => ['en' => 'Create API tokens', 'pt_BR' => 'Criar tokens de API'],
            self::API_TOKENS_DELETE => ['en' => 'Delete API tokens', 'pt_BR' => 'Excluir tokens de API'],

            // Custom Roles
            self::ROLES_VIEW => ['en' => 'View custom roles', 'pt_BR' => 'Visualizar papéis personalizados'],
            self::ROLES_CREATE => ['en' => 'Create custom roles', 'pt_BR' => 'Criar papéis personalizados'],
            self::ROLES_EDIT => ['en' => 'Edit custom roles', 'pt_BR' => 'Editar papéis personalizados'],
            self::ROLES_DELETE => ['en' => 'Delete custom roles', 'pt_BR' => 'Excluir papéis personalizados'],

            // Advanced Reports
            self::REPORTS_VIEW => ['en' => 'View reports', 'pt_BR' => 'Visualizar relatórios'],
            self::REPORTS_EXPORT => ['en' => 'Export reports', 'pt_BR' => 'Exportar relatórios'],
            self::REPORTS_SCHEDULE => ['en' => 'Schedule reports', 'pt_BR' => 'Agendar relatórios'],
            self::REPORTS_CUSTOMIZE => ['en' => 'Customize reports', 'pt_BR' => 'Personalizar relatórios'],

            // SSO
            self::SSO_CONFIGURE => ['en' => 'Configure SSO', 'pt_BR' => 'Configurar SSO'],
            self::SSO_MANAGE => ['en' => 'Manage SSO providers', 'pt_BR' => 'Gerenciar provedores SSO'],
            self::SSO_TEST_CONNECTION => ['en' => 'Test SSO connection', 'pt_BR' => 'Testar conexão SSO'],

            // White Label
            self::BRANDING_VIEW => ['en' => 'View branding', 'pt_BR' => 'Visualizar marca'],
            self::BRANDING_EDIT => ['en' => 'Edit branding', 'pt_BR' => 'Editar marca'],
            self::BRANDING_PREVIEW => ['en' => 'Preview branding', 'pt_BR' => 'Pré-visualizar marca'],
            self::BRANDING_PUBLISH => ['en' => 'Publish branding', 'pt_BR' => 'Publicar marca'],

            // Audit Log
            self::AUDIT_VIEW => ['en' => 'View audit logs', 'pt_BR' => 'Visualizar logs de auditoria'],
            self::AUDIT_EXPORT => ['en' => 'Export audit logs', 'pt_BR' => 'Exportar logs de auditoria'],

            // Multi-Language
            self::LOCALES_VIEW => ['en' => 'View language settings', 'pt_BR' => 'Visualizar configurações de idioma'],
            self::LOCALES_MANAGE => ['en' => 'Manage language settings', 'pt_BR' => 'Gerenciar configurações de idioma'],

            // Federation
            self::FEDERATION_VIEW => ['en' => 'View federation settings', 'pt_BR' => 'Visualizar configurações de federação'],
            self::FEDERATION_MANAGE => ['en' => 'Manage federation settings', 'pt_BR' => 'Gerenciar configurações de federação'],
            self::FEDERATION_INVITE => ['en' => 'Invite tenants to federation', 'pt_BR' => 'Convidar tenants para federação'],
            self::FEDERATION_LEAVE => ['en' => 'Leave federation group', 'pt_BR' => 'Sair do grupo de federação'],
        };
    }

    /**
     * Get translated description.
     */
    public function trans(?string $locale = null): string
    {
        return $this->translatedDescription($locale);
    }

    /**
     * Get Lucide icon name (based on category).
     */
    public function icon(): string
    {
        return $this->categoryEnum()?->icon() ?? 'Circle';
    }

    /**
     * Get color for UI display (based on category).
     */
    public function color(): string
    {
        return $this->categoryEnum()?->color() ?? 'gray';
    }

    /**
     * Get badge variant for UI display.
     */
    public function badgeVariant(): string
    {
        return $this->categoryEnum()?->badgeVariant() ?? 'outline';
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
     * Get the category string (first part before colon).
     */
    public function category(): string
    {
        return explode(':', $this->value)[0];
    }

    /**
     * Get the category as enum (type-safe).
     */
    public function categoryEnum(): ?PermissionCategory
    {
        return PermissionCategory::fromPermission($this->value);
    }

    /**
     * Get the description for a category.
     *
     * @return array{en: string, pt_BR: string}
     */
    public static function categoryDescription(string $category): array
    {
        $categoryEnum = PermissionCategory::tryFrom($category);

        if ($categoryEnum) {
            return $categoryEnum->name();
        }

        // Fallback for unknown categories
        return ['en' => ucfirst($category), 'pt_BR' => ucfirst($category)];
    }

    /**
     * Get translated category description.
     */
    public static function categoryTrans(string $category, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $descriptions = self::categoryDescription($category);

        return $descriptions[$locale] ?? $descriptions['en'];
    }

    /**
     * Get all unique categories with their descriptions.
     *
     * @return array<string, array{en: string, pt_BR: string}>
     */
    public static function categories(): array
    {
        $categories = [];
        foreach (self::cases() as $permission) {
            $cat = $permission->category();
            if (!isset($categories[$cat])) {
                $categories[$cat] = self::categoryDescription($cat);
            }
        }

        return $categories;
    }

    /**
     * Get the action (second part after colon).
     */
    public function action(): string
    {
        return explode(':', $this->value)[1];
    }

    /**
     * Get all permission values as strings.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get permissions grouped by category.
     */
    public static function byCategory(): array
    {
        $grouped = [];
        foreach (self::cases() as $permission) {
            $grouped[$permission->category()][] = $permission->value;
        }

        return $grouped;
    }

    /**
     * Get actions grouped by category.
     * Returns ['projects' => ['view', 'create', ...], ...]
     */
    public static function actionsByCategory(): array
    {
        $grouped = [];
        foreach (self::cases() as $permission) {
            $grouped[$permission->category()][] = $permission->action();
        }

        return $grouped;
    }

    /**
     * Get actions for a specific category.
     * Returns ['view', 'create', 'edit', ...] or empty array if category doesn't exist.
     */
    public static function actionsFor(string $category): array
    {
        return self::actionsByCategory()[$category] ?? [];
    }

    /**
     * Get description for a permission value string.
     *
     * @return array{en: string, pt_BR: string}
     */
    public static function descriptionFor(string $value): array
    {
        $permission = self::tryFrom($value);

        if ($permission) {
            return $permission->description();
        }

        // Fallback for unknown permissions
        $category = explode(':', $value)[0];

        return [
            'en' => "Manage {$category}",
            'pt_BR' => "Gerenciar {$category}",
        ];
    }

    /**
     * Get all permissions as array for database seeding.
     *
     * @return array<array{name: string, category: string, description: array}>
     */
    public static function toSeederArray(): array
    {
        return array_map(fn(self $p) => [
            'name' => $p->value,
            'category' => $p->category(),
            'description' => $p->description(),
        ], self::cases());
    }

    /**
     * Extract category from permission string.
     * Example: 'projects:view' -> 'projects'
     */
    public static function extractCategory(string $permission): string
    {
        return explode(':', $permission)[0];
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
     * Convert single permission to frontend format.
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
            'action' => $this->action(),
        ];
    }

    /**
     * Convert all permissions to frontend array format.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toFrontendArray(?string $locale = null): array
    {
        return array_map(
            fn (self $permission) => $permission->toFrontend($locale),
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
