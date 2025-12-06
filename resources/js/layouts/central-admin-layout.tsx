import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { CentralAdminSidebar } from '@/components/sidebar/central-admin-sidebar';
import { AppSidebarHeader } from '@/components/sidebar/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

interface CentralAdminLayoutProps extends PropsWithChildren {
    breadcrumbs?: BreadcrumbItem[];
}

export default function CentralAdminLayout({
    children,
    breadcrumbs = [],
}: CentralAdminLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <CentralAdminSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
