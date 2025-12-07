import { AppContent } from '@/components/shared/layout/app-content';
import { AppShell } from '@/components/shared/layout/app-shell';
import { AppSidebar } from '@/components/tenant/navigation/app-sidebar';
import { AppSidebarHeader } from '@/components/shared/navigation/sidebar-header';
import { ImpersonationBanner } from '@/components/tenant/feedback/impersonation-banner';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <ImpersonationBanner />
                {children}
            </AppContent>
        </AppShell>
    );
}
