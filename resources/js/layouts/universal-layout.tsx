import CentralAdminLayout from '@/layouts/central-admin-layout';
import CentralPanelLayout from '@/layouts/central-panel-layout';
import TenantAdminLayout from '@/layouts/tenant-admin-layout';
import { usePermissions } from '@/hooks/use-permissions';
import { useTenant } from '@/hooks/use-tenant';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

interface UniversalLayoutProps extends PropsWithChildren {
    breadcrumbs?: BreadcrumbItem[];
}

/**
 * Universal Layout - automatically selects the appropriate layout
 * based on the current context and user role.
 *
 * - Tenant context → TenantAdminLayout
 * - Central context + Super Admin → CentralAdminLayout
 * - Central context + Regular user → CentralPanelLayout
 *
 * @example
 * // In a universal page component:
 * export default function ConfirmPassword() {
 *     return (
 *         <UniversalLayout breadcrumbs={breadcrumbs}>
 *             <Head title="Confirm Password" />
 *             <YourContent />
 *         </UniversalLayout>
 *     );
 * }
 */
export default function UniversalLayout({
    children,
    breadcrumbs = [],
}: UniversalLayoutProps) {
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
