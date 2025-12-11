import admin from '@/routes/tenant/admin';
import { NavFooter } from '@/components/shared/navigation/nav-footer';
import { NavMain } from '@/components/shared/navigation/nav-main';
import { TenantNavUser } from '@/components/tenant/navigation/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { BookOpen, Folder, LayoutGrid } from 'lucide-react';
import AppLogo from '@/components/shared/branding/app-logo';

export function AppSidebar() {
    const { t } = useLaravelReactI18n();

    const mainNavItems: NavItem[] = [
        {
            title: t('sidebar.item.dashboard'),
            href: admin.dashboard.url(),
            icon: LayoutGrid,
        },
    ];

    const footerNavItems: NavItem[] = [
        {
            title: t('sidebar.item.repository'),
            href: 'https://github.com/laravel/react-starter-kit',
            icon: Folder,
        },
        {
            title: t('sidebar.item.documentation'),
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];
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
