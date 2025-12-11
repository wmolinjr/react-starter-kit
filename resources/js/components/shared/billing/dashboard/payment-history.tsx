import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CheckCircle2,
    Clock,
    AlertCircle,
    XCircle,
    RotateCcw,
    CreditCard,
    QrCode,
    Receipt,
    Download,
    ChevronRight,
    ExternalLink,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
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
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { PaymentResource, InvoiceDetailResource } from '@/types';

type PaymentStatus = 'paid' | 'open' | 'failed' | 'refunded' | 'void';
type PaymentType = 'card' | 'pix' | 'boleto';

interface PaymentHistoryProps {
    /** Payments to display - supports both PaymentResource and InvoiceDetailResource */
    payments: (PaymentResource | InvoiceDetailResource)[];
    /** Title for the section */
    title?: string;
    /** Description for the section */
    description?: string;
    /** Display variant */
    variant?: 'card' | 'table' | 'timeline';
    /** Show download button for invoices */
    showDownload?: boolean;
    /** Maximum items to show (use -1 for unlimited) */
    maxItems?: number;
    /** Callback when "View All" is clicked */
    onViewAll?: () => void;
    /** Callback when a payment is clicked */
    onPaymentClick?: (paymentId: string) => void;
    /** Currency code */
    currency?: string;
    /** Whether to show empty state */
    showEmptyState?: boolean;
    /** Additional className */
    className?: string;
}

// Status configuration
const statusConfig: Record<
    PaymentStatus,
    { icon: typeof CheckCircle2; color: string; bgColor: string }
> = {
    paid: {
        icon: CheckCircle2,
        color: 'text-green-600 dark:text-green-400',
        bgColor: 'bg-green-100 dark:bg-green-900/30',
    },
    open: {
        icon: Clock,
        color: 'text-yellow-600 dark:text-yellow-400',
        bgColor: 'bg-yellow-100 dark:bg-yellow-900/30',
    },
    failed: {
        icon: AlertCircle,
        color: 'text-red-600 dark:text-red-400',
        bgColor: 'bg-red-100 dark:bg-red-900/30',
    },
    refunded: {
        icon: RotateCcw,
        color: 'text-blue-600 dark:text-blue-400',
        bgColor: 'bg-blue-100 dark:bg-blue-900/30',
    },
    void: {
        icon: XCircle,
        color: 'text-gray-600 dark:text-gray-400',
        bgColor: 'bg-gray-100 dark:bg-gray-800/30',
    },
};

// Payment method icons
const paymentMethodIcons: Record<PaymentType, typeof CreditCard> = {
    card: CreditCard,
    pix: QrCode,
    boleto: Receipt,
};

/**
 * PaymentHistory - Display payment/invoice history
 *
 * Supports multiple display variants (card, table, timeline) and
 * both PaymentResource and InvoiceDetailResource data types.
 *
 * @example
 * <PaymentHistory
 *     payments={payments}
 *     variant="card"
 *     maxItems={5}
 *     onViewAll={() => navigate('/billing/invoices')}
 * />
 */
