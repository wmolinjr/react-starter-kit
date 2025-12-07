import { AppContent } from '@/components/shared/layout/app-content';
import { AppShell } from '@/components/shared/layout/app-shell';
import { AdminSidebar } from '@/components/tenant/navigation/admin-sidebar';
import { AppSidebarHeader } from '@/components/shared/navigation/sidebar-header';
import { ImpersonationBanner } from '@/components/tenant/feedback/impersonation-banner';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

interface AdminLayoutProps extends PropsWithChildren {
    breadcrumbs?: BreadcrumbItem[];
}

export default function AdminLayout({
    children,
    breadcrumbs = [],
}: AdminLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AdminSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <ImpersonationBanner />
                {children}
            </AppContent>
        </AppShell>
    );
}
