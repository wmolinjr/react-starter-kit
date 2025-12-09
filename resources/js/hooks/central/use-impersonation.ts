import { useState, useCallback } from 'react';
import { router } from '@inertiajs/react';
import admin from '@/routes/central/admin';

interface UseImpersonationOptions {
    onSuccess?: () => void;
    onError?: (error: Error) => void;
}

interface UseImpersonationReturn {
    /** ID of the user/tenant currently being impersonated (loading state) */
    impersonatingId: string | null;
    /** Whether any impersonation action is in progress */
    isImpersonating: boolean;
    /** Navigate to the impersonation selection page for a tenant */
    impersonateTenant: (tenantId: string) => void;
    /** Directly impersonate as a specific user in a tenant */
    impersonateAsUser: (tenantId: string, userId: string) => void;
    /** Enter admin mode for a tenant (without selecting a user) */
    impersonateAdminMode: (tenantId: string) => void;
    /** Stop the current impersonation session */
    stopImpersonation: () => void;
}

/**
 * Hook for managing tenant/user impersonation from central admin.
 *
 * Provides functions to:
 * - Navigate to impersonation selection page
 * - Directly impersonate as a specific user
 * - Enter admin mode for a tenant
 * - Stop impersonation
 *
 * @example
 * ```tsx
 * const { impersonatingId, impersonateTenant, impersonateAsUser } = useImpersonation();
 *
 * // Navigate to selection page
 * <Button onClick={() => impersonateTenant(tenant.id)}>
 *   Impersonate
 * </Button>
 *
 * // Direct impersonation as user
 * <Button
 *   onClick={() => impersonateAsUser(tenant.id, user.id)}
 *   disabled={impersonatingId === user.id}
 * >
 *   {impersonatingId === user.id ? '...' : 'Impersonate'}
 * </Button>
 * ```
 */
export function useImpersonation(options: UseImpersonationOptions = {}): UseImpersonationReturn {
    const [impersonatingId, setImpersonatingId] = useState<string | null>(null);
    const { onSuccess, onError } = options;

    const impersonateTenant = useCallback((tenantId: string) => {
        router.visit(admin.tenants.impersonate.index.url(tenantId));
    }, []);

    const impersonateAsUser = useCallback((tenantId: string, userId: string) => {
        setImpersonatingId(userId);
        router.post(
            admin.tenants.impersonate.asUser.url({ tenant: tenantId, userId }),
            {},
            {
                onSuccess: () => {
                    onSuccess?.();
                },
                onError: (errors) => {
                    onError?.(new Error(Object.values(errors).flat().join(', ')));
                },
                onFinish: () => {
                    setImpersonatingId(null);
                },
            }
        );
    }, [onSuccess, onError]);

    const impersonateAdminMode = useCallback((tenantId: string) => {
        setImpersonatingId(tenantId);
        router.post(
            admin.tenants.impersonate.adminMode.url(tenantId),
            {},
            {
                onSuccess: () => {
                    onSuccess?.();
                },
                onError: (errors) => {
                    onError?.(new Error(Object.values(errors).flat().join(', ')));
                },
                onFinish: () => {
                    setImpersonatingId(null);
                },
            }
        );
    }, [onSuccess, onError]);

    const stopImpersonation = useCallback(() => {
        setImpersonatingId('stop');
        router.post(
            admin.impersonate.stop.url(),
            {},
            {
                onFinish: () => {
                    setImpersonatingId(null);
                },
            }
        );
    }, []);

    return {
        impersonatingId,
        isImpersonating: impersonatingId !== null,
        impersonateTenant,
        impersonateAsUser,
        impersonateAdminMode,
        stopImpersonation,
    };
}
