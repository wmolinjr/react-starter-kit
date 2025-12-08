import { AppContent } from '@/components/shared/layout/app-content';
import { AppShell } from '@/components/shared/layout/app-shell';
import { AdminSidebar } from '@/components/tenant/navigation/admin-sidebar';
import { AppSidebarHeader } from '@/components/shared/navigation/sidebar-header';
import { ImpersonationBanner } from '@/components/tenant/feedback/impersonation-banner';
import { useBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactNode } from 'react';

interface AdminLayoutProps {
    children: ReactNode;
}

/**
 * Tenant Admin Layout - Persistent Layout
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
                <ImpersonationBanner />
                {children}
            </AppContent>
        </AppShell>
    );
}
