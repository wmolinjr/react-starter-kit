import { createContext, useContext, useState, useCallback, useEffect, type ReactNode } from 'react';

type BillingPeriodValue = 'monthly' | 'yearly';

interface BillingPeriodContextValue {
    /** Current billing period */
    period: BillingPeriodValue;

    /** Set the billing period */
    setPeriod: (period: BillingPeriodValue) => void;

    /** Toggle between monthly and yearly */
    toggle: () => void;

    /** Check if current period is yearly */
    isYearly: boolean;

    /** Check if current period is monthly */
    isMonthly: boolean;
}

const STORAGE_KEY = 'billing-period-preference';

const BillingPeriodContext = createContext<BillingPeriodContextValue | null>(null);

/**
 * BillingPeriodProvider - Provides billing period state to children
 *
 * @example
 * // Wrap your billing pages
 * <BillingPeriodProvider>
 *     <BillingPage />
 * </BillingPeriodProvider>
 */
export function BillingPeriodProvider({ children }: { children: ReactNode }) {
    const [period, setPeriodState] = useState<BillingPeriodValue>(() => {
        if (typeof window === 'undefined') return 'monthly';

        const stored = localStorage.getItem(STORAGE_KEY);
        return (stored === 'yearly' || stored === 'monthly') ? stored : 'monthly';
    });

    // Persist to localStorage
    useEffect(() => {
        if (typeof window !== 'undefined') {
            localStorage.setItem(STORAGE_KEY, period);
        }
    }, [period]);

    const setPeriod = useCallback((newPeriod: BillingPeriodValue) => {
        setPeriodState(newPeriod);
    }, []);

    const toggle = useCallback(() => {
        setPeriodState((prev) => (prev === 'monthly' ? 'yearly' : 'monthly'));
    }, []);

    const value: BillingPeriodContextValue = {
        period,
        setPeriod,
        toggle,
        isYearly: period === 'yearly',
        isMonthly: period === 'monthly',
    };

    return (
        <BillingPeriodContext.Provider value={value}>
            {children}
        </BillingPeriodContext.Provider>
    );
}

/**
 * useBillingPeriod - Access billing period state
 *
 * Must be used within a BillingPeriodProvider.
 *
 * @example
 * const { period, setPeriod, toggle, isYearly } = useBillingPeriod();
 *
 * @example
 * // In a pricing toggle
 * <PricingToggle
 *     value={period}
 *     onChange={setPeriod}
 *     savings="Save 20%"
 * />
 */
export function useBillingPeriod(): BillingPeriodContextValue {
    const context = useContext(BillingPeriodContext);

    if (!context) {
        throw new Error('useBillingPeriod must be used within a BillingPeriodProvider');
    }

    return context;
}

/**
 * useBillingPeriodSafe - Access billing period state with fallback
 *
 * Returns default values if not within a provider (useful for standalone components).
 */
export function useBillingPeriodSafe(): BillingPeriodContextValue {
    const context = useContext(BillingPeriodContext);

    if (!context) {
        return {
            period: 'monthly',
            setPeriod: () => {},
            toggle: () => {},
            isYearly: false,
            isMonthly: true,
        };
    }

    return context;
}

/**
 * getBillingPeriodPrice - Helper to get price for current period
 *
 * @example
 * const price = getBillingPeriodPrice(plan.pricing, period);
 */
export function getBillingPeriodPrice<T extends { monthly?: unknown; yearly?: unknown }>(
    pricing: T,
    period: BillingPeriodValue
): T['monthly'] | T['yearly'] | undefined {
    return period === 'yearly' ? pricing.yearly : pricing.monthly;
}

/**
 * calculateYearlySavings - Calculate savings percentage for yearly billing
 *
 * @example
 * const savings = calculateYearlySavings(900, 9000); // ~17%
 */
export function calculateYearlySavings(
    monthlyPrice: number,
    yearlyPrice: number
): number {
    const monthlyTotal = monthlyPrice * 12;
    if (monthlyTotal === 0) return 0;

    const savings = ((monthlyTotal - yearlyPrice) / monthlyTotal) * 100;
    return Math.round(savings);
}
