import CustomerLayout from '@/layouts/customer-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Building2, CreditCard, Receipt, ArrowRight, Plus } from 'lucide-react';

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
    tenants: { data: TenantSummary[] };
    stats: Stats;
}

export default function Dashboard({ customer, tenants, stats }: DashboardProps) {
    const { t } = useLaravelReactI18n();

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: customer.currency.toUpperCase(),
        }).format(amount / 100);
    };

    return (
        <CustomerLayout
            breadcrumbs={[{ title: t('customer.dashboard'), href: '/account' }]}
        >
            <Head title={t('customer.dashboard')} />

            <div className="space-y-6">
                {/* Welcome Section */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {t('customer.welcome_back', { name: customer.name })}
                    </h1>
                    <p className="text-muted-foreground">
                        {t('customer.dashboard_description')}
                    </p>
                </div>

                {/* Stats Grid */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                {t('customer.workspaces')}
                            </CardTitle>
                            <Building2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.tenant_count}</div>
                            <p className="text-xs text-muted-foreground">
                                {t('customer.active_workspaces')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                {t('customer.subscriptions')}
                            </CardTitle>
                            <Receipt className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.active_subscriptions}</div>
                            <p className="text-xs text-muted-foreground">
                                {t('customer.active_subscriptions')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                {t('customer.monthly_billing')}
                            </CardTitle>
                            <CreditCard className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatCurrency(stats.total_monthly_billing)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {t('customer.per_month')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                {t('customer.pending_transfers')}
                            </CardTitle>
                            <ArrowRight className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.pending_transfers}</div>
                            <p className="text-xs text-muted-foreground">
                                {t('customer.awaiting_action')}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Quick Actions */}
                {!customer.has_payment_method && (
                    <Card className="border-warning bg-warning/10">
                        <CardHeader>
                            <CardTitle className="text-warning">
                                {t('customer.add_payment_method')}
                            </CardTitle>
                            <CardDescription>
                                {t('customer.no_payment_method_warning')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button asChild>
                                <Link href="/account/payment-methods/create">
                                    <CreditCard className="mr-2 h-4 w-4" />
                                    {t('customer.add_payment_method')}
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {/* Workspaces List */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>{t('customer.your_workspaces')}</CardTitle>
                            <CardDescription>
                                {t('customer.workspaces_description')}
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link href="/account/tenants/create">
                                <Plus className="mr-2 h-4 w-4" />
                                {t('customer.create_workspace')}
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {tenants.data.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground">
                                <Building2 className="mx-auto h-12 w-12 mb-4 opacity-50" />
                                <p>{t('customer.no_workspaces')}</p>
                                <Button asChild className="mt-4">
                                    <Link href="/account/tenants/create">
                                        {t('customer.create_first_workspace')}
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {tenants.data.map((tenant) => (
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
                                                <Link href={`/account/tenants/${tenant.id}`}>
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
            </div>
        </CustomerLayout>
    );
}
