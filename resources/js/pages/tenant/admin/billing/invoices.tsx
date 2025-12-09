import { Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { format } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import { FileText, Download, CheckCircle, Clock, AlertCircle } from 'lucide-react';

import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { type BreadcrumbItem, type InvoiceDetailResource, type TenantSummaryResource } from '@/types';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { useState } from 'react';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Props {
    invoices: InvoiceDetailResource[];
    tenant: TenantSummaryResource;
}

function InvoicesIndex({ invoices, tenant: tenantData }: Props) {
    const { t, currentLocale } = useLaravelReactI18n();
    const [selectedInvoice, setSelectedInvoice] = useState<InvoiceDetailResource | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('tenant.billing.title'), href: admin.billing.index.url() },
        { title: t('tenant.invoices.title'), href: admin.billing.invoices.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        const locale = currentLocale() === 'pt_BR' ? ptBR : undefined;
        return format(date, 'dd/MM/yyyy', { locale });
    };

    const getStatusBadge = (status: string, paid: boolean) => {
        if (paid) {
            return (
                <Badge variant="default" className="bg-green-500">
                    <CheckCircle className="mr-1 h-3 w-3" />
                    {t('tenant.invoices.status_paid')}
                </Badge>
            );
        }

        switch (status) {
            case 'open':
                return (
                    <Badge variant="secondary">
                        <Clock className="mr-1 h-3 w-3" />
                        {t('tenant.invoices.status_open')}
                    </Badge>
                );
            case 'void':
                return (
                    <Badge variant="outline">
                        {t('tenant.invoices.status_void')}
                    </Badge>
                );
            case 'uncollectible':
                return (
                    <Badge variant="destructive">
                        <AlertCircle className="mr-1 h-3 w-3" />
                        {t('tenant.invoices.status_uncollectible')}
                    </Badge>
                );
            default:
                return (
                    <Badge variant="outline">
                        {status}
                    </Badge>
                );
        }
    };

    return (
        <>
            <Head title={t('tenant.invoices.page_title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={FileText}>{t('tenant.invoices.page_title')}</PageTitle>
                        <PageDescription>
                            {t('tenant.invoices.description', { name: tenantData.name })}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('tenant.invoices.all_invoices')}</CardTitle>
                            <CardDescription>
                                {t('tenant.invoices.all_invoices_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {invoices.length === 0 ? (
                                <div className="py-8 text-center text-muted-foreground">
                                    {t('tenant.invoices.no_invoices')}
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('tenant.invoices.column_number')}</TableHead>
                                            <TableHead>{t('tenant.invoices.column_date')}</TableHead>
                                            <TableHead>{t('tenant.invoices.column_amount')}</TableHead>
                                            <TableHead>{t('tenant.invoices.column_status')}</TableHead>
                                            <TableHead className="text-right">{t('common.actions')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {invoices.map((invoice) => (
                                            <TableRow key={invoice.id}>
                                                <TableCell className="font-medium">
                                                    {invoice.number || invoice.id.slice(0, 8)}
                                                </TableCell>
                                                <TableCell>
                                                    {formatDate(invoice.date)}
                                                </TableCell>
                                                <TableCell className="font-semibold">
                                                    {invoice.total}
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(invoice.status, invoice.paid)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => setSelectedInvoice(invoice)}
                                                        >
                                                            {t('common.view')}
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <a href={invoice.download_url}>
                                                                <Download className="mr-2 h-4 w-4" />
                                                                {t('tenant.billing.download')}
                                                            </a>
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </PageContent>
            </Page>

            {/* Invoice Details Dialog */}
            <Dialog open={!!selectedInvoice} onOpenChange={() => setSelectedInvoice(null)}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {t('tenant.invoices.invoice_details')} {selectedInvoice?.number || selectedInvoice?.id.slice(0, 8)}
                        </DialogTitle>
                        <DialogDescription>
                            {selectedInvoice && formatDate(selectedInvoice.date)}
                        </DialogDescription>
                    </DialogHeader>
                    {selectedInvoice && (
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">{t('tenant.invoices.column_status')}</p>
                                    <div className="mt-1">
                                        {getStatusBadge(selectedInvoice.status, selectedInvoice.paid)}
                                    </div>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm text-muted-foreground">{t('tenant.invoices.column_amount')}</p>
                                    <p className="text-2xl font-bold">{selectedInvoice.total}</p>
                                </div>
                            </div>

                            {selectedInvoice.lines.length > 0 && (
                                <div>
                                    <h4 className="mb-2 font-medium">{t('tenant.invoices.line_items')}</h4>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>{t('common.description')}</TableHead>
                                                <TableHead className="text-center">{t('tenant.invoices.quantity')}</TableHead>
                                                <TableHead className="text-right">{t('tenant.invoices.column_amount')}</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {selectedInvoice.lines.map((line, index) => (
                                                <TableRow key={index}>
                                                    <TableCell>{line.description}</TableCell>
                                                    <TableCell className="text-center">{line.quantity}</TableCell>
                                                    <TableCell className="text-right">{line.amount}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}

                            <div className="flex justify-end">
                                <Button asChild>
                                    <a href={selectedInvoice.download_url}>
                                        <Download className="mr-2 h-4 w-4" />
                                        {t('tenant.invoices.download_pdf')}
                                    </a>
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

InvoicesIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default InvoicesIndex;
