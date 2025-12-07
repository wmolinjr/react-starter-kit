import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { CentralNavUser } from '@/components/central/nav-user';
import {
    useCentralPanelNavItems,
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
} from '@/components/ui/sidebar';

import central from '@/routes/central';
import { Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import AppLogo from '../app-logo';

export function CentralPanelSidebar() {
    const { t } = useLaravelReactI18n();

    // Get nav items from centralized source
    const mainNavItems = useCentralPanelNavItems();
    const footerNavItems = useFooterNavItems();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={central.panel.dashboard.url()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} label={t('sidebar.platform')} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <CentralNavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
