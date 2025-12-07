import admin from '@/routes/tenant/admin';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { TenantNavUser } from '@/components/tenant/nav-user';
import {
    useTenantAdminNavItems,
    useTenantFooterNavItems,
} from '@/components/sidebar/tenant-nav-items';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Link } from '@inertiajs/react';
import AppLogo from '../app-logo';

export function TenantAdminSidebar() {
    // Get nav items from centralized source
    const mainNavItems = useTenantAdminNavItems();
    const footerNavItems = useTenantFooterNavItems();

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
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <TenantNavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
