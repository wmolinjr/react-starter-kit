import CentralAdminLayout from '@/layouts/central-admin-layout';
import TenantAdminLayout from '@/layouts/tenant-admin-layout';
import { useTenant } from '@/hooks/use-tenant';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

interface SharedLayoutProps extends PropsWithChildren {
    breadcrumbs?: BreadcrumbItem[];
}

/**
 * Shared Layout - automatically selects the appropriate layout
 * based on the current context.
 *
 * - Tenant context → TenantAdminLayout
 * - Central context → CentralAdminLayout
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

    // Tenant context: use TenantAdminLayout
    if (isTenantContext) {
        return (
            <TenantAdminLayout breadcrumbs={breadcrumbs}>
                {children}
            </TenantAdminLayout>
        );
    }

    // Central context: use CentralAdminLayout
    return (
        <CentralAdminLayout breadcrumbs={breadcrumbs}>
            {children}
        </CentralAdminLayout>
    );
}
