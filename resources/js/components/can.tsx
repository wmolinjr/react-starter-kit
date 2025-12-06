import { type ReactNode } from 'react';
import type { Permission } from '@/types/permissions';
import { usePermissions } from '@/hooks/use-permissions';

interface CanPropsBase {
    children: ReactNode;
    fallback?: ReactNode;
}

interface CanPropsPermission extends CanPropsBase {
    /** Single permission to check */
    permission: Permission;
    any?: never;
    all?: never;
}

interface CanPropsAny extends CanPropsBase {
    /** Check if user has ANY of these permissions (OR logic) */
    any: Permission[];
    permission?: never;
    all?: never;
}

interface CanPropsAll extends CanPropsBase {
    /** Check if user has ALL of these permissions (AND logic) */
    all: Permission[];
    permission?: never;
    any?: never;
}

type CanProps = CanPropsPermission | CanPropsAny | CanPropsAll;

/**
 * Conditional rendering component based on user permissions.
 *
 * Renders children only if the user has the specified permission(s).
 * Optionally renders a fallback when permission is denied.
 *
 * Supports three modes:
 * - Single permission check
 * - OR logic (any of the permissions)
 * - AND logic (all of the permissions)
 *
 * All permission checks are type-safe using auto-generated Permission type.
 *
 * @example
 * // Single permission
 * <Can permission="projects:create">
 *   <CreateProjectButton />
 * </Can>
 *
 * @example
 * // OR logic - user can edit OR editOwn
 * <Can any={["projects:edit", "projects:editOwn"]}>
 *   <EditButton />
 * </Can>
 *
 * @example
 * // AND logic - user must view AND download
 * <Can all={["projects:view", "projects:download"]}>
 *   <DownloadButton />
 * </Can>
 *
 * @example
 * // With fallback
 * <Can permission="billing:view" fallback={<UpgradePrompt />}>
 *   <BillingSettings />
 * </Can>
 */
export function Can({ permission, any, all, children, fallback = null }: CanProps) {
    const { has, hasAny, hasAll } = usePermissions();

    let allowed = false;

    if (permission) {
        allowed = has(permission);
    } else if (any) {
        allowed = hasAny(...any);
    } else if (all) {
        allowed = hasAll(...all);
    }

    return allowed ? <>{children}</> : <>{fallback}</>;
}
