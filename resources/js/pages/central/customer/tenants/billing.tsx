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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import customer from '@/routes/central/account';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CreditCard,
    Receipt,
    ArrowLeft,
    ExternalLink,
    Calendar,
    Building2,
    Check,
} from 'lucide-react';
import { type ReactElement } from 'react';

interface TenantInfo {
    id: string;
    name: string;
    plan: string | null;
    payment_method_id: string | null;
}

interface Subscription {
    status: string;
    current_period_end: string | null;
    cancel_at_period_end: boolean;
}

interface PaymentMethod {
    id: string;
    brand: string;
    last4: string;
    exp_month: number;
    exp_year: number;
}

interface Invoice {
    id: string;
    date: string;
    total: string;
    status: string;
}

interface TenantBillingProps {
    tenant: TenantInfo;
    subscription: Subscription | null;
    payment_method: PaymentMethod | null;
    invoices: Invoice[];
    available_payment_methods: PaymentMethod[];
}

function TenantBilling({
    tenant,
    subscription,
    payment_method,
    invoices,
    available_payment_methods,
}: TenantBillingProps) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('customer.dashboard.title'), href: customer.dashboard.url() },
        { title: t('customer.workspace.title'), href: customer.tenants.index.url() },
        { title: tenant.name, href: customer.tenants.show.url(tenant.id) },
        { title: t('customer.billing.title'), href: customer.tenants.billing.url(tenant.id) },
    ];
    useSetBreadcrumbs(breadcrumbs);

    const { data, setData, patch, processing } = useForm({
        payment_method_id: tenant.payment_method_id || '',
    });

    const handlePaymentMethodChange = (value: string) => {
        setData('payment_method_id', value === 'default' ? '' : value);
        patch(customer.tenants.paymentMethod.url(tenant.id), {
            preserveScroll: true,
        });
    };

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
            case 'paused':
                return <Badge variant="secondary">{t('billing.page.paused')}</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    const getInvoiceStatusBadge = (status: string) => {
        switch (status) {
            case 'paid':
                return <Badge variant="default">{t('billing.page.paid')}</Badge>;
            case 'open':
                return <Badge variant="secondary">{t('billing.page.open')}</Badge>;
            case 'failed':
                return <Badge variant="destructive">{t('billing.page.failed')}</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <>
            <Head title={`${t('customer.billing.title')} - ${tenant.name}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={CreditCard}>
                            {t('customer.billing.title')}
                        </PageTitle>
                        <PageDescription className="flex items-center gap-2">
                            <Building2 className="h-4 w-4" />
                            {tenant.name}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button asChild variant="outline">
                            <Link href={customer.tenants.show.url(tenant.id)}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                {t('common.back')}
                            </Link>
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-6">
                        {/* Current Plan Section */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('customer.subscription.current_plan')}</CardTitle>
                                <CardDescription>
                                    {t('customer.billing.plan_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {subscription ? (
                                    <div className="space-y-4">
                                        <div className="flex items-center justify-between p-4 border rounded-lg bg-muted/50">
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-xl font-bold">
                                                        {tenant.plan || t('customer.subscription.no_plan')}
                                                    </span>
                                                    {getStatusBadge(subscription.status)}
                                                </div>
                                                {subscription.current_period_end && (
                                                    <div className="flex items-center gap-1 text-sm text-muted-foreground">
                                                        <Calendar className="h-4 w-4" />
                                                        {subscription.cancel_at_period_end
                                                            ? t('billing.page.ends_on')
                                                            : t('billing.page.renews_on')}{' '}
                                                        {new Date(subscription.current_period_end).toLocaleDateString()}
                                                    </div>
                                                )}
                                            </div>
                                            <Button asChild variant="outline">
                                                <a href={`https://${tenant.name.toLowerCase().replace(/\s+/g, '-')}.test/admin/billing`} target="_blank" rel="noopener noreferrer">
                                                    {t('customer.billing.manage_in_workspace')}
                                                    <ExternalLink className="ml-2 h-4 w-4" />
                                                </a>
                                            </Button>
                                        </div>
                                        {subscription.cancel_at_period_end && (
                                            <div className="p-4 border border-destructive/50 rounded-lg bg-destructive/10">
                                                <p className="text-sm text-destructive">
                                                    {t('billing.page.subscription_cancellation_notice')}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="text-center py-8">
                                        <CreditCard className="mx-auto h-12 w-12 text-muted-foreground/50 mb-4" />
                                        <p className="text-muted-foreground mb-4">
                                            {t('customer.subscription.no_active')}
                                        </p>
                                        <Button asChild>
                                            <a href={`https://${tenant.name.toLowerCase().replace(/\s+/g, '-')}.test/admin/billing/plans`} target="_blank" rel="noopener noreferrer">
                                                {t('customer.workspace.choose_plan')}
                                                <ExternalLink className="ml-2 h-4 w-4" />
                                            </a>
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Payment Method Section */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('customer.payment.method')}</CardTitle>
                                <CardDescription>
                                    {t('customer.billing.payment_method_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {payment_method ? (
                                        <div className="flex items-center gap-4 p-4 border rounded-lg">
                                            <CreditCard className="h-8 w-8 text-muted-foreground" />
                                            <div className="flex-1">
                                                <p className="font-medium capitalize">
                                                    {payment_method.brand} •••• {payment_method.last4}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {t('customer.payment.expires')} {payment_method.exp_month}/{payment_method.exp_year}
                                                </p>
                                            </div>
                                            {!tenant.payment_method_id && (
                                                <Badge variant="secondary">{t('customer.payment.default')}</Badge>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="p-4 border rounded-lg border-dashed">
                                            <p className="text-muted-foreground">
                                                {t('customer.payment.no_method')}
                                            </p>
                                        </div>
                                    )}

                                    {available_payment_methods.length > 1 && (
                                        <div className="space-y-2">
                                            <label className="text-sm font-medium">
                                                {t('customer.billing.select_payment_method')}
                                            </label>
                                            <Select
                                                value={data.payment_method_id || 'default'}
                                                onValueChange={handlePaymentMethodChange}
                                                disabled={processing}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder={t('customer.payment.select')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="default">
                                                        {t('customer.payment.use_default')}
                                                    </SelectItem>
                                                    {available_payment_methods.map((pm) => (
                                                        <SelectItem key={pm.id} value={pm.id}>
                                                            <span className="flex items-center gap-2">
                                                                <span className="capitalize">{pm.brand}</span>
                                                                <span>•••• {pm.last4}</span>
                                                                {pm.id === tenant.payment_method_id && (
                                                                    <Check className="h-4 w-4 text-primary" />
                                                                )}
                                                            </span>
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <p className="text-xs text-muted-foreground">
                                                {t('customer.billing.payment_method_help')}
                                            </p>
                                        </div>
                                    )}

                                    <div className="pt-2">
                                        <Button asChild variant="outline" size="sm">
                                            <Link href={customer.paymentMethods.index.url()}>
                                                {t('customer.payment.manage_methods')}
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Recent Invoices Section */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle>{t('customer.invoices.recent')}</CardTitle>
                                        <CardDescription>
                                            {t('customer.billing.invoices_description')}
                                        </CardDescription>
                                    </div>
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={customer.invoices.index.url()}>
                                            {t('customer.invoices.view_all')}
                                        </Link>
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {invoices.length > 0 ? (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>{t('customer.invoice.date')}</TableHead>
                                                <TableHead>{t('customer.invoice.amount')}</TableHead>
                                                <TableHead>{t('customer.status')}</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {invoices.map((invoice) => (
                                                <TableRow key={invoice.id}>
                                                    <TableCell>
                                                        {new Date(invoice.date).toLocaleDateString()}
                                                    </TableCell>
                                                    <TableCell>{invoice.total}</TableCell>
                                                    <TableCell>
                                                        {getInvoiceStatusBadge(invoice.status)}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                ) : (
                                    <div className="text-center py-8">
                                        <Receipt className="mx-auto h-10 w-10 text-muted-foreground/50 mb-3" />
                                        <p className="text-muted-foreground">
                                            {t('customer.invoice.no_invoices')}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </PageContent>
            </Page>
        </>
    );
}

TenantBilling.layout = (page: ReactElement) => <CustomerLayout>{page}</CustomerLayout>;

export default TenantBilling;
