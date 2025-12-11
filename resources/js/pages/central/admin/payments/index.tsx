import { useState, type ReactElement } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CreditCard,
    Download,
    RefreshCw,
    Search,
    Eye,
    CheckCircle,
    Clock,
    XCircle,
    RotateCcw,
} from 'lucide-react';

import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    type BreadcrumbItem,
    type PaymentAdminResource,
    type InertiaPaginatedResponse,
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

interface PaymentFilters {
    status: string | null;
    provider: string | null;
    payment_method: string | null;
    from: string | null;
    to: string | null;
    search: string | null;
}

interface PaymentStats {
    total_amount: number;
    pending_count: number;
    completed_count: number;
    failed_count: number;
    refunded_amount: number;
}

interface Props {
    payments: InertiaPaginatedResponse<PaymentAdminResource>;
    filters: PaymentFilters;
    stats: PaymentStats;
}

const EMPTY_FILTERS: PaymentFilters = {
    status: null,
    provider: null,
    payment_method: null,
    from: null,
    to: null,
    search: null,
};

const STATUS_OPTIONS = [
    { value: 'pending', label: 'Pending' },
    { value: 'processing', label: 'Processing' },
    { value: 'succeeded', label: 'Succeeded' },
    { value: 'failed', label: 'Failed' },
    { value: 'canceled', label: 'Canceled' },
    { value: 'refunded', label: 'Refunded' },
    { value: 'partially_refunded', label: 'Partially Refunded' },
];

const PROVIDER_OPTIONS = [
    { value: 'stripe', label: 'Stripe' },
    { value: 'asaas', label: 'Asaas' },
];

const PAYMENT_METHOD_OPTIONS = [
    { value: 'card', label: 'Card' },
    { value: 'pix', label: 'PIX' },
    { value: 'boleto', label: 'Boleto' },
];

