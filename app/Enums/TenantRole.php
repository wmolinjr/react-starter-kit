<?php

namespace App\Enums;

/**
 * Tenant Role Enum
 *
 * Single source of truth for tenant roles and their permission rules.
 * Defines the default roles available in each tenant and their access levels.
 *
 * Usage:
 * - TenantRole::OWNER->filterPermissions($permissions)
 * - TenantRole::fromString('admin')->excludedPermissions()
 * - TenantRole::systemRoles() - Get all system default roles
 */
enum TenantRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';

    /**
     * Get translatable name.
     *
     * @return array<string, string>
     */
    public function name(): array
    {
        return match ($this) {
            self::OWNER => ['en' => 'Owner', 'pt_BR' => 'Proprietário'],
            self::ADMIN => ['en' => 'Administrator', 'pt_BR' => 'Administrador'],
            self::MEMBER => ['en' => 'Member', 'pt_BR' => 'Membro'],
        };
    }

    /**
     * Get translatable display name (alias for name()).
     *
     * @return array<string, string>
     *
     * @deprecated Use name() instead
     */
    public function displayName(): array
    {
        return $this->name();
    }

    /**
     * Get translatable description.
     *
     * @return array<string, string>
     */
    public function description(): array
    {
        return match ($this) {
            self::OWNER => [
                'en' => 'Full access to all features including billing and API tokens',
                'pt_BR' => 'Acesso total a todos os recursos incluindo faturamento e tokens de API',
            ],
            self::ADMIN => [
                'en' => 'Manages team and projects, no access to billing or API tokens',
                'pt_BR' => 'Gerencia equipe e projetos, sem acesso a faturamento ou tokens de API',
            ],
            self::MEMBER => [
                'en' => 'View access and can edit own projects',
                'pt_BR' => 'Acesso de visualização e pode editar projetos próprios',
            ],
        };
    }

    /**
     * Get Lucide icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::OWNER => 'Crown',
            self::ADMIN => 'ShieldCheck',
            self::MEMBER => 'User',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::OWNER => 'yellow',
            self::ADMIN => 'blue',
            self::MEMBER => 'gray',
        };
    }

    /**
     * Get badge variant for UI display.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::OWNER => 'default',
            self::ADMIN => 'secondary',
            self::MEMBER => 'outline',
        };
    }

    /**
     * Get translated label for current locale.
     */
    public function label(?string $locale = null): string
    {
        return $this->translatedName($locale);
    }

    /**
     * Get translated display name for current locale.
     */
    public function translatedName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $names = $this->name();

        return $names[$locale] ?? $names['en'];
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
     * Whether this is a system default role (cannot be deleted).
     */
    public function isSystemRole(): bool
    {
        return true; // All enum roles are system defaults
    }

    /**
     * Sort order for display.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::OWNER => 1,
            self::ADMIN => 2,
            self::MEMBER => 3,
        };
    }

    /**
     * Get specific permissions excluded for this role.
     *
     * @return TenantPermission[]
     */
    public function excludedPermissions(): array
    {
        return match ($this) {
            self::OWNER => [],
            self::ADMIN => [
                TenantPermission::BILLING_MANAGE,
                TenantPermission::API_TOKENS_VIEW,
                TenantPermission::API_TOKENS_CREATE,
                TenantPermission::API_TOKENS_DELETE,
                TenantPermission::SETTINGS_DANGER,
            ],
            self::MEMBER => [], // Member uses category exclusion instead
        };
    }

    /**
     * Get categories completely excluded for this role.
     *
     * @return PermissionCategory[]
     */
    public function excludedCategories(): array
    {
        return match ($this) {
            self::OWNER, self::ADMIN => [],
            self::MEMBER => [
                PermissionCategory::ROLES,
                PermissionCategory::BILLING,
                PermissionCategory::API_TOKENS,
                PermissionCategory::AUDIT,
                PermissionCategory::SSO,
                PermissionCategory::BRANDING,
                PermissionCategory::REPORTS,
            ],
        };
    }

    /**
     * Get permissions explicitly allowed for this role (beyond default rules).
     *
     * @return TenantPermission[]
     */
    public function explicitlyAllowedPermissions(): array
    {
        return match ($this) {
            self::OWNER, self::ADMIN => [],
            self::MEMBER => [
                TenantPermission::PROJECTS_EDIT_OWN,
            ],
        };
    }

    /**
     * Get action patterns allowed for this role.
     *
     * @return string[]
     */
    public function allowedActionPatterns(): array
    {
        return match ($this) {
            self::OWNER, self::ADMIN => ['*'], // All actions
            self::MEMBER => ['view', 'download'], // Only view and download
        };
    }

    /**
     * Filter permissions based on this role's rules.
     *
     * @param  array<string>  $permissions  Available permission strings
     * @return array<string>  Filtered permissions for this role
     */
    public function filterPermissions(array $permissions): array
    {
        return match ($this) {
            self::OWNER => $permissions,
            self::ADMIN => $this->filterForAdmin($permissions),
            self::MEMBER => $this->filterForMember($permissions),
        };
    }

    /**
     * Filter permissions for admin role.
     */
    private function filterForAdmin(array $permissions): array
    {
        $excludedValues = array_map(
            fn (TenantPermission $p) => $p->value,
            $this->excludedPermissions()
        );

        return array_values(array_filter($permissions, function ($p) use ($excludedValues) {
            // Exclude specific permissions
            if (in_array($p, $excludedValues, true)) {
                return false;
            }

            // Exclude entire apiTokens category
            if (PermissionCategory::API_TOKENS->matches($p)) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Filter permissions for member role.
     */
    private function filterForMember(array $permissions): array
    {
        $excludedCategories = $this->excludedCategories();
        $explicitlyAllowed = array_map(
            fn (TenantPermission $p) => $p->value,
            $this->explicitlyAllowedPermissions()
        );
        $allowedPatterns = $this->allowedActionPatterns();

        return array_values(array_filter($permissions, function ($p) use ($excludedCategories, $explicitlyAllowed, $allowedPatterns) {
            // Check if permission is explicitly allowed
            if (in_array($p, $explicitlyAllowed, true)) {
                return true;
            }

            // Check if permission is in excluded category (type-safe)
            foreach ($excludedCategories as $category) {
                if ($category->matches($p)) {
                    return false;
                }
            }

            // Check if action matches allowed patterns
            foreach ($allowedPatterns as $pattern) {
                if ($pattern === '*' || str_contains($p, ":{$pattern}")) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * Get TenantRole from string value.
     */
    public static function fromString(string $role): ?self
    {
        return self::tryFrom($role);
    }

    /**
     * Get all role values as strings.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all system default roles.
     *
     * @return self[]
     */
    public static function systemRoles(): array
    {
        return self::cases();
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
     * Convert single role to frontend format.
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
            'is_system' => $this->isSystemRole(),
        ];
    }

    /**
     * Convert all roles to frontend array format.
     * Sorted by sortOrder.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toFrontendArray(?string $locale = null): array
    {
        $roles = array_map(
            fn (self $role) => $role->toFrontend($locale) + ['sort_order' => $role->sortOrder()],
            self::cases()
        );

        usort($roles, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return array_map(function ($r) {
            unset($r['sort_order']);

            return $r;
        }, $roles);
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
