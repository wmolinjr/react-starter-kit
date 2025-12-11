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
    Wallet,
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
            title: t('sidebar.item.dashboard'),
            href: admin.dashboard.url(),
            icon: ShieldCheck,
        },

        // Tenant Management group
        {
            title: t('sidebar.group.tenant_management'),
            href: admin.tenants.index.url(),
            icon: Building2,
            items: [
                {
                    title: t('sidebar.item.tenants'),
                    href: admin.tenants.index.url(),
                },
                {
                    title: t('sidebar.item.federation'),
                    href: admin.federation.index.url(),
                },
            ],
        },

        // Catalog group
        {
            title: t('sidebar.group.catalog'),
            href: admin.plans.index.url(),
            icon: Store,
            items: [
                {
                    title: t('sidebar.item.plans'),
                    href: admin.plans.index.url(),
                },
                {
                    title: t('sidebar.item.addon_catalog'),
                    href: admin.catalog.index.url(),
                },
                {
                    title: t('sidebar.item.bundle_catalog'),
                    href: admin.bundles.index.url(),
                },
                {
                    title: t('sidebar.item.active_addons'),
                    href: admin.addons.index.url(),
                },
            ],
        },

        // Access Control group
        {
            title: t('sidebar.group.access_control'),
            href: admin.users.index.url(),
            icon: Shield,
            items: [
                {
                    title: t('sidebar.item.users'),
                    href: admin.users.index.url(),
                },
                {
                    title: t('sidebar.item.roles'),
                    href: admin.roles.index.url(),
                },
            ],
        },

        // Audit Log - single item
        {
            title: t('sidebar.item.audit_log'),
            href: admin.audit.index.url(),
            icon: ClipboardList,
        },

        // Payments - single item
        {
            title: t('sidebar.item.payments'),
            href: admin.payments.index.url(),
            icon: CreditCard,
        },

        // Payment Settings - single item
        {
            title: t('sidebar.item.payment_settings'),
            href: admin.paymentSettings.index.url(),
            icon: Wallet,
        },
    ];
}