export function PaymentHistory({
    payments,
    title,
    description,
    variant = 'card',
    showDownload = true,
    maxItems = 5,
    onViewAll,
    onPaymentClick,
    currency = 'BRL',
    showEmptyState = true,
    className,
}: PaymentHistoryProps) {
    const { t } = useLaravelReactI18n();

    // Limit items if needed
    const displayPayments =
        maxItems > 0 ? payments.slice(0, maxItems) : payments;
    const hasMore = maxItems > 0 && payments.length > maxItems;

    // Type guard to check if it's a PaymentResource
    const isPaymentResource = (
        payment: PaymentResource | InvoiceDetailResource,
    ): payment is PaymentResource => {
        return 'payment_type' in payment;
    };

    // Get status from payment (normalize between types)
    const getStatus = (
        payment: PaymentResource | InvoiceDetailResource,
    ): PaymentStatus => {
        if (isPaymentResource(payment)) {
            return payment.status as PaymentStatus;
        }
        // InvoiceDetailResource
        return payment.paid ? 'paid' : 'open';
    };

    // Get amount formatted
    const getAmount = (
        payment: PaymentResource | InvoiceDetailResource,
    ): string => {
        if (isPaymentResource(payment)) {
            return payment.amount_formatted;
        }
        return payment.total;
    };

    // Get date formatted
    const getDate = (
        payment: PaymentResource | InvoiceDetailResource,
    ): string => {
        if (isPaymentResource(payment)) {
            return payment.date;
        }
        return payment.date_formatted || payment.date;
    };

    // Get invoice number
    const getNumber = (
        payment: PaymentResource | InvoiceDetailResource,
    ): string => {
        if (isPaymentResource(payment)) {
            return payment.number;
        }
        return payment.number || payment.id;
    };

    // Get payment method
    const getPaymentMethod = (
        payment: PaymentResource | InvoiceDetailResource,
    ): PaymentType | null => {
        if (isPaymentResource(payment)) {
            return payment.payment_type as PaymentType;
        }
        return null;
    };

    // Get download URL
    const getDownloadUrl = (
        payment: PaymentResource | InvoiceDetailResource,
    ): string | null => {
        if (!isPaymentResource(payment)) {
            return payment.download_url;
        }
        return null;
    };

    // Format date for display
    const formatDate = (dateStr: string): string => {
        const date = new Date(dateStr);
        return new Intl.DateTimeFormat(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        }).format(date);
    };

    // Status badge component
    const StatusBadge = ({ status }: { status: PaymentStatus }) => {
        const config = statusConfig[status] || statusConfig.void;
        const StatusIcon = config.icon;

        const statusLabel = {
            paid: t('billing.status.paid', { default: 'Paid' }),
            open: t('billing.status.open', { default: 'Open' }),
            failed: t('billing.status.failed', { default: 'Failed' }),
            refunded: t('billing.status.refunded', { default: 'Refunded' }),
            void: t('billing.status.void', { default: 'Void' }),
        }[status];

        return (
            <Badge
                variant="secondary"
                className={cn(
                    'gap-1 font-medium',
                    config.bgColor,
                    config.color,
                )}
            >
                <StatusIcon className="h-3 w-3" />
                {statusLabel}
            </Badge>
        );
    };

    // Payment method badge
    const PaymentMethodBadge = ({ method }: { method: PaymentType | null }) => {
        if (!method) return null;
        const MethodIcon = paymentMethodIcons[method] || CreditCard;

        return (
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Badge variant="outline" className="gap-1">
                            <MethodIcon className="h-3 w-3" />
                            {method.toUpperCase()}
                        </Badge>
                    </TooltipTrigger>
                    <TooltipContent>
                        {t(`billing.payment_method.${method}`, {
                            default: method,
                        })}
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        );
    };

    // Empty state
    if (payments.length === 0 && showEmptyState) {
        return (
            <Card className={className}>
                {(title || description) && (
                    <CardHeader>
                        {title && <CardTitle>{title}</CardTitle>}
                        {description && (
                            <CardDescription>{description}</CardDescription>
                        )}
                    </CardHeader>
                )}
                <CardContent>
                    <div className="flex flex-col items-center justify-center py-8 text-center">
                        <div className="rounded-full bg-muted p-4">
                            <Receipt className="h-8 w-8 text-muted-foreground" />
                        </div>
                        <p className="mt-4 font-medium">
                            {t('billing.no_payments', {
                                default: 'No payment history',
                            })}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('billing.no_payments_description', {
                                default:
                                    'Your payment history will appear here once you make a purchase.',
                            })}
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    // Table variant
    if (variant === 'table') {
        return (
            <Card className={className}>
                {(title || description) && (
                    <CardHeader>
                        {title && <CardTitle>{title}</CardTitle>}
                        {description && (
                            <CardDescription>{description}</CardDescription>
                        )}
                    </CardHeader>
                )}
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t('billing.invoice', {
                                        default: 'Invoice',
                                    })}
                                </TableHead>
                                <TableHead>
                                    {t('billing.date', { default: 'Date' })}
                                </TableHead>
                                <TableHead>
                                    {t('billing.amount', { default: 'Amount' })}
                                </TableHead>
                                <TableHead>
                                    {t('billing.status', { default: 'Status' })}
                                </TableHead>
                                {showDownload && (
                                    <TableHead className="text-right">
                                        {t('billing.actions', {
                                            default: 'Actions',
                                        })}
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {displayPayments.map((payment) => {
                                const status = getStatus(payment);
                                const downloadUrl = getDownloadUrl(payment);
                                const paymentMethod = getPaymentMethod(payment);

                                return (
                                    <TableRow
                                        key={payment.id}
                                        className={cn(
                                            onPaymentClick &&
                                                'cursor-pointer hover:bg-muted/50',
                                        )}
                                        onClick={() =>
                                            onPaymentClick?.(payment.id)
                                        }
                                    >
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2">
                                                {getNumber(payment)}
                                                {paymentMethod && (
                                                    <PaymentMethodBadge
                                                        method={paymentMethod}
                                                    />
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(getDate(payment))}
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            {getAmount(payment)}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={status} />
                                        </TableCell>
                                        {showDownload && (
                                            <TableCell className="text-right">
                                                {downloadUrl && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                        onClick={(e) =>
                                                            e.stopPropagation()
                                                        }
                                                    >
                                                        <a
                                                            href={downloadUrl}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            <Download className="h-4 w-4" />
                                                        </a>
                                                    </Button>
                                                )}
                                            </TableCell>
                                        )}
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                    {hasMore && onViewAll && (
                        <div className="border-t p-4">
                            <Button
                                variant="ghost"
                                className="w-full"
                                onClick={onViewAll}
                            >
                                {t('billing.view_all_payments', {
                                    default: 'View all payments',
                                })}
                                <ChevronRight className="ml-2 h-4 w-4" />
                            </Button>
                        </div>
                    )}
                </CardContent>
            </Card>
        );
    }

    // Timeline variant
    if (variant === 'timeline') {
        return (
            <Card className={className}>
                {(title || description) && (
                    <CardHeader>
                        {title && <CardTitle>{title}</CardTitle>}
                        {description && (
                            <CardDescription>{description}</CardDescription>
                        )}
                    </CardHeader>
                )}
                <CardContent>
                    <ScrollArea className="h-[400px] pr-4">
                        <div className="relative space-y-0">
                            {/* Timeline line */}
                            <div className="absolute left-[19px] top-0 h-full w-px bg-border" />

                            {displayPayments.map((payment, index) => {
                                const status = getStatus(payment);
                                const config =
                                    statusConfig[status] || statusConfig.void;
                                const StatusIcon = config.icon;
                                const downloadUrl = getDownloadUrl(payment);
                                const paymentMethod = getPaymentMethod(payment);

                                return (
                                    <div
                                        key={payment.id}
                                        className={cn(
                                            'relative flex gap-4 pb-8',
                                            index ===
                                                displayPayments.length - 1 &&
                                                'pb-0',
                                        )}
                                    >
                                        {/* Timeline dot */}
                                        <div
                                            className={cn(
                                                'relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                                                config.bgColor,
                                            )}
                                        >
                                            <StatusIcon
                                                className={cn(
                                                    'h-5 w-5',
                                                    config.color,
                                                )}
                                            />
                                        </div>

                                        {/* Content */}
                                        <div
                                            className={cn(
                                                'flex-1 rounded-lg border bg-card p-4',
                                                onPaymentClick &&
                                                    'cursor-pointer transition-colors hover:bg-muted/50',
                                            )}
                                            onClick={() =>
                                                onPaymentClick?.(payment.id)
                                            }
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <p className="font-medium">
                                                        {getNumber(payment)}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {formatDate(
                                                            getDate(payment),
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="font-semibold">
                                                        {getAmount(payment)}
                                                    </p>
                                                    <div className="mt-1 flex items-center gap-2">
                                                        {paymentMethod && (
                                                            <PaymentMethodBadge
                                                                method={
                                                                    paymentMethod
                                                                }
                                                            />
                                                        )}
                                                        <StatusBadge
                                                            status={status}
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            {downloadUrl && showDownload && (
                                                <div className="mt-3 pt-3 border-t">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                        onClick={(e) =>
                                                            e.stopPropagation()
                                                        }
                                                    >
                                                        <a
                                                            href={downloadUrl}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            <Download className="mr-2 h-4 w-4" />
                                                            {t(
                                                                'billing.download_invoice',
                                                                {
                                                                    default:
                                                                        'Download Invoice',
                                                                },
                                                            )}
                                                        </a>
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </ScrollArea>
                    {hasMore && onViewAll && (
                        <div className="mt-4 pt-4 border-t">
                            <Button
                                variant="ghost"
                                className="w-full"
                                onClick={onViewAll}
                            >
                                {t('billing.view_all_payments', {
                                    default: 'View all payments',
                                })}
                                <ChevronRight className="ml-2 h-4 w-4" />
                            </Button>
                        </div>
                    )}
                </CardContent>
            </Card>
        );
    }

    // Card variant (default) - Compact list
    return (
        <Card className={className}>
            {(title || description) && (
                <CardHeader>
                    {title && <CardTitle>{title}</CardTitle>}
                    {description && (
                        <CardDescription>{description}</CardDescription>
                    )}
                </CardHeader>
            )}
            <CardContent className="p-0">
                <div className="divide-y">
                    {displayPayments.map((payment) => {
                        const status = getStatus(payment);
                        const config =
                            statusConfig[status] || statusConfig.void;
                        const StatusIcon = config.icon;
                        const downloadUrl = getDownloadUrl(payment);
                        const paymentMethod = getPaymentMethod(payment);

                        return (
                            <div
                                key={payment.id}
                                className={cn(
                                    'flex items-center justify-between gap-4 p-4',
                                    onPaymentClick &&
                                        'cursor-pointer transition-colors hover:bg-muted/50',
                                )}
                                onClick={() => onPaymentClick?.(payment.id)}
                            >
                                <div className="flex items-center gap-3">
                                    <div
                                        className={cn(
                                            'flex h-10 w-10 items-center justify-center rounded-full',
                                            config.bgColor,
                                        )}
                                    >
                                        <StatusIcon
                                            className={cn(
                                                'h-5 w-5',
                                                config.color,
                                            )}
                                        />
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <p className="font-medium">
                                                {getNumber(payment)}
                                            </p>
                                            {paymentMethod && (
                                                <PaymentMethodBadge
                                                    method={paymentMethod}
                                                />
                                            )}
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {formatDate(getDate(payment))}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-center gap-3">
                                    <div className="text-right">
                                        <p className="font-semibold">
                                            {getAmount(payment)}
                                        </p>
                                        <StatusBadge status={status} />
                                    </div>
                                    {downloadUrl && showDownload && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            asChild
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <a
                                                href={downloadUrl}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <ExternalLink className="h-4 w-4" />
                                            </a>
                                        </Button>
                                    )}
                                    {onPaymentClick && (
                                        <ChevronRight className="h-5 w-5 text-muted-foreground" />
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
                {hasMore && onViewAll && (
                    <div className="border-t p-4">
                        <Button
                            variant="ghost"
                            className="w-full"
                            onClick={onViewAll}
                        >
                            {t('billing.view_all_payments', {
                                default: 'View all payments',
                            })}
                            <ChevronRight className="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default PaymentHistory;
