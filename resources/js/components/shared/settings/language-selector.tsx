import { router, usePage } from '@inertiajs/react';
import { Check, Globe } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTenant } from '@/hooks/tenant/use-tenant';
import centralProfile from '@/routes/central/admin/settings/profile';
import tenantProfile from '@/routes/tenant/admin/user-settings/profile';
import { type SharedData } from '@/types';

interface LanguageSelectorProps {
    variant?: 'dropdown' | 'list';
    showLabel?: boolean;
}

export function LanguageSelector({ variant = 'dropdown', showLabel = true }: LanguageSelectorProps) {
    const { locale, availableLocales, localeLabels } = usePage<SharedData>().props;
    const { isTenantContext } = useTenant();

    // Use appropriate route based on context
    const localeRoute = isTenantContext ? tenantProfile.locale : centralProfile.locale;

    const handleLocaleChange = (newLocale: string) => {
        if (newLocale === locale) return;

        // Persist to backend and hard reload to get new translations
        router.patch(
            localeRoute.url(),
            { locale: newLocale },
            {
                preserveScroll: true,
                onSuccess: () => {
                    // Hard reload to clear cached translations and re-initialize i18n provider
                    window.location.href = window.location.pathname;
                },
            }
        );
    };

    if (variant === 'list') {
        return (
            <div className="space-y-1">
                {availableLocales.map((loc) => (
                    <button
                        key={loc}
                        type="button"
                        onClick={() => handleLocaleChange(loc)}
                        className={`flex w-full items-center justify-between rounded-md px-3 py-2 text-sm transition-colors ${
                            locale === loc
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-accent hover:text-accent-foreground'
                        }`}
                    >
                        <span>{localeLabels[loc] || loc}</span>
                        {locale === loc && <Check className="h-4 w-4" />}
                    </button>
                ))}
            </div>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="gap-2">
                    <Globe className="h-4 w-4" />
                    {showLabel && <span>{localeLabels[locale] || locale}</span>}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {availableLocales.map((loc) => (
                    <DropdownMenuItem
                        key={loc}
                        onClick={() => handleLocaleChange(loc)}
                        className="flex items-center justify-between gap-2"
                    >
                        <span>{localeLabels[loc] || loc}</span>
                        {locale === loc && <Check className="h-4 w-4" />}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
