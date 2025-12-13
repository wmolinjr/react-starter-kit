import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageHeaderActions,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import CustomerLayout from '@/layouts/customer-layout';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import customer from '@/routes/central/account';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Building2, Globe, Users, CreditCard, ArrowRightLeft, ExternalLink } from 'lucide-react';
import { type ReactElement } from 'react';

interface Domain {
    id: string;
    domain: string;
    is_primary: boolean;
}

interface TenantPlan {
    id: string;
    name: string;
    slug: string;
}

interface TenantDetail {
    id: string;
    name: string;
    slug: string;
    domains: Domain[];
    plan: TenantPlan | null;
    created_at: string;
}

interface Subscription {
    status: string;
    plan: string | null;
    current_period_end: string | null;
    cancel_at_period_end: boolean;
}

interface TenantShowProps {
    tenant: TenantDetail;
    subscription: Subscription | null;
    users_count: number;
}

function TenantShow({ tenant, subscription, users_count }: TenantShowProps) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('customer.dashboard.title'), href: customer.dashboard.url() },
        { title: t('customer.workspace.title'), href: customer.tenants.index.url() },
        { title: tenant.name, href: customer.tenants.show.url(tenant.id) },
    ];
    useSetBreadcrumbs(breadcrumbs);

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'active':
                return <Badge variant="default">{t('billing.page.active')}</Badge>;
            case 'trialing':
                return <Badge variant="secondary">{t('billing.page.trialing')}</Badge>;
            case 'past_due':
                return <Badge variant="destructive">{t('billing.page.past_due')}</Badge>;
            case 'canceled':
                return <Badge variant="outline">{t('billing.page.canceled')}</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <>
            <Head title={tenant.name} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Building2}>
                            {tenant.name}
                        </PageTitle>
                        <PageDescription className="flex items-center gap-1">
                            <Globe className="h-4 w-4" />
                            {tenant.domains[0]?.domain}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button asChild variant="outline">
                            <a href={`https://${tenant.domains[0]?.domain}`} target="_blank" rel="noopener noreferrer">
                                {t('customer.workspace.open')}
                                <ExternalLink className="ml-2 h-4 w-4" />
                            </a>
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {/* Plan Card */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('customer.subscription.current_plan')}
                                </CardTitle>
                                <CreditCard className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {tenant.plan?.name || t('customer.subscription.no_plan')}
                                </div>
                                {subscription && (
                                    <div className="flex items-center gap-2 mt-2">
                                        {getStatusBadge(subscription.status)}
                                        {subscription.cancel_at_period_end && (
                                            <span className="text-xs text-muted-foreground">
                                                {t('billing.page.cancels_at_period_end')}
                                            </span>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Users Card */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('customer.workspace.team_members')}
                                </CardTitle>
                                <Users className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{users_count}</div>
                                <p className="text-xs text-muted-foreground">
                                    {t('customer.workspace.active_users')}
                                </p>
                            </CardContent>
                        </Card>

                        {/* Transfer Card */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('customer.transfer.ownership')}
                                </CardTitle>
                                <ArrowRightLeft className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <Button asChild variant="outline" size="sm" className="w-full">
                                    <Link href={customer.transfers.create.url(tenant.id)}>
                                        {t('customer.transfer.ownership_title')}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Billing Section */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('customer.billing.title')}</CardTitle>
                            <CardDescription>
                                {t('customer.billing.description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {subscription ? (
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between p-4 border rounded-lg">
                                        <div>
                                            <p className="font-medium">{subscription.plan}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {subscription.current_period_end && (
                                                    <>
                                                        {t('billing.page.renews_on')}{' '}
                                                        {new Date(subscription.current_period_end).toLocaleDateString()}
                                                    </>
                                                )}
                                            </p>
                                        </div>
                                        {getStatusBadge(subscription.status)}
                                    </div>
                                    <div className="flex gap-2">
                                        <Button asChild variant="outline">
                                            <Link href={`/account/tenants/${tenant.id}/billing`}>
                                                {t('customer.billing.manage')}
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <div className="text-center py-8">
                                    <CreditCard className="mx-auto h-12 w-12 text-muted-foreground/50 mb-4" />
                                    <p className="text-muted-foreground mb-4">
                                        {t('customer.subscription.no_active')}
                                    </p>
                                    <Button asChild>
                                        <a href={`https://${tenant.domains[0]?.domain}/admin/billing`}>
                                            {t('customer.workspace.choose_plan')}
                                        </a>
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </PageContent>
            </Page>
        </>
    );
}

TenantShow.layout = (page: ReactElement) => <CustomerLayout>{page}</CustomerLayout>;

export default TenantShow;
