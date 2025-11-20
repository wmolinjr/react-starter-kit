import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { TenantSidebar } from '@/components/tenant-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

interface TenantLayoutProps extends PropsWithChildren {
    breadcrumbs?: BreadcrumbItem[];
}

export default function TenantLayout({
    children,
    breadcrumbs = [],
}: TenantLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <TenantSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <ImpersonationBanner />
                {children}
            </AppContent>
        </AppShell>
    );
}
