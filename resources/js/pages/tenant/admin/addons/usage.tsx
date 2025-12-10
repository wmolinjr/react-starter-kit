import admin from '@/routes/tenant/admin';
import { Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import AdminLayout from '@/layouts/tenant/admin-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { UsageProgress } from '@/components/shared/billing';
import { useAddons } from '@/hooks/tenant/use-addons';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import { Activity } from 'lucide-react';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

function AddonsUsage() {
    const { t } = useLaravelReactI18n();
    const { active } = useAddons();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('tenant.addons.title', { default: 'Add-ons' }), href: admin.addons.index.url() },
        { title: t('tenant.addons.usage', { default: 'Usage' }), href: admin.addons.usage.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const meteredAddons = active.filter((addon) => addon.is_metered);

    return (
        <>
            <Head title={t('tenant.addons.usage', { default: 'Usage' })} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>
                            <Activity className="mr-2 h-6 w-6" />
                            {t('tenant.addons.usage_dashboard', { default: 'Usage Dashboard' })}
                        </PageTitle>
                        <PageDescription>
                            {t('tenant.addons.usage_description', {
                                default: 'Track your metered addon usage for the current billing period',
                            })}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    {meteredAddons.length === 0 ? (
                        <Card>
                            <CardContent className="py-12 text-center">
                                <Activity className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                                <h3 className="text-lg font-semibold">
                                    {t('tenant.addons.no_metered_addons', { default: 'No metered add-ons' })}
                                </h3>
                                <p className="text-muted-foreground mt-2">
                                    {t('tenant.addons.no_metered_description', {
                                        default: 'You don\'t have any metered add-ons to track.',
                                    })}
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2">
                            {meteredAddons.map((addon) => (
                                <Card key={addon.id}>
                                    <CardHeader>
                                        <CardTitle className="text-lg">{addon.name}</CardTitle>
                                        <CardDescription>
                                            {t('tenant.addons.current_billing_period', {
                                                default: 'Current billing period',
                                            })}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <UsageProgress
                                            label={t('tenant.addons.usage', { default: 'Usage' })}
                                            used={addon.metered_usage || 0}
                                            limit={addon.quantity * 1000}
                                            showPercentage
                                            showValues
                                            size="lg"
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
