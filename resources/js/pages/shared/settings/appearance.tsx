import { Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';

import AppearanceTabs from '@/components/appearance-tabs';
import HeadingSmall from '@/components/heading-small';
import { LanguageSelector } from '@/components/language-selector';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { type BreadcrumbItem } from '@/types';

import SettingsLayout from '@/layouts/settings/layout';
import SharedLayout from '@/layouts/shared-layout';
import { edit as editPreferences } from '@/routes/shared/settings/appearance';
import { edit as editProfile } from '@/routes/shared/settings/profile';

function useBreadcrumbs(): BreadcrumbItem[] {
    const { t } = useLaravelReactI18n();
    return [
        {
            title: t('settings.title'),
            href: editProfile().url,
        },
        {
            title: t('settings.nav.preferences'),
            href: editPreferences().url,
        },
    ];
}

export default function Appearance() {
    const { t } = useLaravelReactI18n();
    const breadcrumbs = useBreadcrumbs();

    return (
        <SharedLayout breadcrumbs={breadcrumbs}>
            <Head title={t('settings.preferences.page_title')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title={t('settings.language.title')}
                        description={t('settings.language.description')}
                    />

                    <div className="grid gap-2">
                        <Label>{t('settings.language.preferred')}</Label>
                        <div className="max-w-xs">
                            <LanguageSelector variant="list" />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {t('settings.language.help')}
                        </p>
                    </div>
                </div>

                <Separator />

                <div className="space-y-6">
                    <HeadingSmall
                        title={t('settings.appearance.title')}
                        description={t('settings.appearance.description')}
                    />
                    <AppearanceTabs />
                </div>
            </SettingsLayout>
        </SharedLayout>
    );
}
