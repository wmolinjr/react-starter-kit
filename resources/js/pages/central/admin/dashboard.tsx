import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import AdminLayout from '@/layouts/central/admin-layout';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import admin from '@/routes/central/admin';
import { type BreadcrumbItem, type CentralDashboardStatsResource } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Building2, CreditCard, Layers, Network, Package, Shield, Users } from 'lucide-react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { type ReactElement } from 'react';

interface Props {
    stats: CentralDashboardStatsResource;
}

function AdminDashboard({ stats }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
    ];
    useSetBreadcrumbs(breadcrumbs);

    return (
        <>
            <Head title={t('dashboard.page.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle className="flex items-center gap-2 text-3xl">
                            <Shield className="h-8 w-8" />
                            {t('dashboard.page.title')}
                        </PageTitle>
                        <PageDescription>{t('dashboard.page.description')}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">{t('dashboard.page.tenants')}</CardTitle>
                            <Building2 className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_tenants}</div>
                            <Link href={admin.tenants.index.url()} className="text-muted-foreground text-xs hover:underline">
                                {t('dashboard.page.view_all_tenants')}
                            </Link>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">{t('dashboard.page.admins')}</CardTitle>
                            <Shield className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_admins}</div>
                            <p className="text-muted-foreground text-xs">
                                {t('dashboard.page.central_admins')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">{t('dashboard.page.active_addons')}</CardTitle>
                            <Package className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_addons}</div>
                            <Link href={admin.addons.index.url()} className="text-muted-foreground text-xs hover:underline">
                                {t('dashboard.page.view_addons')}
                            </Link>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">{t('dashboard.page.plans')}</CardTitle>
                            <CreditCard className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_plans}</div>
                            <Link href={admin.plans.index.url()} className="text-muted-foreground text-xs hover:underline">
                                {t('dashboard.page.manage_plans')}
                            </Link>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('dashboard.page.quick_actions')}</CardTitle>
                        <CardDescription>{t('dashboard.page.quick_actions_description')}</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2 md:grid-cols-2">
                        <Link
                            href={admin.tenants.index.url()}
                            className="hover:bg-muted flex items-center gap-2 rounded-lg border p-3"
                        >
                            <Building2 className="h-5 w-5" />
                            <div>
                                <p className="font-medium">{t('dashboard.page.manage_tenants')}</p>
                                <p className="text-muted-foreground text-xs">{t('dashboard.page.manage_tenants_description')}</p>
                            </div>
                        </Link>
                        <Link
                            href={admin.users.index.url()}
                            className="hover:bg-muted flex items-center gap-2 rounded-lg border p-3"
                        >
                            <Users className="h-5 w-5" />
                            <div>
                                <p className="font-medium">{t('dashboard.page.manage_users')}</p>
                                <p className="text-muted-foreground text-xs">{t('dashboard.page.manage_users_description')}</p>
                            </div>
                        </Link>
                        <Link
                            href={admin.plans.index.url()}
                            className="hover:bg-muted flex items-center gap-2 rounded-lg border p-3"
                        >
                            <CreditCard className="h-5 w-5" />
                            <div>
                                <p className="font-medium">{t('dashboard.page.plan_catalog')}</p>
                                <p className="text-muted-foreground text-xs">{t('dashboard.page.plan_catalog_description')}</p>
                            </div>
                        </Link>
                        <Link
                            href={admin.catalog.index.url()}
                            className="hover:bg-muted flex items-center gap-2 rounded-lg border p-3"
                        >
                            <Package className="h-5 w-5" />
                            <div>
                                <p className="font-medium">{t('dashboard.page.addon_catalog')}</p>
                                <p className="text-muted-foreground text-xs">{t('dashboard.page.addon_catalog_description')}</p>
                            </div>
                        </Link>
                        <Link
                            href={admin.bundles.index.url()}
                            className="hover:bg-muted flex items-center gap-2 rounded-lg border p-3"
                        >
                            <Layers className="h-5 w-5" />
                            <div>
                                <p className="font-medium">{t('dashboard.page.bundle_catalog')}</p>
                                <p className="text-muted-foreground text-xs">{t('dashboard.page.bundle_catalog_description')}</p>
                            </div>
                        </Link>
                        <Link
                            href={admin.roles.index.url()}
                            className="hover:bg-muted flex items-center gap-2 rounded-lg border p-3"
                        >
                            <Shield className="h-5 w-5" />
                            <div>
                                <p className="font-medium">{t('dashboard.page.manage_roles')}</p>
                                <p className="text-muted-foreground text-xs">{t('dashboard.page.manage_roles_description')}</p>
                            </div>
                        </Link>
                        <Link
                            href={admin.federation.index.url()}
                            className="hover:bg-muted flex items-center gap-2 rounded-lg border p-3 md:col-span-2"
                        >
                            <Network className="h-5 w-5" />
                            <div>
                                <p className="font-medium">{t('dashboard.page.federation_groups')}</p>
                                <p className="text-muted-foreground text-xs">{t('dashboard.page.federation_groups_description')}</p>
                            </div>
                        </Link>
                    </CardContent>
                </Card>
                </PageContent>
            </Page>
        </>
    );
}

AdminDashboard.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default AdminDashboard;
