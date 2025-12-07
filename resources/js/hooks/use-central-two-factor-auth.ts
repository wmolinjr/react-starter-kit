import { qrCode, recoveryCodes } from '@/routes/central/admin/settings/two-factor';
import { useCallback, useMemo, useState } from 'react';

interface TwoFactorSetupData {
    svg: string;
    url: string;
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

/**
 * Hook for managing two-factor authentication state for Central administrators.
 * Uses custom routes instead of Fortify routes since central admins use a different guard.
 */
export const useCentralTwoFactorAuth = () => {
    const [qrCodeSvg, setQrCodeSvg] = useState<string | null>(null);
    const [manualSetupKey, setManualSetupKey] = useState<string | null>(null);
    const [recoveryCodesList, setRecoveryCodesList] = useState<string[]>([]);
    const [errors, setErrors] = useState<string[]>([]);

    const hasSetupData = useMemo<boolean>(
        () => qrCodeSvg !== null,
        [qrCodeSvg],
    );

    const fetchQrCode = useCallback(async (): Promise<void> => {
        try {
            const data = await fetchJson<TwoFactorSetupData>(qrCode.url());
            setQrCodeSvg(data.svg);
            // Extract secret key from URL (otpauth://totp/...?secret=XXXX&...)
            const urlParams = new URL(data.url).searchParams;
            setManualSetupKey(urlParams.get('secret'));
        } catch {
            setErrors((prev) => [...prev, 'Failed to fetch QR code']);
            setQrCodeSvg(null);
            setManualSetupKey(null);
        }
    }, []);

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
            const codes = await fetchJson<string[]>(recoveryCodes.url());
            setRecoveryCodesList(codes);
        } catch {
            setErrors((prev) => [...prev, 'Failed to fetch recovery codes']);
            setRecoveryCodesList([]);
        }
    }, [clearErrors]);

    const fetchSetupData = useCallback(async (): Promise<void> => {
        try {
            clearErrors();
            await fetchQrCode();
        } catch {
            setQrCodeSvg(null);
            setManualSetupKey(null);
        }
    }, [clearErrors, fetchQrCode]);

    return {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        hasSetupData,
        errors,
        clearErrors,
        clearSetupData,
        fetchQrCode,
        fetchSetupData,
        fetchRecoveryCodes,
    };
};
