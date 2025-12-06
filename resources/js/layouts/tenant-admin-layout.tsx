import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { TenantAdminSidebar } from '@/components/sidebar/tenant-admin-sidebar';
import { AppSidebarHeader } from '@/components/sidebar/app-sidebar-header';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

interface TenantAdminLayoutProps extends PropsWithChildren {
    breadcrumbs?: BreadcrumbItem[];
}

export default function TenantAdminLayout({
    children,
    breadcrumbs = [],
}: TenantAdminLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <TenantAdminSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <ImpersonationBanner />
                {children}
            </AppContent>
        </AppShell>
    );
}
