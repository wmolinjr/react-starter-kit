import { Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';

import AppearanceTabs from '@/components/shared/settings/appearance-tabs';
import HeadingSmall from '@/components/shared/typography/heading-small';
import { LanguageSelector } from '@/components/shared/settings/language-selector';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AdminLayout from '@/layouts/central/admin-layout';
import CentralUserSettingsLayout from '@/layouts/central/user-settings-layout';
import settings from '@/routes/central/admin/settings';
import { type BreadcrumbItem } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

function Appearance() {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('settings.page.title'),
            href: settings.profile.edit().url,
        },
        {
            title: t('settings.nav.preferences'),
            href: settings.appearance.edit().url,
        },
    ];

    useSetBreadcrumbs(breadcrumbs);

    return (
        <>
            <Head title={t('settings.preferences.page_title')} />

            <CentralUserSettingsLayout>
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
            </CentralUserSettingsLayout>
        </>
    );
}

Appearance.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default Appearance;
