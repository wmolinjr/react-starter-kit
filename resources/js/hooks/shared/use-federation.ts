import { useState, useCallback, useMemo } from 'react';
import { router, usePage } from '@inertiajs/react';
import centralAdmin from '@/routes/central/admin';
import tenantAdmin from '@/routes/tenant/admin';
import type { PageProps } from '@/types';

interface UseFederationOptions {
    onSuccess?: () => void;
    onError?: (error: Error) => void;
}

interface CentralFederationReturn {
    context: 'central';
    processingId: string | null;
    isProcessing: boolean;
    // Tenant operations (central only)
    addTenant: (groupId: string, tenantId: string) => void;
    removeTenant: (groupId: string, tenantId: string) => void;
    rejoinTenant: (groupId: string, tenantId: string) => void;
    toggleTenantSync: (groupId: string, tenantId: string) => void;
    // User operations
    syncUser: (groupId: string, userId: string) => void;
    retryAllSync: (groupId: string) => void;
}

interface TenantFederationReturn {
    context: 'tenant';
    processingId: string | null;
    isProcessing: boolean;
    // User operations (tenant only)
    federateUser: (userId: string) => void;
    unfederateUser: (userId: string) => void;
    syncUser: (userId: string) => void;
}

type UseFederationReturn = CentralFederationReturn | TenantFederationReturn;

/**
 * Hook for managing federation operations.
 * Automatically detects context (central/tenant) and provides appropriate functions.
 *
 * @example
 * ```tsx
 * // In central admin
 * const federation = useFederation();
 * if (federation.context === 'central') {
 *   federation.addTenant(groupId, tenantId);
 *   federation.toggleTenantSync(groupId, tenantId);
 * }
 *
 * // In tenant admin
 * const federation = useFederation();
 * if (federation.context === 'tenant') {
 *   federation.federateUser(userId);
 *   federation.syncUser(userId);
 * }
 * ```
 */
export function useFederation(options: UseFederationOptions = {}): UseFederationReturn {
    const [processingId, setProcessingId] = useState<string | null>(null);
    const { onSuccess, onError } = options;
    const { auth } = usePage<PageProps>().props;
    const context = auth.guard === 'central' ? 'central' : 'tenant';

    const handleRequest = useCallback((
        id: string,
        method: 'post' | 'delete',
        url: string,
        data?: Record<string, string>
    ) => {
        setProcessingId(id);

        const requestOptions = {
            onSuccess: () => {
                onSuccess?.();
            },
            onError: () => {
                onError?.(new Error('Request failed'));
            },
            onFinish: () => {
                setProcessingId(null);
            },
        };

        if (method === 'post') {
            router.post(url, data ?? {}, requestOptions);
        } else {
            router.delete(url, requestOptions);
        }
    }, [onSuccess, onError]);

    // Central operations
    const centralOperations = useMemo(() => ({
        addTenant: (groupId: string, tenantId: string) => {
            handleRequest(tenantId, 'post', centralAdmin.federation.tenants.add.url(groupId), { tenant_id: tenantId });
        },
        removeTenant: (groupId: string, tenantId: string) => {
            handleRequest(tenantId, 'delete', centralAdmin.federation.tenants.remove.url({ group: groupId, tenant: tenantId }));
        },
        rejoinTenant: (groupId: string, tenantId: string) => {
            handleRequest(tenantId, 'post', centralAdmin.federation.tenants.add.url(groupId), { tenant_id: tenantId });
        },
        toggleTenantSync: (groupId: string, tenantId: string) => {
            handleRequest(tenantId, 'post', centralAdmin.federation.tenants.toggleSync.url({ group: groupId, tenant: tenantId }));
        },
        syncUser: (groupId: string, userId: string) => {
            handleRequest(userId, 'post', centralAdmin.federation.users.sync.url({ group: groupId, user: userId }));
        },
        retryAllSync: (groupId: string) => {
            handleRequest('retry-all', 'post', centralAdmin.federation.retrySync.url(groupId));
        },
    }), [handleRequest]);

    // Tenant operations
    const tenantOperations = useMemo(() => ({
        federateUser: (userId: string) => {
            handleRequest(userId, 'post', tenantAdmin.settings.federation.users.federate.url(), { user_id: userId });
        },
        unfederateUser: (userId: string) => {
            handleRequest(userId, 'delete', tenantAdmin.settings.federation.users.unfederate.url(userId));
        },
        syncUser: (userId: string) => {
            handleRequest(userId, 'post', tenantAdmin.settings.federation.users.sync.url(userId));
        },
    }), [handleRequest]);

    if (context === 'central') {
        return {
            context: 'central',
            processingId,
            isProcessing: processingId !== null,
            ...centralOperations,
        };
    }

    return {
        context: 'tenant',
        processingId,
        isProcessing: processingId !== null,
        ...tenantOperations,
    };
}

// Type guards for easier usage
export function isCentralFederation(federation: UseFederationReturn): federation is CentralFederationReturn {
    return federation.context === 'central';
}

export function isTenantFederation(federation: UseFederationReturn): federation is TenantFederationReturn {
    return federation.context === 'tenant';
}

// Re-export types for external use
export type { CentralFederationReturn, TenantFederationReturn, UseFederationReturn };
