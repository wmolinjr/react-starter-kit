<?php

namespace App\Enums;

/**
 * Central Permissions Enum
 *
 * Single source of truth for all central admin permissions.
 * These are used in the central admin panel for Super Admins.
 * Stored in central database only.
 * Format: category:action
 *
 * Each permission includes its description in multiple languages.
 */
enum CentralPermission: string
{
    // Tenant Management (5 permissions)
    case TENANTS_VIEW = 'tenants:view';
    case TENANTS_SHOW = 'tenants:show';
    case TENANTS_EDIT = 'tenants:edit';
    case TENANTS_DELETE = 'tenants:delete';
    case TENANTS_IMPERSONATE = 'tenants:impersonate';

    // User Management (4 permissions)
    case USERS_VIEW = 'users:view';
    case USERS_SHOW = 'users:show';
    case USERS_EDIT = 'users:edit';
    case USERS_DELETE = 'users:delete';

    // Plan Catalog Management (5 permissions)
    case PLANS_VIEW = 'plans:view';
    case PLANS_CREATE = 'plans:create';
    case PLANS_EDIT = 'plans:edit';
    case PLANS_DELETE = 'plans:delete';
    case PLANS_SYNC = 'plans:sync';

    // Addon Catalog Management (5 permissions)
    case CATALOG_VIEW = 'catalog:view';
    case CATALOG_CREATE = 'catalog:create';
    case CATALOG_EDIT = 'catalog:edit';
    case CATALOG_DELETE = 'catalog:delete';
    case CATALOG_SYNC = 'catalog:sync';

    // Tenant Addons Management (4 permissions)
    case ADDONS_VIEW = 'addons:view';
    case ADDONS_REVENUE = 'addons:revenue';
    case ADDONS_GRANT = 'addons:grant';
    case ADDONS_REVOKE = 'addons:revoke';

    // Central Roles Management (4 permissions)
    case ROLES_VIEW = 'roles:view';
    case ROLES_CREATE = 'roles:create';
    case ROLES_EDIT = 'roles:edit';
    case ROLES_DELETE = 'roles:delete';

    // System Settings (3 permissions)
    case SYSTEM_VIEW = 'system:view';
    case SYSTEM_EDIT = 'system:edit';
    case SYSTEM_LOGS = 'system:logs';

    // Federation Management (5 permissions)
    case FEDERATION_VIEW = 'federation:view';
    case FEDERATION_CREATE = 'federation:create';
    case FEDERATION_EDIT = 'federation:edit';
    case FEDERATION_DELETE = 'federation:delete';
    case FEDERATION_MANAGE_CONFLICTS = 'federation:manageConflicts';

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        $category = $this->category();
        $action = $this->action();

        $categoryNames = [
            'tenants' => ['en' => 'Tenants', 'pt_BR' => 'Tenants'],
            'users' => ['en' => 'Users', 'pt_BR' => 'Usuários'],
            'plans' => ['en' => 'Plans', 'pt_BR' => 'Planos'],
            'catalog' => ['en' => 'Catalog', 'pt_BR' => 'Catálogo'],
            'addons' => ['en' => 'Addons', 'pt_BR' => 'Add-ons'],
            'roles' => ['en' => 'Roles', 'pt_BR' => 'Papéis'],
            'system' => ['en' => 'System', 'pt_BR' => 'Sistema'],
            'federation' => ['en' => 'Federation', 'pt_BR' => 'Federação'],
        ];

        $actionNames = [
            'view' => ['en' => 'View', 'pt_BR' => 'Visualizar'],
            'show' => ['en' => 'Show Details', 'pt_BR' => 'Ver Detalhes'],
            'create' => ['en' => 'Create', 'pt_BR' => 'Criar'],
            'edit' => ['en' => 'Edit', 'pt_BR' => 'Editar'],
            'delete' => ['en' => 'Delete', 'pt_BR' => 'Excluir'],
            'sync' => ['en' => 'Sync', 'pt_BR' => 'Sincronizar'],
            'impersonate' => ['en' => 'Impersonate', 'pt_BR' => 'Personificar'],
            'logs' => ['en' => 'Logs', 'pt_BR' => 'Logs'],
            'revenue' => ['en' => 'Revenue', 'pt_BR' => 'Receita'],
            'grant' => ['en' => 'Grant', 'pt_BR' => 'Conceder'],
            'revoke' => ['en' => 'Revoke', 'pt_BR' => 'Revogar'],
            'manageConflicts' => ['en' => 'Manage Conflicts', 'pt_BR' => 'Gerenciar Conflitos'],
        ];

