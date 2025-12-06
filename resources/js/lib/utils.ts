import { usePage } from '@inertiajs/react';
import { InertiaLinkProps } from '@inertiajs/react';
import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

import type { CurrencyConfig, PageProps } from '@/types';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Format price in cents to locale-aware currency string.
 * Uses the currency configuration from the backend.
 *
 * @param cents - Price in cents (e.g., 2900 = R$29.00)
 * @param currency - Optional currency config override
 * @returns Formatted price string (e.g., "R$ 29,00")
 */
export function formatPrice(cents: number | null | undefined, currency?: CurrencyConfig): string {
    if (cents === null || cents === undefined || cents === 0) return '-';

    // Use provided currency or try to get from page props
    const currencyConfig = currency ?? getCurrencyConfig();

    if (!currencyConfig) {
        // Fallback if no config available
        return `$${(cents / 100).toFixed(2)}`;
    }

    try {
        return new Intl.NumberFormat(currencyConfig.locale.replace('_', '-'), {
            style: 'currency',
            currency: currencyConfig.code.toUpperCase(),
        }).format(cents / 100);
    } catch {
        // Fallback on error
        return `${currencyConfig.symbol}${(cents / 100).toFixed(2)}`;
    }
}

/**
 * Get currency config from page props.
 * Call this inside a React component or hook.
 */
export function getCurrencyConfig(): CurrencyConfig | null {
    try {
        // This will only work inside React components
        // eslint-disable-next-line react-hooks/rules-of-hooks
        const { currency } = usePage<PageProps>().props;
        return currency;
    } catch {
        return null;
    }
}

export function isSameUrl(
    url1: NonNullable<InertiaLinkProps['href']>,
    url2: NonNullable<InertiaLinkProps['href']>,
) {
    return resolveUrl(url1) === resolveUrl(url2);
}

export function resolveUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}
