import { Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';

import AppearanceTabs from '@/components/appearance-tabs';
import HeadingSmall from '@/components/heading-small';
import { LanguageSelector } from '@/components/language-selector';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import TenantAdminLayout from '@/layouts/tenant-admin-layout';
import TenantUserSettingsLayout from '@/layouts/tenant/user-settings-layout';
import userSettings from '@/routes/tenant/admin/user-settings';
import { type BreadcrumbItem } from '@/types';

export default function Appearance() {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('settings.title'),
            href: userSettings.profile.edit().url,
        },
        {
            title: t('settings.nav.preferences'),
            href: userSettings.appearance.edit().url,
        },
    ];

    return (
        <TenantAdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('settings.preferences.page_title')} />

            <TenantUserSettingsLayout>
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
            </TenantUserSettingsLayout>
        </TenantAdminLayout>
    );
}