        $catName = $categoryNames[$category] ?? ['en' => ucfirst($category), 'pt_BR' => ucfirst($category)];
        $actName = $actionNames[$action] ?? ['en' => ucfirst($action), 'pt_BR' => ucfirst($action)];

        return [
            'en' => "{$catName['en']}: {$actName['en']}",
            'pt_BR' => "{$catName['pt_BR']}: {$actName['pt_BR']}",
        ];
    }

    /**
     * Get the description for this permission.
     *
     * @return array<string, string>
     */
    public function description(): array
    {
        return match ($this) {
            // Tenants
            self::TENANTS_VIEW => ['en' => 'View all tenants', 'pt_BR' => 'Visualizar todos os tenants'],
            self::TENANTS_SHOW => ['en' => 'View tenant details', 'pt_BR' => 'Visualizar detalhes do tenant'],
            self::TENANTS_EDIT => ['en' => 'Edit tenant settings', 'pt_BR' => 'Editar configurações do tenant'],
            self::TENANTS_DELETE => ['en' => 'Delete tenants', 'pt_BR' => 'Excluir tenants'],
            self::TENANTS_IMPERSONATE => ['en' => 'Impersonate tenant users', 'pt_BR' => 'Personificar usuários de tenants'],

            // Users
            self::USERS_VIEW => ['en' => 'View all users', 'pt_BR' => 'Visualizar todos os usuários'],
            self::USERS_SHOW => ['en' => 'View user details', 'pt_BR' => 'Visualizar detalhes do usuário'],
            self::USERS_EDIT => ['en' => 'Edit user details', 'pt_BR' => 'Editar detalhes do usuário'],
            self::USERS_DELETE => ['en' => 'Delete users', 'pt_BR' => 'Excluir usuários'],

            // Plans
            self::PLANS_VIEW => ['en' => 'View all plans', 'pt_BR' => 'Visualizar todos os planos'],
            self::PLANS_CREATE => ['en' => 'Create new plans', 'pt_BR' => 'Criar novos planos'],
            self::PLANS_EDIT => ['en' => 'Edit plans', 'pt_BR' => 'Editar planos'],
            self::PLANS_DELETE => ['en' => 'Delete plans', 'pt_BR' => 'Excluir planos'],
            self::PLANS_SYNC => ['en' => 'Sync plans with Stripe', 'pt_BR' => 'Sincronizar planos com Stripe'],

            // Catalog
            self::CATALOG_VIEW => ['en' => 'View addon catalog', 'pt_BR' => 'Visualizar catálogo de add-ons'],
            self::CATALOG_CREATE => ['en' => 'Create new addons', 'pt_BR' => 'Criar novos add-ons'],
            self::CATALOG_EDIT => ['en' => 'Edit addons', 'pt_BR' => 'Editar add-ons'],
            self::CATALOG_DELETE => ['en' => 'Delete addons', 'pt_BR' => 'Excluir add-ons'],
            self::CATALOG_SYNC => ['en' => 'Sync addons with Stripe', 'pt_BR' => 'Sincronizar add-ons com Stripe'],

            // Addons
            self::ADDONS_VIEW => ['en' => 'View tenant addons', 'pt_BR' => 'Visualizar add-ons de tenants'],
            self::ADDONS_REVENUE => ['en' => 'View addon revenue reports', 'pt_BR' => 'Visualizar relatórios de receita de add-ons'],
            self::ADDONS_GRANT => ['en' => 'Grant addons to tenants', 'pt_BR' => 'Conceder add-ons a tenants'],
            self::ADDONS_REVOKE => ['en' => 'Revoke addons from tenants', 'pt_BR' => 'Revogar add-ons de tenants'],

            // Roles
            self::ROLES_VIEW => ['en' => 'View central roles', 'pt_BR' => 'Visualizar papéis centrais'],
            self::ROLES_CREATE => ['en' => 'Create central roles', 'pt_BR' => 'Criar papéis centrais'],
            self::ROLES_EDIT => ['en' => 'Edit central roles', 'pt_BR' => 'Editar papéis centrais'],
            self::ROLES_DELETE => ['en' => 'Delete central roles', 'pt_BR' => 'Excluir papéis centrais'],

            // System
            self::SYSTEM_VIEW => ['en' => 'View system settings', 'pt_BR' => 'Visualizar configurações do sistema'],
            self::SYSTEM_EDIT => ['en' => 'Edit system settings', 'pt_BR' => 'Editar configurações do sistema'],
            self::SYSTEM_LOGS => ['en' => 'View system logs', 'pt_BR' => 'Visualizar logs do sistema'],

            // Federation
            self::FEDERATION_VIEW => ['en' => 'View federation groups', 'pt_BR' => 'Visualizar grupos de federação'],
            self::FEDERATION_CREATE => ['en' => 'Create federation groups', 'pt_BR' => 'Criar grupos de federação'],
            self::FEDERATION_EDIT => ['en' => 'Edit federation groups', 'pt_BR' => 'Editar grupos de federação'],
            self::FEDERATION_DELETE => ['en' => 'Delete federation groups', 'pt_BR' => 'Excluir grupos de federação'],
            self::FEDERATION_MANAGE_CONFLICTS => ['en' => 'Manage federation conflicts', 'pt_BR' => 'Gerenciar conflitos de federação'],
        };
    }

    /**
     * Get Lucide icon name (based on category).
     */
    public function icon(): string
    {
        return match ($this->category()) {
            'tenants' => 'Building2',
            'users' => 'Users',
            'plans' => 'CreditCard',
            'catalog' => 'Package',
            'addons' => 'Puzzle',
            'roles' => 'Shield',
            'system' => 'Settings',
            'federation' => 'Network',
            default => 'Circle',
        };
    }

    /**
     * Get color for UI display (based on category).
     */
    public function color(): string
    {
        return match ($this->category()) {
            'tenants' => 'blue',
            'users' => 'purple',
            'plans' => 'green',
            'catalog' => 'orange',
            'addons' => 'pink',
            'roles' => 'yellow',
            'system' => 'gray',
            'federation' => 'cyan',
            default => 'gray',
        };
    }

    /**
     * Get badge variant for UI display.
     */
    public function badgeVariant(): string
    {
        return match ($this->category()) {
            'tenants', 'users' => 'default',
            'plans', 'catalog', 'addons' => 'secondary',
            'roles', 'system' => 'outline',
            'federation' => 'default',
            default => 'outline',
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
     * Get translated description.
     */
    public function trans(?string $locale = null): string
    {
        return $this->translatedDescription($locale);
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
     * Get the category (first part before colon).
     */
    public function category(): string
    {
        return explode(':', $this->value)[0];
    }

    /**
     * Get the description for a category.
     *
     * @return array{en: string, pt_BR: string}
     */
    public static function categoryDescription(string $category): array
    {
        return match ($category) {
            'tenants' => ['en' => 'Tenants', 'pt_BR' => 'Tenants'],
            'users' => ['en' => 'Users', 'pt_BR' => 'Usuários'],
            'plans' => ['en' => 'Plans', 'pt_BR' => 'Planos'],
            'catalog' => ['en' => 'Addon Catalog', 'pt_BR' => 'Catálogo de Add-ons'],
            'addons' => ['en' => 'Tenant Addons', 'pt_BR' => 'Add-ons de Tenants'],
            'roles' => ['en' => 'Central Roles', 'pt_BR' => 'Papéis Centrais'],
            'system' => ['en' => 'System', 'pt_BR' => 'Sistema'],
            'federation' => ['en' => 'Federation', 'pt_BR' => 'Federação'],
            default => ['en' => ucfirst($category), 'pt_BR' => ucfirst($category)],
        };
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
     * Example: 'tenants:view' -> 'tenants'
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
