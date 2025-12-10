import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Globe } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useLocales } from '@/hooks/shared/use-locales';

export interface Translations {
    [locale: string]: string;
}

interface TranslatableInputProps {
    label: string;
    value: Translations;
    onChange: (value: Translations) => void;
    locales?: string[];
    localeLabels?: Record<string, string>;
    placeholder?: string | Record<string, string>;
    multiline?: boolean;
    required?: boolean;
    className?: string;
    disabled?: boolean;
}

/**
 * TranslatableInput - Multi-language input component
 *
 * Renders tabs for each locale with input/textarea fields.
 * Supports both single-line (Input) and multi-line (Textarea) modes.
 *
 * Uses locale configuration from backend via useLocales hook.
 * To customize locales per app, configure APP_LOCALES in .env
 *
 * @example
 * <TranslatableInput
 *   label="Name"
 *   value={{ en: 'Users', pt_BR: 'Usuários' }}
 *   onChange={(v) => setData('name', v)}
 *   required
 * />
 */
export function TranslatableInput({
    label,
    value,
    onChange,
    locales: localesProp,
    localeLabels: localeLabelsProps,
    placeholder,
    multiline = false,
    required = false,
    className,
    disabled = false,
}: TranslatableInputProps) {
    // Get locale config from backend (single source of truth)
    const { availableLocales, localeLabels: sharedLabels } = useLocales();

    // Allow override via props, but default to shared config
    const locales = localesProp ?? availableLocales;
    const localeLabels = localeLabelsProps ?? sharedLabels;

    const [activeTab, setActiveTab] = useState(locales[0]);

    const handleChange = (locale: string, newValue: string) => {
        onChange({
            ...value,
            [locale]: newValue,
        });
    };

    const getPlaceholder = (locale: string): string => {
        if (!placeholder) return '';
        if (typeof placeholder === 'string') return placeholder;
        return placeholder[locale] || '';
    };

    const hasTranslation = (locale: string): boolean => {
        return !!value[locale]?.trim();
    };

    const missingTranslations = locales.filter((l) => !hasTranslation(l));

    return (
        <div className={cn('space-y-2', className)}>
            <div className="flex items-center gap-2">
                <Label className="flex items-center gap-1.5">
                    <Globe className="text-muted-foreground h-3.5 w-3.5" />
                    {label}
                    {required && <span className="text-destructive">*</span>}
                </Label>
                {missingTranslations.length > 0 && missingTranslations.length < locales.length && (
                    <Badge variant="outline" className="text-muted-foreground text-xs">
                        {missingTranslations.length} missing
                    </Badge>
                )}
            </div>

            <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full gap-0">
                {locales.map((locale) => (
                    <TabsContent key={locale} value={locale} className="mt-0">
                        {multiline ? (
                            <Textarea
                                value={value[locale] || ''}
                                onChange={(e) => handleChange(locale, e.target.value)}
                                placeholder={getPlaceholder(locale)}
                                disabled={disabled}
                                className="min-h-[80px] rounded-b-none border-b-0 focus-visible:ring-0 focus-visible:ring-offset-0 focus-visible:border-ring"
                            />
                        ) : (
                            <Input
                                value={value[locale] || ''}
                                onChange={(e) => handleChange(locale, e.target.value)}
                                placeholder={getPlaceholder(locale)}
                                disabled={disabled}
                                className="rounded-b-none border-b-0 focus-visible:ring-0 focus-visible:ring-offset-0 focus-visible:border-ring"
                            />
                        )}
                    </TabsContent>
                ))}

                <TabsList className="h-7 w-full justify-start rounded-t-none border border-t-0 bg-transparent p-0">
                    {locales.map((locale) => (
                        <TabsTrigger
                            key={locale}
                            value={locale}
                            className={cn(
                                'h-full rounded-none border-r px-3 text-xs data-[state=active]:bg-muted data-[state=active]:shadow-none',
                                !hasTranslation(locale) && locale !== activeTab && 'text-muted-foreground',
                            )}
                        >
                            {localeLabels[locale] || locale}
                            {hasTranslation(locale) && <span className="ml-1 text-green-500">•</span>}
                        </TabsTrigger>
                    ))}
                </TabsList>
            </Tabs>
        </div>
    );
}

/**
 * Helper to ensure translations object has all required locales.
 *
 * IMPORTANT: This function is designed to work with TranslatableInput.
 * For the locales parameter, prefer using the useLocales() hook:
 *
 * @example
 * const { availableLocales } = useLocales();
 * const translations = ensureTranslations(data.name, availableLocales);
 */
export function ensureTranslations(
    value: Translations | string | undefined,
    locales: string[],
): Translations {
    if (!value) {
        return locales.reduce((acc, l) => ({ ...acc, [l]: '' }), {});
    }

    if (typeof value === 'string') {
        // Fallback: single string, use as value for all locales
        return locales.reduce((acc, l) => ({ ...acc, [l]: value }), {});
    }

    // Ensure all locales exist
    return locales.reduce((acc, l) => ({ ...acc, [l]: value[l] || '' }), {});
}
