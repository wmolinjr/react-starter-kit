import { usePage } from '@inertiajs/react';
import type { PageProps, Tenant, TenantInfo } from '@/types';

/**
 * Hook to access current tenant data.
 *
 * OPTION C ARCHITECTURE:
 * - Users exist ONLY in tenant databases (complete isolation)
 * - A user belongs to exactly one tenant (the database they're in)
 * - No tenants list - just current tenant info
 *
 * @example
 * const { tenant, isTenantContext, hasTenant } = useTenant();
 *
 * if (!isTenantContext) {
 *   return <div>No tenant context</div>;
 * }
 *
 * return <div>Current tenant: {tenant.name}</div>;
 */
export function useTenant() {
    const { tenant, auth } = usePage<PageProps>().props;

    return {
        /**
         * Current tenant data (null if not in tenant context)
         */
        tenant: tenant as Tenant | null,

        /**
         * Current user's tenant info from auth context (null if not in tenant context)
         */
        userTenant: auth.tenant as TenantInfo | null,

        /**
         * Whether we're currently in a tenant context
         */
        isTenantContext: tenant !== null,

        /**
         * Check if user belongs to a tenant (Option C: users always belong to exactly one tenant)
         */
        hasTenant: auth.tenant !== null,

        /**
         * Get tenant setting by dot notation key
         * @example getSetting('branding.logo_url')
         */
        getSetting: <T = unknown>(key: string, defaultValue?: T): T | undefined => {
            if (!tenant?.settings) return defaultValue;

            const keys = key.split('.');
            let value: unknown = tenant.settings;

            for (const k of keys) {
                if (typeof value === 'object' && value !== null && k in value) {
                    value = (value as Record<string, unknown>)[k];
                } else {
                    return defaultValue;
                }
            }

            return value as T;
        },

        /**
         * Check if current user is the owner of the current tenant
         */
        isOwner: auth.role?.isOwner ?? false,

        /**
         * Check if current user is admin or owner of the current tenant
         */
        isAdminOrOwner: auth.role?.isAdminOrOwner ?? false,

        /**
         * Current user's role on the current tenant
         */
        role: auth.role?.name,

        /**
         * Subscription info
         */
        subscription: tenant?.subscription,

        /**
         * Check if tenant has an active subscription
         */
        hasActiveSubscription: tenant?.subscription?.active ?? false,

        /**
         * Check if tenant is on trial
         */
        isOnTrial: tenant?.subscription?.on_trial ?? false,
    };
}
