import { type NavItem } from '@/types';
import customer from '@/routes/central/account';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    Building2,
    CreditCard,
    LayoutDashboard,
    Receipt,
    User,
} from 'lucide-react';

/**
 * Customer Portal main navigation items
 *
 * Organized for billing portal experience:
 * - Dashboard (overview)
 * - Workspaces (tenant management)
 * - Payment Methods (billing)
 * - Invoices (billing history)
 */
export function useCustomerNavItems(): NavItem[] {
    const { t } = useLaravelReactI18n();

    return [
        {
            title: t('customer.dashboard.title'),
            href: customer.dashboard.url(),
            icon: LayoutDashboard,
        },
        {
            title: t('customer.workspace.title'),
            href: customer.tenants.index.url(),
            icon: Building2,
        },
        {
            title: t('customer.payment.methods'),
            href: customer.paymentMethods.index.url(),
            icon: CreditCard,
        },
        {
            title: t('customer.invoices.title'),
            href: customer.invoices.index.url(),
            icon: Receipt,
        },
    ];
}

/**
 * Customer Portal footer navigation items
 *
 * User-related actions:
 * - Profile settings
 */
export function useCustomerFooterNavItems(): NavItem[] {
    const { t } = useLaravelReactI18n();

    return [
        {
            title: t('customer.profile.title'),
            href: customer.profile.edit.url(),
            icon: User,
        },
    ];
}
