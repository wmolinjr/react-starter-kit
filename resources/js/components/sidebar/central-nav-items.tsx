import admin from '@/routes/central/admin';
import { type NavItem } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    BookOpen,
    Building2,
    CreditCard,
    Folder,
    Package,
    PackageOpen,
    Settings,
    Shield,
    ShieldCheck,
    Users,
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
 */
export function useCentralAdminNavItems(): NavItem[] {
    const { t } = useLaravelReactI18n();

    return [
        {
            title: t('sidebar.dashboard'),
            href: admin.dashboard.url(),
            icon: ShieldCheck,
        },
        {
            title: t('sidebar.users'),
            href: admin.users.index.url(),
            icon: Users,
        },
        {
            title: t('sidebar.tenants'),
            href: admin.tenants.index.url(),
            icon: Building2,
        },
        {
            title: t('sidebar.active_addons'),
            href: admin.addons.index.url(),
            icon: Package,
        },
        {
            title: t('sidebar.addon_catalog'),
            href: admin.catalog.index.url(),
            icon: Package,
        },
        {
            title: t('sidebar.bundle_catalog'),
            href: admin.bundles.index.url(),
            icon: PackageOpen,
        },
        {
            title: t('sidebar.plan_catalog'),
            href: admin.plans.index.url(),
            icon: CreditCard,
        },
        {
            title: t('sidebar.roles'),
            href: admin.roles.index.url(),
            icon: Shield,
        },
        {
            title: t('sidebar.settings'),
            href: admin.settings.profile.edit.url(),
            icon: Settings,
        },
    ];
}