function formatMoney(amount: number): string {
    return 'R$' + (amount / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
}

function getStatusIcon(status: string) {
    switch (status) {
        case 'succeeded':
            return <CheckCircle className="h-4 w-4 text-green-500" />;
        case 'pending':
        case 'processing':
            return <Clock className="h-4 w-4 text-yellow-500" />;
        case 'failed':
        case 'canceled':
            return <XCircle className="h-4 w-4 text-red-500" />;
        case 'refunded':
        case 'partially_refunded':
            return <RotateCcw className="h-4 w-4 text-gray-500" />;
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

function PaymentsIndex({ payments, filters, stats }: Props) {
    const { t } = useLaravelReactI18n();
    const [localFilters, setLocalFilters] = useState<PaymentFilters>(filters);
    const [isExporting, setIsExporting] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('payments.page.title'), href: admin.payments.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const applyFilters = () => {
        const queryParams = Object.fromEntries(
            Object.entries(localFilters).filter(([, v]) => v !== null && v !== '')
        ) as Record<string, string>;
        router.get('/admin/payments', queryParams, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        setLocalFilters(EMPTY_FILTERS);
        router.get('/admin/payments', {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleExport = () => {
        setIsExporting(true);
        const queryParams = Object.fromEntries(
            Object.entries(localFilters).filter(([, v]) => v !== null && v !== '')
        );
        const params = new URLSearchParams(queryParams as Record<string, string>).toString();
        window.location.href = `/admin/payments/export${params ? '?' + params : ''}`;
        setTimeout(() => setIsExporting(false), 2000);
    };

    const hasActiveFilters = Object.values(localFilters).some(v => v !== null && v !== '');

    return (
        <>
            <Head title={t('payments.page.page_title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={CreditCard}>{t('payments.page.page_title')}</PageTitle>
                        <PageDescription>
                            {t('payments.page.description')}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button
                            variant="outline"
                            onClick={handleExport}
                            disabled={isExporting}
                        >
                            <Download className="mr-2 h-4 w-4" />
                            {isExporting ? t('common.loading') : t('payments.page.export_csv')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => router.reload({ only: ['payments', 'stats'] })}
                        >
                            <RefreshCw className="mr-2 h-4 w-4" />
                            {t('common.refresh')}
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    {/* Stats Cards */}
                    <div className="grid gap-4 md:grid-cols-4 mb-6">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('payments.stats.total')}
                                </CardTitle>
                                <CreditCard className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{formatMoney(stats.total_amount)}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('payments.stats.completed')}
                                </CardTitle>
                                <CheckCircle className="h-4 w-4 text-green-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.completed_count}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('payments.stats.pending')}
                                </CardTitle>
                                <Clock className="h-4 w-4 text-yellow-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.pending_count}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {t('payments.stats.failed')}
                                </CardTitle>
                                <XCircle className="h-4 w-4 text-red-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.failed_count}</div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Filters */}
                    <Card className="mb-6">
                        <CardContent className="pt-6">
                            <div className="flex flex-wrap gap-4">
                                <div className="flex-1 min-w-[200px]">
                                    <div className="relative">
                                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            placeholder={t('payments.page.search_placeholder')}
                                            value={localFilters.search ?? ''}
                                            onChange={(e) => setLocalFilters({ ...localFilters, search: e.target.value || null })}
                                            className="pl-10"
                                        />
                                    </div>
                                </div>
                                <Select
                                    value={localFilters.status ?? ''}
                                    onValueChange={(value) => setLocalFilters({ ...localFilters, status: value || null })}
                                >
                                    <SelectTrigger className="w-[160px]">
                                        <SelectValue placeholder={t('payments.page.filter_status')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {STATUS_OPTIONS.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={localFilters.provider ?? ''}
                                    onValueChange={(value) => setLocalFilters({ ...localFilters, provider: value || null })}
                                >
                                    <SelectTrigger className="w-[140px]">
                                        <SelectValue placeholder={t('payments.page.filter_provider')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {PROVIDER_OPTIONS.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={localFilters.payment_method ?? ''}
                                    onValueChange={(value) => setLocalFilters({ ...localFilters, payment_method: value || null })}
                                >
                                    <SelectTrigger className="w-[160px]">
                                        <SelectValue placeholder={t('payments.page.filter_payment_method')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {PAYMENT_METHOD_OPTIONS.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Input
                                    type="date"
                                    placeholder={t('payments.page.filter_date_from')}
                                    value={localFilters.from ?? ''}
                                    onChange={(e) => setLocalFilters({ ...localFilters, from: e.target.value || null })}
                                    className="w-[150px]"
                                />
                                <Input
                                    type="date"
                                    placeholder={t('payments.page.filter_date_to')}
                                    value={localFilters.to ?? ''}
                                    onChange={(e) => setLocalFilters({ ...localFilters, to: e.target.value || null })}
                                    className="w-[150px]"
                                />
                                <Button onClick={applyFilters}>
                                    {t('common.apply')}
                                </Button>
                                {hasActiveFilters && (
                                    <Button variant="outline" onClick={clearFilters}>
                                        {t('common.clear')}
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Payments Table */}
                    <Card>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('payments.page.date')}</TableHead>
                                    <TableHead>{t('payments.page.tenant')}</TableHead>
                                    <TableHead>{t('payments.page.customer')}</TableHead>
                                    <TableHead>{t('payments.page.amount')}</TableHead>
                                    <TableHead>{t('payments.page.status')}</TableHead>
                                    <TableHead>{t('payments.page.payment_method')}</TableHead>
                                    <TableHead>{t('payments.page.provider')}</TableHead>
                                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payments.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={8} className="text-center py-8 text-muted-foreground">
                                            {t('payments.page.no_payments')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    payments.data.map((payment) => (
                                        <TableRow key={payment.id}>
                                            <TableCell className="font-mono text-sm">
                                                {new Date(payment.created_at).toLocaleDateString('pt-BR')}
                                            </TableCell>
                                            <TableCell>
                                                {payment.tenant?.name ?? payment.tenant?.id ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    <div className="font-medium">{payment.customer?.name ?? '-'}</div>
                                                    <div className="text-sm text-muted-foreground">{payment.customer?.email}</div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="font-mono">
                                                {payment.formatted_amount}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {getStatusIcon(payment.status)}
                                                    <Badge variant={getStatusVariant(payment.status)}>
                                                        {payment.status_label}
                                                    </Badge>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {payment.payment_method_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">
                                                    {payment.provider}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link href={admin.payments.show.url({ payment: payment.id })}>
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </Card>

                    {/* Pagination */}
                    {payments.data.length > 0 && payments.links && (
                        <div className="mt-6 flex items-center justify-between">
                            <p className="text-sm text-muted-foreground">
                                Showing {payments.from} to {payments.to} of {payments.total} results
                            </p>
                            <div className="flex gap-2">
                                {payments.links.map((link, index) => (
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
                </PageContent>
            </Page>
        </>
    );
}

PaymentsIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default PaymentsIndex;
