import CustomerLayout from '@/layouts/customer-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Download, Receipt } from 'lucide-react';

interface InvoiceLine {
    description: string;
    amount: number;
    quantity: number | null;
}

interface Invoice {
    id: string;
    number: string | null;
    date: string;
    due_date: string | null;
    total: string;
    subtotal: string;
    tax: string;
    status: string;
    description: string | null;
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
                return <Badge variant="default">{t('billing.paid')}</Badge>;
            case 'open':
                return <Badge variant="secondary">{t('billing.open')}</Badge>;
            case 'draft':
                return <Badge variant="outline">{t('billing.draft')}</Badge>;
            case 'uncollectible':
                return <Badge variant="destructive">{t('billing.uncollectible')}</Badge>;
            case 'void':
                return <Badge variant="outline">{t('billing.void')}</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    const formatAmount = (amount: number) => {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        }).format(amount / 100);
    };

    return (
        <CustomerLayout
            breadcrumbs={[
                { title: t('customer.dashboard'), href: '/account' },
                { title: t('customer.invoices'), href: '/account/invoices' },
                { title: invoice.number || invoice.id, href: `/account/invoices/${invoice.id}` },
            ]}
        >
            <Head title={`${t('customer.invoice')} ${invoice.number || invoice.id}`} />

            <div className="space-y-6 max-w-3xl">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                            <Receipt className="h-6 w-6" />
                            {t('customer.invoice')} {invoice.number || invoice.id.substring(0, 8)}
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
                                {t('customer.download_pdf')}
                            </a>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('customer.invoice_details')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p className="text-muted-foreground">{t('customer.invoice_number')}</p>
                                <p className="font-medium">{invoice.number || invoice.id}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground">{t('customer.date')}</p>
                                <p className="font-medium">{new Date(invoice.date).toLocaleDateString()}</p>
                            </div>
                            {invoice.due_date && (
                                <div>
                                    <p className="text-muted-foreground">{t('customer.due_date')}</p>
                                    <p className="font-medium">{new Date(invoice.due_date).toLocaleDateString()}</p>
                                </div>
                            )}
                            <div>
                                <p className="text-muted-foreground">{t('customer.status')}</p>
                                <div className="mt-1">{getStatusBadge(invoice.status)}</div>
                            </div>
                        </div>

                        <Separator />

                        <div>
                            <h3 className="font-medium mb-4">{t('customer.line_items')}</h3>
                            <div className="space-y-3">
                                {invoice.lines.map((line, index) => (
                                    <div key={index} className="flex justify-between items-center py-2 border-b last:border-0">
                                        <div>
                                            <p className="font-medium">{line.description}</p>
                                            {line.quantity && (
                                                <p className="text-sm text-muted-foreground">
                                                    {t('customer.quantity')}: {line.quantity}
                                                </p>
                                            )}
                                        </div>
                                        <p className="font-medium">{formatAmount(line.amount)}</p>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <Separator />

                        <div className="space-y-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">{t('customer.subtotal')}</span>
                                <span>{invoice.subtotal}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">{t('customer.tax')}</span>
                                <span>{invoice.tax}</span>
                            </div>
                            <Separator />
                            <div className="flex justify-between font-medium text-lg">
                                <span>{t('customer.total')}</span>
                                <span>{invoice.total}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </CustomerLayout>
    );
}
