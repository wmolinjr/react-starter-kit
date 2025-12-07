import * as centralRecoveryCodesRoutes from '@/routes/central/admin/settings/two-factor/recovery-codes';
import * as centralRoutes from '@/routes/central/admin/settings/two-factor';
import * as tenantRecoveryCodesRoutes from '@/routes/tenant/admin/user-settings/two-factor/recovery-codes';
import * as tenantRoutes from '@/routes/tenant/admin/user-settings/two-factor';
import { useCallback, useMemo, useState } from 'react';

interface TwoFactorSetupData {
    svg: string;
    url: string;
}

interface TwoFactorSecretKey {
    secretKey: string;
}

export const OTP_MAX_LENGTH = 6;

const fetchJson = async <T>(url: string): Promise<T> => {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Failed to fetch: ${response.status}`);
    }

    return response.json();
};

type TwoFactorContext = 'tenant' | 'central';

interface UseTwoFactorAuthOptions {
    context?: TwoFactorContext;
}

/**
 * Hook for managing two-factor authentication state.
 *
 * @param options.context - 'tenant' (default) or 'central' to use appropriate routes
 *
 * @example
 * // Tenant context (default)
 * const { fetchSetupData, qrCodeSvg } = useTwoFactorAuth();
 *
 * // Central admin context
 * const { fetchSetupData, qrCodeSvg } = useTwoFactorAuth({ context: 'central' });
 */
export const useTwoFactorAuth = (options: UseTwoFactorAuthOptions = {}) => {
    const { context = 'tenant' } = options;

    const routes = context === 'central' ? centralRoutes : tenantRoutes;
    const recoveryCodesRoutes =
        context === 'central'
            ? centralRecoveryCodesRoutes
            : tenantRecoveryCodesRoutes;

    const [qrCodeSvg, setQrCodeSvg] = useState<string | null>(null);
    const [manualSetupKey, setManualSetupKey] = useState<string | null>(null);
    const [recoveryCodesList, setRecoveryCodesList] = useState<string[]>([]);
    const [errors, setErrors] = useState<string[]>([]);

    const hasSetupData = useMemo<boolean>(
        () => qrCodeSvg !== null && manualSetupKey !== null,
        [qrCodeSvg, manualSetupKey],
    );

    const fetchQrCode = useCallback(async (): Promise<void> => {
        try {
            const data = await fetchJson<TwoFactorSetupData>(routes.qrCode.url());
            setQrCodeSvg(data.svg);

            // For central, extract secret from URL; for tenant, fetch separately
            if (context === 'central') {
                const urlParams = new URL(data.url).searchParams;
                setManualSetupKey(urlParams.get('secret'));
            }
        } catch {
            setErrors((prev) => [...prev, 'Failed to fetch QR code']);
            setQrCodeSvg(null);
            if (context === 'central') {
                setManualSetupKey(null);
            }
        }
    }, [context, routes.qrCode]);

    const fetchSetupKey = useCallback(async (): Promise<void> => {
        // Only tenant has a separate secretKey endpoint
        if (context !== 'tenant' || !('secretKey' in routes)) {
            return;
        }

        try {
            const { secretKey: key } = await fetchJson<TwoFactorSecretKey>(
                (routes as typeof tenantRoutes).secretKey.url(),
            );
            setManualSetupKey(key);
        } catch {
            setErrors((prev) => [...prev, 'Failed to fetch a setup key']);
            setManualSetupKey(null);
        }
    }, [context, routes]);

    const clearErrors = useCallback((): void => {
        setErrors([]);
    }, []);

    const clearSetupData = useCallback((): void => {
        setManualSetupKey(null);
        setQrCodeSvg(null);
        clearErrors();
    }, [clearErrors]);

    const fetchRecoveryCodes = useCallback(async (): Promise<void> => {
        try {
            clearErrors();
            const codes = await fetchJson<string[]>(routes.recoveryCodes.url());
            setRecoveryCodesList(codes);
        } catch {
            setErrors((prev) => [...prev, 'Failed to fetch recovery codes']);
            setRecoveryCodesList([]);
        }
    }, [clearErrors, routes.recoveryCodes]);

    const fetchSetupData = useCallback(async (): Promise<void> => {
        try {
            clearErrors();
            if (context === 'central') {
                // Central: QR code response includes secret in URL
                await fetchQrCode();
            } else {
                // Tenant: Fetch QR code and secret key separately
                await Promise.all([fetchQrCode(), fetchSetupKey()]);
            }
        } catch {
            setQrCodeSvg(null);
            setManualSetupKey(null);
        }
    }, [clearErrors, context, fetchQrCode, fetchSetupKey]);

    return {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        hasSetupData,
        errors,
        clearErrors,
        clearSetupData,
        fetchQrCode,
        fetchSetupKey,
        fetchSetupData,
        fetchRecoveryCodes,
        routes: {
            enable: routes.enable,
            confirm: routes.confirm,
            disable: routes.disable,
            regenerateRecoveryCodes: recoveryCodesRoutes.store,
        },
    };
};
