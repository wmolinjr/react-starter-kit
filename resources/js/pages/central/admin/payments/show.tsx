import { useState, type ReactElement } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ArrowLeft,
    CreditCard,
    CheckCircle,
    Clock,
    XCircle,
    RotateCcw,
    Building2,
    User,
    Calendar,
    DollarSign,
    FileText,
    ExternalLink,
} from 'lucide-react';

import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    type BreadcrumbItem,
    type PaymentAdminResource,
} from '@/types';
import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageHeaderActions,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { Link } from '@inertiajs/react';

interface AddonPurchaseData {
    id: string;
    addon?: { name: string; slug: string };
    bundle?: { name: string; slug: string };
    quantity: number;
    status: string;
}

interface Props {
    payment: PaymentAdminResource;
    purchase: AddonPurchaseData | null;
}

function getStatusIcon(status: string) {
    switch (status) {
        case 'succeeded':
            return <CheckCircle className="h-5 w-5 text-green-500" />;
        case 'pending':
        case 'processing':
            return <Clock className="h-5 w-5 text-yellow-500" />;
        case 'failed':
        case 'canceled':
            return <XCircle className="h-5 w-5 text-red-500" />;
        case 'refunded':
        case 'partially_refunded':
            return <RotateCcw className="h-5 w-5 text-gray-500" />;
        default:
            return null;
    }
}

function getStatusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'succeeded':
            return 'default';
        case 'pending':
        case 'processing':
            return 'secondary';
        case 'failed':
        case 'canceled':
            return 'destructive';
        default:
            return 'outline';
    }
}

