import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import CustomerLayout from '@/layouts/customer-layout';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import customerRoutes from '@/routes/central/account';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Building2, CreditCard, Receipt, ArrowRight, Plus, LayoutDashboard } from 'lucide-react';
import { type ReactElement } from 'react';

interface TenantSummary {
    id: string;
    name: string;
    domain: string;
    plan_name: string | null;
}

interface Stats {
    tenant_count: number;
    active_subscriptions: number;
    pending_transfers: number;
    total_monthly_billing: number;
}

interface Customer {
    id: string;
    name: string;
    email: string;
    currency: string;
    has_payment_method: boolean;
}

interface DashboardProps {
    customer: Customer;
    tenants: TenantSummary[];
    stats: Stats;
}

function Dashboard({ customer, tenants, stats }: DashboardProps) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('customer.dashboard.title'), href: customerRoutes.dashboard.url() },
    ];
    useSetBreadcrumbs(breadcrumbs);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: customer.currency.toUpperCase(),
        }).format(amount / 100);
    };

    return (
        <>
            <Head title={t('customer.dashboard.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={LayoutDashboard}>
                            {t('customer.dashboard.welcome', { name: customer.name })}
                        </PageTitle>
                        <PageDescription>
                            {t('customer.dashboard.description')}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    {/* Stats Grid */}
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('customer.workspace.title')}
                                </CardTitle>
                                <Building2 className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.tenant_count}</div>
                                <p className="text-xs text-muted-foreground">
                                    {t('customer.workspace.active_workspaces')}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('customer.subscription.title')}
                                </CardTitle>
                                <Receipt className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.active_subscriptions}</div>
                                <p className="text-xs text-muted-foreground">
                                    {t('customer.subscription.active')}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('customer.billing.monthly')}
                                </CardTitle>
                                <CreditCard className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {formatCurrency(stats.total_monthly_billing)}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {t('customer.subscription.per_month')}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('customer.transfer.pending')}
                                </CardTitle>
                                <ArrowRight className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.pending_transfers}</div>
                                <p className="text-xs text-muted-foreground">
                                    {t('customer.status.awaiting_action')}
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Quick Actions */}
                    {!customer.has_payment_method && (
                        <Card className="border-warning bg-warning/10">
                            <CardHeader>
                                <CardTitle className="text-warning">
                                    {t('customer.payment.add_method')}
                                </CardTitle>
                                <CardDescription>
                                    {t('customer.payment.no_warning')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button asChild>
                                    <Link href={customerRoutes.paymentMethods.create.url()}>
                                        <CreditCard className="mr-2 h-4 w-4" />
                                        {t('customer.payment.add_method')}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    {/* Workspaces List */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{t('customer.workspace.your_workspaces')}</CardTitle>
                                <CardDescription>
                                    {t('customer.workspace.workspaces_description')}
                                </CardDescription>
                            </div>
                            <Button asChild variant="outline" size="sm">
                                <Link href={customerRoutes.tenants.create.url()}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('customer.workspace.create')}
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {tenants.length === 0 ? (
                                <div className="text-center py-8 text-muted-foreground">
                                    <Building2 className="mx-auto h-12 w-12 mb-4 opacity-50" />
                                    <p>{t('customer.workspace.no_workspaces')}</p>
                                    <Button asChild className="mt-4">
                                        <Link href={customerRoutes.tenants.create.url()}>
                                            {t('customer.workspace.create_first')}
                                        </Link>
                                    </Button>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {tenants.map((tenant) => (
                                        <div
                                            key={tenant.id}
                                            className="flex items-center justify-between rounded-lg border p-4"
                                        >
                                            <div className="space-y-1">
                                                <p className="font-medium">{tenant.name}</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {tenant.domain}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {tenant.plan_name && (
                                                    <span className="text-xs bg-primary/10 text-primary px-2 py-1 rounded">
                                                        {tenant.plan_name}
                                                    </span>
                                                )}
                                                <Button asChild variant="ghost" size="sm">
                                                    <Link href={customerRoutes.tenants.show.url(tenant.id)}>
                                                        {t('common.view')}
                                                        <ArrowRight className="ml-2 h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </PageContent>
            </Page>
        </>
    );
}

Dashboard.layout = (page: ReactElement) => <CustomerLayout>{page}</CustomerLayout>;

export default Dashboard;
