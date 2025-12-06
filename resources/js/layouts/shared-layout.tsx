import CentralAdminLayout from '@/layouts/central-admin-layout';
import CentralPanelLayout from '@/layouts/central-panel-layout';
import TenantAdminLayout from '@/layouts/tenant-admin-layout';
import { usePermissions } from '@/hooks/use-permissions';
import { useTenant } from '@/hooks/use-tenant';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

interface SharedLayoutProps extends PropsWithChildren {
    breadcrumbs?: BreadcrumbItem[];
}

/**
 * Shared Layout - automatically selects the appropriate layout
 * based on the current context and user role.
 *
 * - Tenant context → TenantAdminLayout
 * - Central context + Super Admin → CentralAdminLayout
 * - Central context + Regular user → CentralPanelLayout
 *
 * @example
 * // In a shared page component:
 * export default function ConfirmPassword() {
 *     return (
 *         <SharedLayout breadcrumbs={breadcrumbs}>
 *             <Head title="Confirm Password" />
 *             <YourContent />
 *         </SharedLayout>
 *     );
 * }
 */
export default function SharedLayout({
    children,
    breadcrumbs = [],
}: SharedLayoutProps) {
    const { isTenantContext } = useTenant();
    const { isSuperAdmin } = usePermissions();

    // Tenant context: use TenantAdminLayout
    if (isTenantContext) {
        return (
            <TenantAdminLayout breadcrumbs={breadcrumbs}>
                {children}
            </TenantAdminLayout>
        );
    }

    // Central context + Super Admin: use CentralAdminLayout
    if (isSuperAdmin) {
        return (
            <CentralAdminLayout breadcrumbs={breadcrumbs}>
                {children}
            </CentralAdminLayout>
        );
    }

    // Central context + Regular user: use CentralPanelLayout
    return (
        <CentralPanelLayout breadcrumbs={breadcrumbs}>
            {children}
        </CentralPanelLayout>
    );
}