function PaymentShow({ payment, purchase }: Props) {
    const { t } = useLaravelReactI18n();
    const [isRefundDialogOpen, setIsRefundDialogOpen] = useState(false);

    const refundForm = useForm({
        amount: payment.refundable_amount,
        reason: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('admin.payments.title'), href: admin.payments.index.url() },
        { title: t('admin.payments.show_title'), href: admin.payments.show.url({ payment: payment.id }) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleRefund = () => {
        refundForm.post(admin.payments.refund.url({ payment: payment.id }), {
            preserveScroll: true,
            onSuccess: () => {
                setIsRefundDialogOpen(false);
                refundForm.reset();
            },
        });
    };

    return (
        <>
            <Head title={t('admin.payments.show_title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={CreditCard}>{t('admin.payments.show_title')}</PageTitle>
                        <PageDescription>
                            ID: {payment.id}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button variant="outline" asChild>
                            <Link href={admin.payments.index.url()}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                {t('common.back')}
                            </Link>
                        </Button>
                        {payment.can_refund && (
                            <Dialog open={isRefundDialogOpen} onOpenChange={setIsRefundDialogOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="destructive">
                                        <RotateCcw className="mr-2 h-4 w-4" />
                                        {t('admin.payments.refund')}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>{t('admin.payments.refund_confirm')}</DialogTitle>
                                        <DialogDescription>
                                            {t('admin.payments.refund_amount')}: {payment.formatted_refundable_amount}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="space-y-4 py-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="amount">{t('admin.payments.refund_amount')}</Label>
                                            <Input
                                                id="amount"
                                                type="number"
                                                min={1}
                                                max={payment.refundable_amount}
                                                value={refundForm.data.amount}
                                                onChange={(e) => refundForm.setData('amount', parseInt(e.target.value) || 0)}
                                            />
                                            <p className="text-sm text-muted-foreground">
                                                Max: {payment.formatted_refundable_amount}
                                            </p>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="reason">{t('admin.payments.refund_reason')}</Label>
                                            <Textarea
                                                id="reason"
                                                value={refundForm.data.reason}
                                                onChange={(e) => refundForm.setData('reason', e.target.value)}
                                                placeholder="Optional reason for the refund..."
                                            />
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button variant="outline" onClick={() => setIsRefundDialogOpen(false)}>
                                            {t('common.cancel')}
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            onClick={handleRefund}
                                            disabled={refundForm.processing}
                                        >
                                            {refundForm.processing ? t('common.loading') : t('admin.payments.refund_confirm')}
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        )}
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-6 md:grid-cols-2">
                        {/* Payment Status */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    {getStatusIcon(payment.status)}
                                    {t('admin.payments.status')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Status</span>
                                    <Badge variant={getStatusVariant(payment.status)} className="text-sm">
                                        {payment.status_label}
                                    </Badge>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">{t('admin.payments.amount')}</span>
                                    <span className="font-mono text-lg font-bold">{payment.formatted_amount}</span>
                                </div>
                                {payment.refunded_amount > 0 && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">{t('admin.payments.stats.refunded')}</span>
                                        <span className="font-mono text-red-500">{payment.formatted_refunded_amount}</span>
                                    </div>
                                )}
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">{t('admin.payments.payment_method')}</span>
                                    <Badge variant="outline">{payment.payment_method_label}</Badge>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">{t('admin.payments.provider')}</span>
                                    <Badge variant="secondary">{payment.provider}</Badge>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Timeline */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Calendar className="h-5 w-5" />
                                    Timeline
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Created</span>
                                    <span className="font-mono text-sm">
                                        {new Date(payment.created_at).toLocaleString('pt-BR')}
                                    </span>
                                </div>
                                {payment.paid_at && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Paid</span>
                                        <span className="font-mono text-sm text-green-600">
                                            {new Date(payment.paid_at).toLocaleString('pt-BR')}
                                        </span>
                                    </div>
                                )}
                                {payment.failed_at && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Failed</span>
                                        <span className="font-mono text-sm text-red-600">
                                            {new Date(payment.failed_at).toLocaleString('pt-BR')}
                                        </span>
                                    </div>
                                )}
                                {payment.refunded_at && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Refunded</span>
                                        <span className="font-mono text-sm text-gray-600">
                                            {new Date(payment.refunded_at).toLocaleString('pt-BR')}
                                        </span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Tenant Info */}
                        {payment.tenant && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Building2 className="h-5 w-5" />
                                        {t('admin.payments.tenant')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">ID</span>
                                        <span className="font-mono text-sm">{payment.tenant.id}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Name</span>
                                        <span>{payment.tenant.name}</span>
                                    </div>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={admin.tenants.show.url({ tenant: payment.tenant.id })}>
                                            <ExternalLink className="mr-2 h-4 w-4" />
                                            View Tenant
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        )}

                        {/* Customer Info */}
                        {payment.customer && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <User className="h-5 w-5" />
                                        {t('admin.payments.customer')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Name</span>
                                        <span>{payment.customer.name}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Email</span>
                                        <span className="font-mono text-sm">{payment.customer.email}</span>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Payment Method Details */}
                        {payment.payment_method_details && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <CreditCard className="h-5 w-5" />
                                        Payment Method Details
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Type</span>
                                        <span>{payment.payment_method_details.type}</span>
                                    </div>
                                    {payment.payment_method_details.brand && (
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">Brand</span>
                                            <span className="capitalize">{payment.payment_method_details.brand}</span>
                                        </div>
                                    )}
                                    {payment.payment_method_details.last_four && (
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">Last 4</span>
                                            <span className="font-mono">****{payment.payment_method_details.last_four}</span>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Related Purchase */}
                        {purchase && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <DollarSign className="h-5 w-5" />
                                        Related Purchase
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Type</span>
                                        <span>{purchase.addon ? 'Add-on' : purchase.bundle ? 'Bundle' : 'Unknown'}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Name</span>
                                        <span>{purchase.addon?.name ?? purchase.bundle?.name ?? '-'}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Quantity</span>
                                        <span>{purchase.quantity}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Status</span>
                                        <Badge variant="outline">{purchase.status}</Badge>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Provider Data */}
                        {payment.provider_payment_id && (
                            <Card className="md:col-span-2">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="h-5 w-5" />
                                        Provider Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Provider Payment ID</span>
                                        <span className="font-mono text-sm">{payment.provider_payment_id}</span>
                                    </div>
                                    {payment.description && (
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">Description</span>
                                            <span>{payment.description}</span>
                                        </div>
                                    )}
                                    {payment.metadata && Object.keys(payment.metadata).length > 0 && (
                                        <div>
                                            <span className="text-muted-foreground">Metadata</span>
                                            <pre className="mt-2 rounded-md bg-muted p-4 text-sm overflow-auto">
                                                {JSON.stringify(payment.metadata, null, 2)}
                                            </pre>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </PageContent>
            </Page>
        </>
    );
}

PaymentShow.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default PaymentShow;
