import CustomerLayout from '@/layouts/customer-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Receipt, Download, Eye } from 'lucide-react';

interface Invoice {
    id: string;
    number: string;
    date: string;
    paid_at: string | null;
    amount: number;
    amount_formatted: string;
    currency: string;
    status: 'paid' | 'open' | 'failed' | 'refunded' | 'void';
    payment_type: 'card' | 'pix' | 'boleto';
    provider: string;
    description: string | null;
    payable_type: string | null;
    is_refundable: boolean;
    failure_message: string | null;
}

interface InvoicesIndexProps {
    invoices: Invoice[];
}

export default function InvoicesIndex({ invoices }: InvoicesIndexProps) {
    const { t } = useLaravelReactI18n();

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'paid':
                return <Badge variant="default">{t('billing.paid')}</Badge>;
            case 'open':
                return <Badge variant="secondary">{t('billing.open')}</Badge>;
            case 'failed':
                return <Badge variant="destructive">{t('billing.failed')}</Badge>;
            case 'refunded':
                return <Badge variant="outline">{t('billing.refunded')}</Badge>;
            case 'void':
                return <Badge variant="outline">{t('billing.void')}</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <CustomerLayout
            breadcrumbs={[
                { title: t('customer.dashboard'), href: '/account' },
                { title: t('customer.invoices'), href: '/account/invoices' },
            ]}
        >
            <Head title={t('customer.invoices')} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {t('customer.invoices')}
                    </h1>
                    <p className="text-muted-foreground">
                        {t('customer.invoices_description')}
                    </p>
                </div>

                {invoices.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <Receipt className="h-16 w-16 text-muted-foreground/50 mb-4" />
                            <h3 className="text-lg font-medium mb-2">
                                {t('customer.no_invoices')}
                            </h3>
                            <p className="text-muted-foreground text-center">
                                {t('customer.no_invoices_description')}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('customer.invoice_number')}</TableHead>
                                    <TableHead>{t('customer.date')}</TableHead>
                                    <TableHead>{t('customer.description')}</TableHead>
                                    <TableHead>{t('customer.amount')}</TableHead>
                                    <TableHead>{t('customer.status')}</TableHead>
                                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoices.map((invoice) => (
                                    <TableRow key={invoice.id}>
                                        <TableCell className="font-medium">
                                            {invoice.number}
                                        </TableCell>
                                        <TableCell>
                                            {new Date(invoice.date).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell className="max-w-xs truncate">
                                            {invoice.description || '-'}
                                        </TableCell>
                                        <TableCell>{invoice.amount_formatted}</TableCell>
                                        <TableCell>{getStatusBadge(invoice.status)}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button asChild variant="ghost" size="sm">
                                                    <Link href={`/account/invoices/${invoice.id}`}>
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button asChild variant="ghost" size="sm">
                                                    <a href={`/account/invoices/${invoice.id}/download`}>
                                                        <Download className="h-4 w-4" />
                                                    </a>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>
                )}
            </div>
        </CustomerLayout>
    );
}
