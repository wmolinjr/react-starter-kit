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

        billingItems.push({
            title: t('sidebar.subscription'),
            href: admin.billing.index.url(),
        });

        billingItems.push({
            title: t('sidebar.plans'),
            href: admin.billing.plans.url(),
        });

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
            items: billingItems,
        });

        // Add-ons section (under billing permission)
        const addonItems: NavItem[] = [];

        addonItems.push({
            title: t('sidebar.marketplace'),
            href: admin.addons.index.url(),
        });

        addonItems.push({
            title: t('sidebar.bundles'),
            href: admin.billing.bundles.url(),
        });

        addonItems.push({
            title: t('sidebar.usage'),
            href: admin.addons.usage.url(),
        });

        navItems.push({
            title: t('sidebar.addons'),
            href: admin.addons.index.url(),
            icon: Package,
            items: addonItems,
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

        // Branding (Enterprise feature)
        if (has('branding:view') && hasFeature('whiteLabel')) {
            settingsItems.push({
                title: t('tenant.settings.branding'),
                href: admin.settings.branding.url(),
            });
        }

        // Domains
        if (has('settings:edit')) {
            settingsItems.push({
                title: t('tenant.settings.domains'),
                href: admin.settings.domains.url(),
            });
        }

        // Config
        if (has('settings:edit')) {
            settingsItems.push({
                title: t('tenant.config.title'),
                href: admin.settings.config.url(),
            });
        }

        if (has('apiTokens:view')) {
            settingsItems.push({
                title: t('tenant.settings.api_tokens'),
                href: admin.settings.apiTokens.url(),
            });
        }

        // Custom Roles (Pro+ feature)
        if (has('roles:view') && hasFeature('customRoles')) {
            settingsItems.push({
                title: t('tenant.settings.custom_roles'),
                href: admin.settings.roles.index.url(),
            });
        }

        // Federation (Enterprise feature)
        if (has('federation:view') && hasFeature('federation')) {
            settingsItems.push({
                title: t('tenant.settings.federation'),
                href: admin.settings.federation.index.url(),
            });
        }

        if (has('settings:danger')) {
            settingsItems.push({
                title: t('tenant.settings.danger_zone'),
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
