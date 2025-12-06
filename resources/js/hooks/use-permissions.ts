import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import type { Permission } from '@/types/permissions';

/**
 * Hook to access and check user permissions in the current tenant context.
 *
 * Returns helper methods to check permissions and access role metadata.
 * All permission checks are type-safe using the auto-generated Permission type.
 *
 * @example
 * // Check single permission
 * const { has, role } = usePermissions();
 * if (has('tenant.projects:create')) {
 *   return <CreateProjectButton />;
 * }
 *
 * @example
 * // Check multiple permissions (OR logic)
 * const { hasAny } = usePermissions();
 * if (hasAny('tenant.projects:edit', 'tenant.projects:editOwn')) {
 *   return <EditButton />;
 * }
 *
 * @example
 * // Check multiple permissions (AND logic)
 * const { hasAll } = usePermissions();
 * if (hasAll('tenant.projects:view', 'tenant.projects:download')) {
 *   return <DownloadButton />;
 * }
 *
 * @example
 * // Access role metadata (for UI only - NOT for authorization)
 * const { role } = usePermissions();
 * return <Badge>{role?.name}</Badge>;
 */
export function usePermissions() {
    const { auth } = usePage<PageProps>().props;

    return {
        /**
         * Check if user has a specific permission
         */
        has: (permission: Permission): boolean => {
            return auth?.permissions?.includes(permission) ?? false;
        },

        /**
         * Check if user has ANY of the provided permissions (OR logic)
         */
        hasAny: (...permissions: Permission[]): boolean => {
            return permissions.some((p) => auth?.permissions?.includes(p)) ?? false;
        },

        /**
         * Check if user has ALL of the provided permissions (AND logic)
         */
        hasAll: (...permissions: Permission[]): boolean => {
            return permissions.every((p) => auth?.permissions?.includes(p)) ?? false;
        },

        /**
         * Get all permissions the user has
         */
        all: (): Permission[] => {
            return auth?.permissions ?? [];
        },

        /**
         * Role metadata (for UI display only - NOT for authorization)
         * Use permissions for actual authorization checks
         */
        role: auth?.role ?? null,

        /**
         * Convenience accessors for role metadata
         * WARNING: Use only for UI display (badges, visual elements)
         * Always use permissions for actual authorization
         */
        isOwner: auth?.role?.isOwner ?? false,
        isAdmin: auth?.role?.isAdmin ?? false,
        isAdminOrOwner: auth?.role?.isAdminOrOwner ?? false,
        isSuperAdmin: auth?.role?.isSuperAdmin ?? false,
        roleName: auth?.role?.name ?? null,
    };
}

/**
 * Shorthand hook to check a single permission.
 * Returns boolean indicating if user has the permission.
 *
 * @example
 * const canCreate = useCan('tenant.projects:create');
 * if (canCreate) {
 *   return <CreateButton />;
 * }
 */
export function useCan(permission: Permission): boolean {
    const { has } = usePermissions();
    return has(permission);
}
