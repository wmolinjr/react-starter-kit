import { type ReactNode } from 'react';
import { type Permissions } from '@/types';
import { usePermissions } from '@/hooks/use-permissions';

interface CanProps {
    permission: keyof Permissions;
    children: ReactNode;
    fallback?: ReactNode;
}

/**
 * Conditional rendering component based on user permissions.
 *
 * Renders children only if the user has the specified permission.
 * Optionally renders a fallback when permission is denied.
 *
 * @example
 * <Can permission="canManageTeam">
 *   <TeamManagementButton />
 * </Can>
 *
 * @example
 * <Can permission="canManageBilling" fallback={<UpgradePrompt />}>
 *   <BillingSettings />
 * </Can>
 */
export function Can({ permission, children, fallback = null }: CanProps) {
    const permissions = usePermissions();

    if (permissions[permission]) {
        return <>{children}</>;
    }

    return <>{fallback}</>;
}
