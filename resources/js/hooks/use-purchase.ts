import { useState, useCallback } from 'react';
import { router } from '@inertiajs/react';
import addons from '@/routes/tenant/admin/addons';

type BillingPeriod = 'monthly' | 'yearly' | 'one_time';

interface UsePurchaseReturn {
    purchase: (slug: string, quantity: number, billingPeriod: BillingPeriod) => void;
    cancel: (addonId: string, reason?: string) => void;
    updateQuantity: (addonId: string, newQuantity: number) => void;
    isPurchasing: boolean;
    isCanceling: boolean;
    isUpdating: boolean;
    error: string | null;
}

export function usePurchase(): UsePurchaseReturn {
    const [isPurchasing, setIsPurchasing] = useState(false);
    const [isCanceling, setIsCanceling] = useState(false);
    const [isUpdating, setIsUpdating] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const purchase = useCallback((slug: string, quantity: number, billingPeriod: BillingPeriod) => {
        setIsPurchasing(true);
        setError(null);

        router.post(
            addons.purchase.url(),
            {
                addon_slug: slug,
                quantity,
                billing_period: billingPeriod,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsPurchasing(false);
                },
                onError: (errors) => {
                    setError(Object.values(errors)[0] as string);
                    setIsPurchasing(false);
                },
            },
        );
    }, []);

    const cancel = useCallback((addonId: string, reason?: string) => {
        setIsCanceling(true);
        setError(null);

        router.post(
            addons.cancel.url({ addon: addonId }),
            { reason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsCanceling(false);
                },
                onError: (errors) => {
                    setError(Object.values(errors)[0] as string);
                    setIsCanceling(false);
                },
            },
        );
    }, []);

    const updateQuantity = useCallback((addonId: string, newQuantity: number) => {
        setIsUpdating(true);
        setError(null);

        router.patch(
            addons.update.url({ addon: addonId }),
            { quantity: newQuantity },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsUpdating(false);
                },
                onError: (errors) => {
                    setError(Object.values(errors)[0] as string);
                    setIsUpdating(false);
                },
            },
        );
    }, []);

    return {
        purchase,
        cancel,
        updateQuantity,
        isPurchasing,
        isCanceling,
        isUpdating,
        error,
    };
}
