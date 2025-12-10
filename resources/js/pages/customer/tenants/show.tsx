import CustomerLayout from '@/layouts/customer-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Building2, Globe, Users, CreditCard, ArrowRightLeft, ExternalLink } from 'lucide-react';

interface TenantDetail {
    id: string;
    name: string;
    slug: string;
    domain: string;
    plan_name: string | null;
    created_at: string;
}

interface Subscription {
    status: string;
    plan: string | null;
    current_period_end: string | null;
    cancel_at_period_end: boolean;
}

interface TenantShowProps {
    tenant: { data: TenantDetail };
    subscription: Subscription | null;
    users_count: number;
}

export default function TenantShow({ tenant, subscription, users_count }: TenantShowProps) {
    const { t } = useLaravelReactI18n();
    const data = tenant.data;

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'active':
                return <Badge variant="default">{t('billing.active')}</Badge>;
            case 'trialing':
                return <Badge variant="secondary">{t('billing.trialing')}</Badge>;
            case 'past_due':
                return <Badge variant="destructive">{t('billing.past_due')}</Badge>;
            case 'canceled':
                return <Badge variant="outline">{t('billing.canceled')}</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <CustomerLayout
            breadcrumbs={[
                { title: t('customer.dashboard'), href: '/account' },
                { title: t('customer.workspaces'), href: '/account/tenants' },
                { title: data.name, href: `/account/tenants/${data.id}` },
            ]}
        >
            <Head title={data.name} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                            <Building2 className="h-6 w-6" />
                            {data.name}
                        </h1>
                        <p className="text-muted-foreground flex items-center gap-1">
                            <Globe className="h-4 w-4" />
                            {data.domain}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <a href={`https://${data.domain}`} target="_blank" rel="noopener noreferrer">
                            {t('customer.open_workspace')}
                            <ExternalLink className="ml-2 h-4 w-4" />
                        </a>
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {/* Plan Card */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                {t('customer.current_plan')}
                            </CardTitle>
                            <CreditCard className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {data.plan_name || t('customer.no_plan')}
                            </div>
                            {subscription && (
                                <div className="flex items-center gap-2 mt-2">
                                    {getStatusBadge(subscription.status)}
                                    {subscription.cancel_at_period_end && (
                                        <span className="text-xs text-muted-foreground">
                                            {t('billing.cancels_at_period_end')}
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
                                {t('customer.team_members')}
                            </CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{users_count}</div>
                            <p className="text-xs text-muted-foreground">
                                {t('customer.active_users')}
                            </p>
                        </CardContent>
                    </Card>

                    {/* Transfer Card */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                {t('customer.ownership')}
                            </CardTitle>
                            <ArrowRightLeft className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <Button asChild variant="outline" size="sm" className="w-full">
                                <Link href={`/account/tenants/${data.id}/transfer`}>
                                    {t('customer.transfer_ownership')}
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                {/* Billing Section */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('customer.billing')}</CardTitle>
                        <CardDescription>
                            {t('customer.billing_description')}
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
                                                    {t('billing.renews_on')}{' '}
                                                    {new Date(subscription.current_period_end).toLocaleDateString()}
                                                </>
                                            )}
                                        </p>
                                    </div>
                                    {getStatusBadge(subscription.status)}
                                </div>
                                <div className="flex gap-2">
                                    <Button asChild variant="outline">
                                        <Link href={`/account/tenants/${data.id}/billing`}>
                                            {t('customer.manage_billing')}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <div className="text-center py-8">
                                <CreditCard className="mx-auto h-12 w-12 text-muted-foreground/50 mb-4" />
                                <p className="text-muted-foreground mb-4">
                                    {t('customer.no_active_subscription')}
                                </p>
                                <Button asChild>
                                    <a href={`https://${data.domain}/admin/billing`}>
                                        {t('customer.choose_plan')}
                                    </a>
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </CustomerLayout>
    );
}
