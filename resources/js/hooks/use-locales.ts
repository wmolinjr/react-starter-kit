import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import type { PageProps, LocaleConfig } from '@/types';

export interface Translations {
    [locale: string]: string;
}

/**
 * Hook to access locale configuration from Inertia shared props.
 *
 * Single source of truth for i18n configuration in the frontend.
 * All locale data is configured in the backend (config/app.php) and
 * shared via HandleInertiaRequests middleware.
 *
 * @example
 * const { locale, availableLocales, localeLabels, getLabel, ensureTranslations } = useLocales();
 *
 * // Use in forms
 * const { data, setData } = useForm({
 *   name: ensureTranslations(plan?.name),
 * });
 *
 * // Use in TranslatableInput
 * <TranslatableInput
 *   locales={availableLocales}
 *   localeLabels={localeLabels}
 * />
 */
export function useLocales(): LocaleConfig & {
    getLabel: (locale: string) => string;
    isSupported: (locale: string) => boolean;
    ensureTranslations: (value: Translations | string | undefined) => Translations;
} {
    const {
        locale,
        fallbackLocale,
        availableLocales,
        localeLabels,
    } = usePage<PageProps>().props;

    /**
     * Ensure translations object has all required locales.
     * Uses availableLocales from the backend config.
     */
    const ensureTranslations = useCallback((value: Translations | string | undefined): Translations => {
        if (!value) {
            return availableLocales.reduce((acc, l) => ({ ...acc, [l]: '' }), {});
        }

        if (typeof value === 'string') {
            return availableLocales.reduce((acc, l) => ({ ...acc, [l]: value }), {});
        }

        return availableLocales.reduce((acc, l) => ({ ...acc, [l]: value[l] || '' }), {});
    }, [availableLocales]);

    return {
        /**
         * Current application locale (e.g., "en", "pt_BR")
         */
        locale,

        /**
         * Fallback locale when translation is not available
         */
        fallbackLocale,

        /**
         * List of all supported locales (from APP_LOCALES env)
         */
        availableLocales,

        /**
         * Human-readable labels for each locale (e.g., { en: "English", pt_BR: "Português" })
         */
        localeLabels,

        /**
         * Get human-readable label for a locale code
         * Falls back to the locale code itself if not found
         */
        getLabel: (localeCode: string): string => {
            return localeLabels[localeCode] || localeCode;
        },

        /**
         * Check if a locale is supported
         */
        isSupported: (localeCode: string): boolean => {
            return availableLocales.includes(localeCode);
        },

        /**
         * Ensure translations object has all required locales.
         * Automatically uses availableLocales from backend config.
         */
        ensureTranslations,
    };
}
