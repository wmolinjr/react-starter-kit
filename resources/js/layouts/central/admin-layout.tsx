import { AppContent } from '@/components/shared/layout/app-content';
import { AppShell } from '@/components/shared/layout/app-shell';
import { AdminSidebar } from '@/components/central/navigation/admin-sidebar';
import { AppSidebarHeader } from '@/components/shared/navigation/sidebar-header';
import { useBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactNode } from 'react';

interface AdminLayoutProps {
    children: ReactNode;
}

/**
 * Central Admin Layout - Persistent Layout
 *
 * This layout doesn't remount on navigation, preserving sidebar state.
 * Breadcrumbs are read from BreadcrumbContext (set by pages).
 *
 * @see https://inertiajs.com/pages#persistent-layouts
 */
export default function AdminLayout({ children }: AdminLayoutProps) {
    const breadcrumbs = useBreadcrumbs();

    return (
        <AppShell variant="sidebar">
            <AdminSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
