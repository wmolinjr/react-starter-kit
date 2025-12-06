import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTenant } from '@/hooks/use-tenant';
import TenantAdminLayout from '@/layouts/tenant-admin-layout';
import admin from '@/routes/tenant/admin';
import { Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { BarChart3, CreditCard, FolderOpen, TrendingUp, Users } from 'lucide-react';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/page';
import { type BreadcrumbItem } from '@/types';

export default function TenantDashboard() {
    const { t } = useLaravelReactI18n();
    const { tenant, subscription, hasActiveSubscription, isOnTrial } = useTenant();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
    ];

    return (
        <TenantAdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>Dashboard</PageTitle>
                        <PageDescription>{t('tenant.dashboard.welcome', { name: tenant?.name ?? '' })}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    {/* Subscription Status */}
                    {subscription && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CreditCard className="h-5 w-5" />
                                    {t('tenant.dashboard.subscription_status')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center gap-4">
                                    <div className={`h-3 w-3 rounded-full ${hasActiveSubscription ? 'bg-green-500' : 'bg-yellow-500'}`} />
                                    <div>
                                        <p className="font-medium">
                                            {isOnTrial ? t('tenant.dashboard.trial_period') : subscription.active ? t('tenant.dashboard.active') : t('tenant.dashboard.inactive')}
                                        </p>
                                        {subscription.trial_ends_at && (
                                            <p className="text-sm text-muted-foreground">
                                                {t('tenant.dashboard.trial_ends_at', { date: new Date(subscription.trial_ends_at).toLocaleDateString('pt-BR') })}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Stats Grid */}
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('tenant.dashboard.members')}
                                </CardTitle>
                                <Users className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">-</div>
                                <p className="text-xs text-muted-foreground">
                                    {t('tenant.dashboard.manage_team')}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('tenant.dashboard.projects')}
                                </CardTitle>
                                <FolderOpen className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">-</div>
                                <p className="text-xs text-muted-foreground">
                                    {t('tenant.dashboard.view_all_projects')}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('tenant.dashboard.activity')}
                                </CardTitle>
                                <TrendingUp className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">-</div>
                                <p className="text-xs text-muted-foreground">
                                    {t('tenant.dashboard.last_7_days')}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('tenant.dashboard.reports')}
                                </CardTitle>
                                <BarChart3 className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">-</div>
                                <p className="text-xs text-muted-foreground">
                                    {t('tenant.dashboard.view_reports')}
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Quick Actions */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('tenant.dashboard.quick_access')}</CardTitle>
                            <CardDescription>
                                {t('tenant.dashboard.quick_access_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <a href={admin.projects.index.url()} className="block p-4 border rounded-lg hover:bg-accent transition-colors">
                                    <FolderOpen className="h-8 w-8 mb-2 text-primary" />
                                    <h3 className="font-semibold">{t('tenant.dashboard.projects')}</h3>
                                    <p className="text-sm text-muted-foreground">{t('tenant.dashboard.manage_projects')}</p>
                                </a>
                                <a href={admin.team.index.url()} className="block p-4 border rounded-lg hover:bg-accent transition-colors">
                                    <Users className="h-8 w-8 mb-2 text-primary" />
                                    <h3 className="font-semibold">{t('tenant.dashboard.team')}</h3>
                                    <p className="text-sm text-muted-foreground">{t('tenant.dashboard.manage_members')}</p>
                                </a>
                                <a href={admin.billing.index.url()} className="block p-4 border rounded-lg hover:bg-accent transition-colors">
                                    <CreditCard className="h-8 w-8 mb-2 text-primary" />
                                    <h3 className="font-semibold">{t('tenant.dashboard.billing')}</h3>
                                    <p className="text-sm text-muted-foreground">{t('tenant.dashboard.plans_and_invoices')}</p>
                                </a>
                            </div>
                        </CardContent>
                    </Card>
                </PageContent>
            </Page>
        </TenantAdminLayout>
    );
}
