import { usePage } from '@inertiajs/react';
import tenantAuth from '@/routes/tenant/admin/auth';
import centralAuth from '@/routes/central/admin/auth';
import type { PageProps } from '@/types';

/**
 * Hook to get the appropriate logout route based on authentication guard.
 *
 * DUAL GUARD SYSTEM:
 * - 'central' guard: Uses /admin/logout (AdminLogoutController)
 * - 'tenant' guard: Uses /logout (LogoutController)
 *
 * This ensures central admins and tenant users use their respective logout routes.
 */
export function useLogout() {
    const { auth } = usePage<PageProps>().props;

    // Return appropriate logout route based on guard
    return auth.guard === 'central' ? centralAuth.logout : tenantAuth.logout;
}
