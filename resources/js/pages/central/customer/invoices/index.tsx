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
import customer from '@/routes/central/account';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Receipt, Download, Eye } from 'lucide-react';
import type { InertiaPaginatedResponse, PaymentResource } from '@/types';
import { type ReactElement } from 'react';

interface InvoicesIndexProps {
    invoices: InertiaPaginatedResponse<PaymentResource>;
}

function InvoicesIndex({ invoices }: InvoicesIndexProps) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('customer.dashboard.title'), href: customer.dashboard.url() },
        { title: t('customer.invoices.title'), href: customer.invoices.index.url() },
    ];
    useSetBreadcrumbs(breadcrumbs);

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

    return (
        <>
            <Head title={t('customer.invoices.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Receipt}>
                            {t('customer.invoices.title')}
                        </PageTitle>
                        <PageDescription>
                            {t('customer.invoices.description')}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    {invoices.data.length === 0 ? (
                        <Card>
                            <CardContent className="flex flex-col items-center justify-center py-16">
                                <Receipt className="h-16 w-16 text-muted-foreground/50 mb-4" />
                                <h3 className="text-lg font-medium mb-2">
                                    {t('customer.invoice.no_invoices')}
                                </h3>
                                <p className="text-muted-foreground text-center">
                                    {t('customer.invoice.no_invoices_description')}
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <>
                            <Card>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('customer.invoice.number')}</TableHead>
                                            <TableHead>{t('customer.invoice.date')}</TableHead>
                                            <TableHead>{t('customer.invoice.description')}</TableHead>
                                            <TableHead>{t('customer.invoice.amount')}</TableHead>
                                            <TableHead>{t('customer.status')}</TableHead>
                                            <TableHead className="text-right">{t('common.actions')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {invoices.data.map((invoice) => (
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
                                                            <Link href={customer.invoices.show.url(invoice.id)}>
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

                            {invoices.links && invoices.links.length > 0 && (
                                <div className="mt-6 flex items-center justify-between">
                                    <p className="text-sm text-muted-foreground">
                                        {t('customer.showing_results', {
                                            from: invoices.from ?? 0,
                                            to: invoices.to ?? 0,
                                            total: invoices.total,
                                        })}
                                    </p>
                                    <div className="flex gap-2">
                                        {invoices.links.map((link, index) => (
                                            <Button
                                                key={index}
                                                variant={link.active ? 'default' : 'outline'}
                                                size="sm"
                                                disabled={!link.url}
                                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </PageContent>
            </Page>
        </>
    );
}

InvoicesIndex.layout = (page: ReactElement) => <CustomerLayout>{page}</CustomerLayout>;

export default InvoicesIndex;
