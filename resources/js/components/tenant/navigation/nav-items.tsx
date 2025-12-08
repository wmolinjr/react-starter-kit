import { usePermissions } from '@/hooks/shared/use-permissions';
import { usePlan } from '@/hooks/tenant/use-plan';
import admin from '@/routes/tenant/admin';
import { type NavItem } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    BookOpen,
    ClipboardList,
    CreditCard,
    Folder,
    FolderOpen,
    LayoutGrid,
    Network,
    Package,
    Settings,
    Users,
} from 'lucide-react';

/**
 * Footer navigation items (shared across tenant sidebars)
 */
export function useTenantFooterNavItems(): NavItem[] {
    const { t } = useLaravelReactI18n();

    return [
        {
            title: t('sidebar.repository'),
            href: 'https://github.com/laravel/react-starter-kit',
            icon: Folder,
        },
        {
            title: t('sidebar.documentation'),
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];
}

/**
 * Tenant Admin navigation items (permission-based)
 */
export function useTenantAdminNavItems(): NavItem[] {
    const { t } = useLaravelReactI18n();
    const { has } = usePermissions();
    const { hasFeature } = usePlan();

    const navItems: NavItem[] = [
        {
            title: t('sidebar.dashboard'),
            href: admin.dashboard.url(),
            icon: LayoutGrid,
        },
    ];

    // Projects section (permission-based)
    if (has('projects:view')) {
        navItems.push({
            title: t('sidebar.projects'),
            href: admin.projects.index.url(),
            icon: FolderOpen,
        });
    }

    // Team section
    if (has('team:view')) {
        const teamItems: NavItem[] = [];

        if (has('team:view')) {
            teamItems.push({
                title: t('sidebar.members'),
                href: admin.team.index.url(),
            });
        }

        if (has('team:activity')) {
            teamItems.push({
                title: t('sidebar.activities'),
                href: admin.team.activity.url(),
            });
        }

        navItems.push({
            title: t('sidebar.team'),
            href: admin.team.index.url(),
            icon: Users,
            items: teamItems.length > 0 ? teamItems : undefined,
        });
    }

    // Audit Log section (Enterprise feature)
    if (has('audit:view') && hasFeature('auditLog')) {
        navItems.push({
            title: t('sidebar.audit_log'),
            href: admin.audit.index.url(),
            icon: ClipboardList,
        });
    }

    // Billing section
    if (has('billing:view')) {
        const billingItems: NavItem[] = [];

        if (has('billing:view')) {
            billingItems.push({
                title: t('sidebar.subscription'),
                href: admin.billing.index.url(),
            });
        }

        if (has('billing:invoices')) {
            billingItems.push({
                title: t('sidebar.invoices'),
                href: admin.billing.invoices.url(),
            });
        }

        navItems.push({
            title: t('sidebar.billing'),
            href: admin.billing.index.url(),
            icon: CreditCard,
            items: billingItems.length > 0 ? billingItems : undefined,
        });

        // Add-ons (under billing permission)
        navItems.push({
            title: t('sidebar.addons'),
            href: admin.addons.index.url(),
            icon: Package,
        });
    }

    // Settings section
    if (has('settings:view')) {
        const settingsItems: NavItem[] = [];

        if (has('settings:view')) {
            settingsItems.push({
                title: t('sidebar.general'),
                href: admin.settings.index.url(),
            });
        }

        if (has('apiTokens:view')) {
            settingsItems.push({
                title: t('sidebar.api_tokens'),
                href: admin.settings.apiTokens.url(),
            });
        }

        // Custom Roles (Pro+ feature)
        if (has('roles:view') && hasFeature('customRoles')) {
            settingsItems.push({
                title: t('sidebar.roles'),
                href: admin.settings.roles.index.url(),
            });
        }

        // Federation (Enterprise feature)
        if (has('federation:view') && hasFeature('federation')) {
            settingsItems.push({
                title: t('sidebar.federation'),
                href: admin.settings.federation.index.url(),
            });
        }

        if (has('settings:danger')) {
            settingsItems.push({
                title: t('sidebar.danger_zone'),
                href: admin.settings.danger.url(),
            });
        }

        navItems.push({
            title: t('sidebar.settings'),
            href: admin.settings.index.url(),
            icon: Settings,
            items: settingsItems.length > 0 ? settingsItems : undefined,
        });
    }

    return navItems;
}
