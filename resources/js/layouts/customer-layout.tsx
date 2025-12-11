import { AppContent } from '@/components/shared/layout/app-content';
import { AppShell } from '@/components/shared/layout/app-shell';
import { AppSidebarHeader } from '@/components/shared/navigation/sidebar-header';
import { SidebarInset, SidebarProvider, Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type BreadcrumbItem } from '@/types';
import customer from '@/routes/customer';
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

    const isActive = (href: string) => {
        if (href === customer.dashboard.url()) {
            return url === customer.dashboard.url() || url === customer.dashboard.url() + '/';
        }
        return url.startsWith(href);
    };

    return (
        <SidebarProvider defaultOpen>
            <AppShell variant="sidebar">
                <Sidebar>
                    <SidebarHeader className="border-b px-4 py-3">
                        <Link href={customer.dashboard.url()} className="flex items-center gap-2 font-semibold">
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
                                <SidebarMenuButton asChild isActive={isActive(customer.profile.edit.url())}>
                                    <Link href={customer.profile.edit.url()}>
                                        <User className="h-4 w-4" />
                                        <span>{t('customer.profile.title')}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                            <SidebarMenuItem>
                                <SidebarMenuButton asChild>
                                    <Link href={customer.logout.url()} method="post" as="button" className="w-full">
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
