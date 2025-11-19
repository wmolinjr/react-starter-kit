import { usePage } from '@inertiajs/react';
import type { PageProps, Permissions } from '@/types';

/**
 * Hook to access user permissions in the current tenant context.
 *
 * Returns all permission flags (Gates) and role information.
 * If not authenticated or no permissions available, returns default false values.
 *
 * @example
 * const { canManageTeam, isOwner, role } = usePermissions();
 *
 * if (canManageTeam) {
 *   return <TeamManagementUI />;
 * }
 */
export function usePermissions(): Permissions {
    const { auth } = usePage<PageProps>().props;

    return (
        auth?.permissions || {
            canManageTeam: false,
            canManageBilling: false,
            canManageSettings: false,
            canCreateResources: false,
            role: null,
            isOwner: false,
            isAdmin: false,
            isAdminOrOwner: false,
        }
    );
}

/**
 * Helper hook to check a specific permission.
 *
 * @example
 * const canManage = useCan('canManageTeam');
 * if (canManage) {
 *   // render team management UI
 * }
 */
export function useCan(permission: keyof Permissions): boolean {
    const permissions = usePermissions();
    const value = permissions[permission];
    return typeof value === 'boolean' ? value : false;
}
