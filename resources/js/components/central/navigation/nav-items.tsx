import admin from '@/routes/central/admin';
import { type NavItem } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    BookOpen,
    Building2,
    Folder,
    Shield,
    ShieldCheck,
    Store,
} from 'lucide-react';

/**
 * Footer navigation items (shared across central sidebars)
 */
export function useFooterNavItems(): NavItem[] {
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
 * Central Admin navigation items (super admin)
 *
 * Organized in logical groups:
 * - Dashboard (single item)
 * - Tenant Management (Tenants, Federation)
 * - Catalog (Plans, Add-ons, Bundles, Active Add-ons)
 * - Access Control (Users, Roles)
 */
export function useCentralAdminNavItems(): NavItem[] {
    const { t } = useLaravelReactI18n();

    return [
        // Dashboard - single item
        {
            title: t('sidebar.dashboard'),
            href: admin.dashboard.url(),
            icon: ShieldCheck,
        },

        // Tenant Management group
        {
            title: t('sidebar.tenant_management'),
            href: admin.tenants.index.url(),
            icon: Building2,
            items: [
                {
                    title: t('sidebar.tenants'),
                    href: admin.tenants.index.url(),
                },
                {
                    title: t('sidebar.federation'),
                    href: admin.federation.index.url(),
                },
            ],
        },

        // Catalog group
        {
            title: t('sidebar.catalog'),
            href: admin.plans.index.url(),
            icon: Store,
            items: [
                {
                    title: t('sidebar.plans'),
                    href: admin.plans.index.url(),
                },
                {
                    title: t('sidebar.addon_catalog'),
                    href: admin.catalog.index.url(),
                },
                {
                    title: t('sidebar.bundle_catalog'),
                    href: admin.bundles.index.url(),
                },
                {
                    title: t('sidebar.active_addons'),
                    href: admin.addons.index.url(),
                },
            ],
        },

        // Access Control group
        {
            title: t('sidebar.access_control'),
            href: admin.users.index.url(),
            icon: Shield,
            items: [
                {
                    title: t('sidebar.users'),
                    href: admin.users.index.url(),
                },
                {
                    title: t('sidebar.roles'),
                    href: admin.roles.index.url(),
                },
            ],
        },
    ];
}

