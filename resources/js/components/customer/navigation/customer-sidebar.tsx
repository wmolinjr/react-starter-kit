import { NavMain } from '@/components/shared/navigation/nav-main';
import { NavFooter } from '@/components/shared/navigation/nav-footer';
import { CustomerNavUser } from '@/components/customer/navigation/customer-nav-user';
import {
    useCustomerNavItems,
    useCustomerFooterNavItems,
} from '@/components/customer/navigation/nav-items';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import customer from '@/routes/central/account';
import { Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CreditCard } from 'lucide-react';

/**
 * Customer Portal Sidebar
 *
 * Sidebar for customer billing portal.
 * Uses same pattern as AdminSidebar for consistency.
 *
 * Navigation includes:
 * - Main nav items (dashboard, workspaces, payments, invoices)
 * - Footer items (profile)
 * - User menu with logout
 */
export function CustomerSidebar() {
    const { t } = useLaravelReactI18n();
    const { state } = useSidebar();

    const mainNavItems = useCustomerNavItems();
    const footerNavItems = useCustomerFooterNavItems();

    const isCollapsed = state === 'collapsed';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={customer.dashboard.url()} prefetch>
                                <div className="bg-primary text-primary-foreground flex aspect-square size-8 items-center justify-center rounded-lg">
                                    <CreditCard className="size-4" />
                                </div>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">
                                        {t('customer.billing.portal')}
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    items={mainNavItems}
                    label={isCollapsed ? undefined : t('sidebar.group.navigation')}
                />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <CustomerNavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
