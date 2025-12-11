import { AppContent } from '@/components/shared/layout/app-content';
import { AppShell } from '@/components/shared/layout/app-shell';
import { AppSidebarHeader } from '@/components/shared/navigation/sidebar-header';
import { SidebarInset, SidebarProvider, Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type BreadcrumbItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    LayoutDashboard,
    CreditCard,
    Building2,
    Receipt,
    User,
    LogOut,
} from 'lucide-react';
import { type ReactNode } from 'react';

interface CustomerLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function CustomerLayout({ children, breadcrumbs = [] }: CustomerLayoutProps) {
    const { t } = useLaravelReactI18n();
    const { url } = usePage();

    const mainNavItems = [
        {
            title: t('customer.dashboard.title'),
            href: '/account',
            icon: LayoutDashboard,
        },
        {
            title: t('customer.workspace.title'),
            href: '/account/tenants',
            icon: Building2,
        },
        {
            title: t('customer.payment.methods'),
            href: '/account/payment-methods',
            icon: CreditCard,
        },
        {
            title: t('customer.invoices.title'),
            href: '/account/invoices',
            icon: Receipt,
        },
    ];

    const isActive = (href: string) => {
        if (href === '/account') {
            return url === '/account' || url === '/account/';
        }
        return url.startsWith(href);
    };

    return (
        <SidebarProvider defaultOpen>
            <AppShell variant="sidebar">
                <Sidebar>
                    <SidebarHeader className="border-b px-4 py-3">
                        <Link href="/account" className="flex items-center gap-2 font-semibold">
                            <CreditCard className="h-5 w-5" />
                            <span>{t('customer.billing.portal')}</span>
                        </Link>
                    </SidebarHeader>
                    <SidebarContent>
                        <SidebarMenu className="px-2 py-2">
                            {mainNavItems.map((item) => (
                                <SidebarMenuItem key={item.href}>
                                    <SidebarMenuButton asChild isActive={isActive(item.href)}>
                                        <Link href={item.href}>
                                            <item.icon className="h-4 w-4" />
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarContent>
                    <SidebarFooter className="border-t">
                        <SidebarMenu className="px-2 py-2">
                            <SidebarMenuItem>
                                <SidebarMenuButton asChild isActive={isActive('/account/profile')}>
                                    <Link href="/account/profile">
                                        <User className="h-4 w-4" />
                                        <span>{t('customer.profile.title')}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                            <SidebarMenuItem>
                                <SidebarMenuButton asChild>
                                    <Link href="/account/logout" method="post" as="button" className="w-full">
                                        <LogOut className="h-4 w-4" />
                                        <span>{t('auth.logout.button')}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarFooter>
                </Sidebar>
                <SidebarInset>
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    <AppContent variant="sidebar">
                        {children}
                    </AppContent>
                </SidebarInset>
            </AppShell>
        </SidebarProvider>
    );
}
