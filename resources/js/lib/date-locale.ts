/**
 * Date-fns locale mapping utility
 *
 * Maps Laravel locale codes to date-fns locale objects.
 * Add new locales here as needed - tree-shaking will remove unused imports.
 *
 * Laravel locale format: en, pt_BR, es (underscore)
 * date-fns locale format: enUS, ptBR, es (camelCase imports)
 */
import type { Locale } from 'date-fns';
import { enUS, es, ptBR } from 'date-fns/locale';
import { useLaravelReactI18n } from 'laravel-react-i18n';

/**
 * Mapping of Laravel locale codes to date-fns Locale objects
 */
const localeMap: Record<string, Locale> = {
    en: enUS,
    en_US: enUS,
    es: es,
    es_ES: es,
    pt_BR: ptBR,
    pt: ptBR,
};

/**
 * Get date-fns locale from Laravel locale code
 *
 * @param laravelLocale - Laravel locale code (e.g., 'pt_BR', 'en', 'es')
 * @returns date-fns Locale object or undefined for default (English)
 *
 * @example
 * ```tsx
 * import { getDateFnsLocale } from '@/lib/date-locale';
 * import { useLaravelReactI18n } from 'laravel-react-i18n';
 *
 * const { currentLocale } = useLaravelReactI18n();
 * const locale = getDateFnsLocale(currentLocale());
 *
 * format(date, 'PPP', { locale });
 * ```
 */
export function getDateFnsLocale(laravelLocale: string): Locale | undefined {
    return localeMap[laravelLocale];
}

/**
 * Hook to get date-fns locale based on current Laravel locale
 *
 * @example
 * ```tsx
 * import { useDateFnsLocale } from '@/lib/date-locale';
 *
 * function MyComponent() {
 *     const locale = useDateFnsLocale();
 *     return <Calendar locale={locale} />;
 * }
 * ```
 */
export function useDateFnsLocale(): Locale | undefined {
    const { currentLocale } = useLaravelReactI18n();
    return getDateFnsLocale(currentLocale());
}

/**
 * List of supported locales for reference
 */
export const supportedLocales = ['en', 'es', 'pt_BR'] as const;
export type SupportedLocale = (typeof supportedLocales)[number];
