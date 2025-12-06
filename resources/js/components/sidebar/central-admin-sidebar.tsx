import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    useCentralAdminNavItems,
    useCentralPanelNavItems,
    useFooterNavItems,
} from '@/components/sidebar/central-nav-items';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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
import central from '@/routes/central';
import { Link, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ShieldCheck, User } from 'lucide-react';
import AppLogo from '../app-logo';

export function CentralAdminSidebar() {
    const { t } = useLaravelReactI18n();
    const { state } = useSidebar();
    const { url } = usePage();

    // Get nav items from centralized source
    const adminNavItems = useCentralAdminNavItems();
    const panelNavItems = useCentralPanelNavItems();
    const footerNavItems = useFooterNavItems();

    // Determine active tab based on current URL
    // Admin routes: /admin/*
    // Account routes: /painel/* or /settings/*
    const activeTab = url.startsWith('/admin') ? 'admin' : 'account';
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
                <Tabs
                    value={activeTab}
                    className="flex h-full flex-col"
                >
                    {/* Tabs list - hidden when sidebar is collapsed */}
                    <div className="px-2 group-data-[collapsible=icon]:hidden">
                        <TabsList className="w-full">
                            <TabsTrigger value="admin" className="flex-1 gap-1.5" asChild>
                                <Link href={admin.dashboard.url()} prefetch>
                                    <ShieldCheck className="size-4" />
                                    <span>{t('sidebar.administration')}</span>
                                </Link>
                            </TabsTrigger>
                            <TabsTrigger value="account" className="flex-1 gap-1.5" asChild>
                                <Link href={central.panel.dashboard.url()} prefetch>
                                    <User className="size-4" />
                                    <span>{t('sidebar.account')}</span>
                                </Link>
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <TabsContent value="admin" className="mt-0 flex-1">
                        <NavMain
                            items={adminNavItems}
                            label={isCollapsed ? undefined : t('sidebar.administration')}
                        />
                    </TabsContent>

                    <TabsContent value="account" className="mt-0 flex-1">
                        <NavMain
                            items={panelNavItems}
                            label={isCollapsed ? undefined : t('sidebar.account')}
                        />
                    </TabsContent>
                </Tabs>
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
