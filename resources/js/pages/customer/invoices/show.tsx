import CustomerLayout from '@/layouts/customer-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Download, Receipt, CreditCard, Building2, AlertCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface InvoiceLine {
    description: string;
    amount: number;
    amount_formatted: string;
    quantity: number;
}

interface PaymentMethodInfo {
    type: string;
    brand: string | null;
    last4: string | null;
}

interface TenantInfo {
    id: string;
    name: string;
}

interface Invoice {
    id: string;
    number: string;
    date: string;
    paid_at: string | null;
    due_date: string | null;
    amount: number;
    amount_formatted: string;
    fee: number;
    fee_formatted: string;
    net_amount: number;
    net_amount_formatted: string;
    currency: string;
    status: 'paid' | 'open' | 'failed' | 'refunded' | 'void';
    payment_type: string;
    provider: string;
    description: string | null;
    failure_message: string | null;
    is_refundable: boolean;
    amount_refunded: number;
    amount_refunded_formatted: string;
    payment_method: PaymentMethodInfo | null;
    tenant: TenantInfo | null;
    lines: InvoiceLine[];
}

interface InvoiceShowProps {
    invoice: Invoice;
}

export default function InvoiceShow({ invoice }: InvoiceShowProps) {
    const { t } = useLaravelReactI18n();

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'paid':
                return <Badge variant="default">{t('billing.page.paid')}</Badge>;
            case 'open':
                return <Badge variant="secondary">{t('billing.page.open')}</Badge>;
            case 'failed':
                return <Badge variant="destructive">{t('billing.page.failed')}</Badge>;
            case 'refunded':
                return <Badge variant="outline">{t('billing.page.refunded')}</Badge>;
            case 'void':
                return <Badge variant="outline">{t('billing.page.void')}</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    const getPaymentTypeLabel = (type: string) => {
        switch (type) {
            case 'card':
                return t('customer.credit_card');
            case 'pix':
                return 'PIX';
            case 'boleto':
                return 'Boleto';
            default:
                return type;
        }
    };

    return (
        <CustomerLayout
            breadcrumbs={[
                { title: t('customer.dashboard.title'), href: '/account' },
                { title: t('customer.invoices.title'), href: '/account/invoices' },
                { title: invoice.number, href: `/account/invoices/${invoice.id}` },
            ]}
        >
            <Head title={`${t('customer.invoice.title')} ${invoice.number}`} />

            <div className="space-y-6 max-w-3xl">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                            <Receipt className="h-6 w-6" />
                            {t('customer.invoice.title')} {invoice.number}
                        </h1>
                        <p className="text-muted-foreground">
                            {new Date(invoice.date).toLocaleDateString()}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {getStatusBadge(invoice.status)}
                        <Button asChild variant="outline">
                            <a href={`/account/invoices/${invoice.id}/download`}>
                                <Download className="mr-2 h-4 w-4" />
                                {t('customer.invoice.download_pdf')}
                            </a>
                        </Button>
                    </div>
                </div>

                {invoice.failure_message && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{invoice.failure_message}</AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('customer.invoice.details')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p className="text-muted-foreground">{t('customer.invoice.number')}</p>
                                <p className="font-medium">{invoice.number}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground">{t('customer.invoice.date')}</p>
                                <p className="font-medium">{new Date(invoice.date).toLocaleDateString()}</p>
                            </div>
                            {invoice.paid_at && (
                                <div>
                                    <p className="text-muted-foreground">{t('customer.paid_at')}</p>
                                    <p className="font-medium">{new Date(invoice.paid_at).toLocaleDateString()}</p>
                                </div>
                            )}
                            {invoice.due_date && invoice.status === 'open' && (
                                <div>
                                    <p className="text-muted-foreground">{t('customer.invoice.due_date')}</p>
                                    <p className="font-medium">{new Date(invoice.due_date).toLocaleDateString()}</p>
                                </div>
                            )}
                            <div>
                                <p className="text-muted-foreground">{t('customer.payment_method')}</p>
                                <p className="font-medium flex items-center gap-1">
                                    {invoice.payment_method ? (
                                        <>
                                            <CreditCard className="h-4 w-4" />
                                            {invoice.payment_method.brand && (
                                                <span className="capitalize">{invoice.payment_method.brand}</span>
                                            )}
                                            {invoice.payment_method.last4 && (
                                                <span>•••• {invoice.payment_method.last4}</span>
                                            )}
                                        </>
                                    ) : (
                                        getPaymentTypeLabel(invoice.payment_type)
                                    )}
                                </p>
                            </div>
                            {invoice.tenant && (
                                <div>
                                    <p className="text-muted-foreground">{t('customer.workspace')}</p>
                                    <p className="font-medium flex items-center gap-1">
                                        <Building2 className="h-4 w-4" />
                                        {invoice.tenant.name}
                                    </p>
                                </div>
                            )}
                        </div>

                        <Separator />

                        <div>
                            <h3 className="font-medium mb-4">{t('customer.invoice.line_items')}</h3>
                            <div className="space-y-3">
                                {invoice.lines.map((line, index) => (
                                    <div key={index} className="flex justify-between items-center py-2 border-b last:border-0">
                                        <div>
                                            <p className="font-medium">{line.description}</p>
                                            {line.quantity > 1 && (
                                                <p className="text-sm text-muted-foreground">
                                                    {t('customer.invoice.quantity')}: {line.quantity}
                                                </p>
                                            )}
                                        </div>
                                        <p className="font-medium">{line.amount_formatted}</p>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <Separator />

                        <div className="space-y-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">{t('customer.invoice.subtotal')}</span>
                                <span>{invoice.amount_formatted}</span>
                            </div>
                            {invoice.fee > 0 && (
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">{t('customer.processing_fee')}</span>
                                    <span>- {invoice.fee_formatted}</span>
                                </div>
                            )}
                            {invoice.amount_refunded > 0 && (
                                <div className="flex justify-between text-sm text-destructive">
                                    <span>{t('customer.refunded')}</span>
                                    <span>- {invoice.amount_refunded_formatted}</span>
                                </div>
                            )}
                            <Separator />
                            <div className="flex justify-between font-medium text-lg">
                                <span>{t('customer.invoice.total')}</span>
                                <span>{invoice.amount_formatted}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </CustomerLayout>
    );
}
