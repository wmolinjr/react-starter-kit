import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { CentralPanelSidebar } from '@/components/sidebar/central-panel-sidebar';
import { AppSidebarHeader } from '@/components/sidebar/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

interface CentralPanelLayoutProps extends PropsWithChildren {
    breadcrumbs?: BreadcrumbItem[];
}

export default function CentralPanelLayout({
    children,
    breadcrumbs = [],
}: CentralPanelLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <CentralPanelSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
