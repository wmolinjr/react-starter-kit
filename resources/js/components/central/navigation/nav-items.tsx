import admin from '@/routes/central/admin';
import { type NavItem } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    Building2,
    ClipboardList,
    CreditCard,
    Gauge,
    Shield,
    ShieldCheck,
    Store,
    Telescope,
} from 'lucide-react';

/**
 * Footer navigation items (shared across central sidebars)
 * Links to development/monitoring tools (only accessible by super admins)
 */
export function useFooterNavItems(): NavItem[] {
    return [
        {
            title: 'Telescope',
            href: '/telescope',
            icon: Telescope,
        },
        {
            title: 'Horizon',
            href: '/horizon',
            icon: Gauge,
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

        // Audit Log - single item
        {
            title: t('sidebar.audit_log'),
            href: admin.audit.index.url(),
            icon: ClipboardList,
        },

        // Payments - single item
        {
            title: t('sidebar.payments'),
            href: admin.payments.index.url(),
            icon: CreditCard,
        },
    ];
}

