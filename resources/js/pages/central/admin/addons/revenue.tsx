import { Head } from '@inertiajs/react';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { formatPrice } from '@/lib/utils';

interface RevenueByType {
    addon_type: string;
    total: number;
}

interface Props {
    monthly_revenue: number;
    yearly_revenue: number;
    revenue_by_type: RevenueByType[];
    formatted_monthly: string;
    formatted_yearly: string;
}

function AdminAddonsRevenue({
    monthly_revenue,
    yearly_revenue,
    revenue_by_type,
    formatted_monthly,
    formatted_yearly,
}: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: admin.dashboard.url() },
        { title: t('admin.addons.title'), href: admin.addons.index.url() },
        { title: t('admin.addons.revenue'), href: admin.addons.revenue.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const totalRevenue = monthly_revenue + yearly_revenue / 12;
    const formattedTotal = formatPrice(Math.round(totalRevenue));

    return (
        <>
            <Head title={t('admin.addons.revenue_dashboard')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('admin.addons.revenue_dashboard')}</PageTitle>
                        <PageDescription>{t('admin.addons.revenue_description')}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>{t('admin.addons.monthly_recurring')}</CardDescription>
                            <CardTitle className="text-3xl">{formatted_monthly}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-xs">{t('admin.addons.from_monthly')}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>{t('admin.addons.annual_revenue')}</CardDescription>
                            <CardTitle className="text-3xl">{formatted_yearly}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-xs">{t('admin.addons.from_yearly')}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>{t('admin.addons.estimated_mrr')}</CardDescription>
                            <CardTitle className="text-3xl">{formattedTotal}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-xs">{t('admin.addons.combined_monthly')}</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.addons.revenue_by_type')}</CardTitle>
                        <CardDescription>{t('admin.addons.revenue_breakdown')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {revenue_by_type.map((item) => (
                                <div key={item.addon_type} className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <div className="bg-primary h-3 w-3 rounded-full" />
                                        <span className="font-medium capitalize">
                                            {item.addon_type.replace('_', ' ')}
                                        </span>
                                    </div>
                                    <span className="text-muted-foreground">
                                        {formatPrice(item.total)}
                                    </span>
                                </div>
                            ))}
                            {revenue_by_type.length === 0 && (
                                <p className="text-muted-foreground text-center py-4">
                                    {t('admin.addons.no_revenue_data')}
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>
                </PageContent>
            </Page>
        </>
    );
}

AdminAddonsRevenue.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default AdminAddonsRevenue;
