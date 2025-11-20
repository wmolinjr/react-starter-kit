import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { CentralSidebar } from '@/components/central-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

interface CentralLayoutProps extends PropsWithChildren {
    breadcrumbs?: BreadcrumbItem[];
}

export default function CentralLayout({
    children,
    breadcrumbs = [],
}: CentralLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <CentralSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
