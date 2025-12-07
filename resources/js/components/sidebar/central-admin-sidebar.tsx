import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    useCentralAdminNavItems,
    useFooterNavItems,
} from '@/components/sidebar/central-nav-items';
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

import admin from '@/routes/central/admin';
import { Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import AppLogo from '../app-logo';

/**
 * Central Admin Sidebar
 *
 * Simplified sidebar for central admin dashboard.
 * Removed tabs (Administration/Account) to provide cleaner UX.
 *
 * Navigation includes:
 * - Admin nav items (dashboard, tenants, plans, users, etc.)
 * - Footer items (settings, help)
 * - User menu with logout
 */
export function CentralAdminSidebar() {
    const { t } = useLaravelReactI18n();
    const { state } = useSidebar();

    // Get nav items from centralized source
    const adminNavItems = useCentralAdminNavItems();
    const footerNavItems = useFooterNavItems();

    const isCollapsed = state === 'collapsed';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={admin.dashboard.url()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    items={adminNavItems}
                    label={isCollapsed ? undefined : t('sidebar.administration')}
                />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
