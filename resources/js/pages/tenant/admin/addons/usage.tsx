import admin from '@/routes/tenant/admin';
import { Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import AdminLayout from '@/layouts/tenant/admin-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { UsageMeter } from '@/components/tenant/addons/usage-meter';
import { useAddons } from '@/hooks/tenant/use-addons';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

function AddonsUsage() {
    const { t } = useLaravelReactI18n();
    const { active } = useAddons();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: 'Add-ons', href: admin.addons.index.url() },
        { title: t('tenant.addons.usage'), href: admin.addons.usage.url() },
    ];

    const meteredAddons = active.filter((addon) => addon.is_metered);

    return (
        <>
            <Head title={t('tenant.addons.usage')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('tenant.addons.usage_dashboard')}</PageTitle>
                        <PageDescription>{t('tenant.addons.usage_description')}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    {meteredAddons.length === 0 ? (
                        <Card>
                            <CardContent className="py-12 text-center">
                                <p className="text-muted-foreground">{t('tenant.addons.no_metered_addons')}</p>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2">
                            {meteredAddons.map((addon) => (
                                <Card key={addon.id}>
                                    <CardHeader>
                                        <CardTitle className="text-lg">{addon.name}</CardTitle>
                                        <CardDescription>{t('tenant.addons.current_billing_period')}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <UsageMeter
                                            label={t('tenant.addons.usage')}
                                            used={addon.metered_usage || 0}
                                            limit={addon.quantity * 1000}
                                            unit={t('tenant.addons.units')}
                                        />
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </PageContent>
            </Page>
        </>
    );
}

AddonsUsage.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default AddonsUsage;
