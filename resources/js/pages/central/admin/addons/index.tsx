import { Head } from '@inertiajs/react';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { type BreadcrumbItem, type InertiaPaginatedResponse, type AddonSubscriptionResource } from '@/types';
import { type AddonManagementStats } from '@/types/common';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { formatPrice } from '@/lib/utils';

interface Props {
    addons: InertiaPaginatedResponse<AddonSubscriptionResource>;
    stats: AddonManagementStats;
}

function AdminAddonsIndex({ addons, stats }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.addon_management'), href: admin.addons.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    return (
        <>
            <Head title={t('admin.addons.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('admin.addons.title')}</PageTitle>
                        <PageDescription>{t('admin.addons.description')}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>{t('admin.addons.total_addons')}</CardDescription>
                            <CardTitle className="text-2xl">{stats.total_addons}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>{t('admin.addons.active_addons')}</CardDescription>
                            <CardTitle className="text-2xl">{stats.active_addons}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>{t('admin.addons.total_revenue')}</CardDescription>
                            <CardTitle className="text-2xl">
                                {formatPrice(stats.total_revenue)}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>{t('admin.addons.tenants_with_addons')}</CardDescription>
                            <CardTitle className="text-2xl">{stats.tenants_with_addons}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.addons.all_addons')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('admin.addons.tenant')}</TableHead>
                                    <TableHead>{t('admin.addons.addon')}</TableHead>
                                    <TableHead>{t('admin.addons.quantity')}</TableHead>
                                    <TableHead>{t('admin.addons.price')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead>{t('admin.addons.billing')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {addons.data.map((addon) => (
                                    <TableRow key={addon.id}>
                                        <TableCell className="font-medium">
                                            {addon.tenant?.name || 'N/A'}
                                        </TableCell>
                                        <TableCell>{addon.name}</TableCell>
                                        <TableCell>{addon.quantity}</TableCell>
                                        <TableCell>{addon.formatted_price}</TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={addon.is_active ? 'default' : 'secondary'}
                                            >
                                                {addon.status_label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{addon.billing_period_label}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
                </PageContent>
            </Page>
        </>
    );
}

AdminAddonsIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default AdminAddonsIndex;
