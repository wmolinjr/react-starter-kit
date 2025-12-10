import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CreditCard, Tag, ArrowRight, RefreshCw, Calendar } from 'lucide-react';
import { Separator } from '@/components/ui/separator';
import type { CheckoutItem, PlanChangeInfo } from '@/types/billing';
import type { BillingPeriod } from '@/types/enums';

export interface CheckoutSummaryProps {
    /** Items in the checkout */
    items: CheckoutItem[];
    /** Current billing period */
    billingPeriod: BillingPeriod;
    /** Currency code */
    currency?: string;
    /** Subtotal (sum of all items) */
    subtotal: number;
    /** Discount amount (if any) */
    discount?: number;
    /** Final total */
    total: number;
    /** Formatted prices */
    formattedSubtotal?: string;
    formattedDiscount?: string;
    formattedTotal?: string;
    /** Plan change information (for upgrades/downgrades) */
    planChange?: PlanChangeInfo;
    /** Show individual items */
    showItems?: boolean;
    /** Additional className */
    className?: string;
}

/**
 * CheckoutSummary - Displays order summary with totals
 *
 * @example
 * <CheckoutSummary
 *     items={items}
 *     billingPeriod="monthly"
 *     subtotal={4900}
 *     discount={500}
 *     total={4400}
 * />
 *
 * @example
 * // With plan change
 * <CheckoutSummary
 *     items={items}
 *     billingPeriod="monthly"
 *     subtotal={4900}
 *     total={4900}
 *     planChange={{
 *         from: currentPlan,
 *         to: newPlan,
 *         prorationAmount: -1200,
 *         formattedProration: "-$12.00",
 *         effectiveDate: "2024-02-01"
 *     }}
 * />
 */
export function CheckoutSummary({
    items,
    billingPeriod,
    currency = 'USD',
    subtotal,
    discount = 0,
    total,
    formattedSubtotal,
    formattedDiscount,
    formattedTotal,
    planChange,
    showItems = false,
    className,
}: CheckoutSummaryProps) {
    const { t } = useLaravelReactI18n();

    // Format price
    const formatPrice = (amount: number): string => {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
            minimumFractionDigits: amount % 100 === 0 ? 0 : 2,
            maximumFractionDigits: 2,
        }).format(amount / 100);
    };

    // Helper to get item price based on current billing period
    const getItemPrice = (item: CheckoutItem): number => {
        if (item.pricingByPeriod && item.isRecurring) {
            const periodKey = billingPeriod === 'yearly' ? 'yearly' : 'monthly';
            const periodPricing = item.pricingByPeriod[periodKey];
            if (periodPricing) {
                return periodPricing.price * item.quantity;
            }
        }
        return item.totalPrice;
    };

    // Helper to get formatted item price based on current billing period
    const getFormattedItemPrice = (item: CheckoutItem): string => {
        if (item.pricingByPeriod && item.isRecurring) {
            const periodKey = billingPeriod === 'yearly' ? 'yearly' : 'monthly';
            const periodPricing = item.pricingByPeriod[periodKey];
            if (periodPricing) {
                return formatPrice(periodPricing.price * item.quantity);
            }
        }
        return item.formattedTotalPrice;
    };

    // Count recurring vs one-time items
    const recurringItems = items.filter((item) => item.isRecurring);
    const oneTimeItems = items.filter((item) => !item.isRecurring);
    const hasRecurring = recurringItems.length > 0;
    const hasOneTime = oneTimeItems.length > 0;

    // Calculate recurring and one-time totals using dynamic pricing
    const recurringTotal = recurringItems.reduce((sum, item) => sum + getItemPrice(item), 0);
    const oneTimeTotal = oneTimeItems.reduce((sum, item) => sum + getItemPrice(item), 0);

    // Get billing period label
    const periodLabel =
        billingPeriod === 'yearly'
            ? t('billing.per_year', { default: '/year' })
            : t('billing.per_month', { default: '/month' });

    return (
        <div className={cn('space-y-4', className)}>
            {/* Plan change info */}
            {planChange && (
                <div className="bg-muted/50 rounded-lg p-3">
                    <div className="mb-2 flex items-center gap-2 text-sm font-medium">
                        <RefreshCw className="h-4 w-4" />
                        {t('billing.plan_change', { default: 'Plan Change' })}
                    </div>
                    <div className="flex items-center gap-2 text-sm">
                        <span className="text-muted-foreground">{planChange.from.name}</span>
                        <ArrowRight className="h-4 w-4" />
                        <span className="font-medium">{planChange.to.name}</span>
                    </div>
                    {planChange.prorationAmount !== 0 && (
                        <div className="mt-2 flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                {t('billing.proration', { default: 'Proration adjustment' })}
                            </span>
                            <span
                                className={cn(
                                    'font-medium',
                                    planChange.prorationAmount < 0 && 'text-green-600'
                                )}
                            >
                                {planChange.formattedProration}
                            </span>
                        </div>
                    )}
                    {planChange.effectiveDate && (
                        <div className="text-muted-foreground mt-1 flex items-center gap-1 text-xs">
                            <Calendar className="h-3 w-3" />
                            {t('billing.effective', { default: 'Effective' })}:{' '}
                            {new Date(planChange.effectiveDate).toLocaleDateString()}
                        </div>
                    )}
                </div>
            )}

            {/* Items list (compact) */}
            {showItems && items.length > 0 && (
                <div className="space-y-2">
                    {items.map((item) => (
                        <div key={item.id} className="flex items-center justify-between text-sm">
                            <div className="flex items-center gap-2">
                                <span className="truncate">{item.product.name}</span>
                                {item.quantity > 1 && (
                                    <span className="text-muted-foreground">
                                        x{item.quantity}
                                    </span>
                                )}
                            </div>
                            <span>{getFormattedItemPrice(item)}</span>
                        </div>
                    ))}
                    <Separator />
                </div>
            )}

            {/* Subtotal */}
            <div className="flex items-center justify-between text-sm">
                <span className="text-muted-foreground">
                    {t('billing.subtotal', { default: 'Subtotal' })}
                </span>
                <span>{formattedSubtotal || formatPrice(subtotal)}</span>
            </div>

            {/* Discount */}
            {discount > 0 && (
                <div className="flex items-center justify-between text-sm text-green-600">
                    <div className="flex items-center gap-1">
                        <Tag className="h-3.5 w-3.5" />
                        <span>{t('billing.discount', { default: 'Discount' })}</span>
                    </div>
                    <span>-{formattedDiscount || formatPrice(discount)}</span>
                </div>
            )}

            <Separator />

            {/* Total */}
            <div className="flex items-center justify-between">
                <span className="font-medium">
                    {t('billing.total', { default: 'Total' })}
                </span>
                <div className="text-right">
                    <span className="text-lg font-semibold">
                        {formattedTotal || formatPrice(total)}
                    </span>
                    {hasRecurring && (
                        <span className="text-muted-foreground ml-1 text-sm">
                            {periodLabel}
                        </span>
                    )}
                </div>
            </div>

            {/* Breakdown by billing type */}
            {hasRecurring && hasOneTime && (
                <div className="bg-muted/50 space-y-2 rounded-lg p-3 text-sm">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-1">
                            <RefreshCw className="text-muted-foreground h-3.5 w-3.5" />
                            <span className="text-muted-foreground">
                                {t('billing.recurring', { default: 'Recurring' })}
                            </span>
                        </div>
                        <span>
                            {formatPrice(recurringTotal)}
                            {periodLabel}
                        </span>
                    </div>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-1">
                            <CreditCard className="text-muted-foreground h-3.5 w-3.5" />
                            <span className="text-muted-foreground">
                                {t('billing.one_time', { default: 'One-time' })}
                            </span>
                        </div>
                        <span>{formatPrice(oneTimeTotal)}</span>
                    </div>
                </div>
            )}

            {/* Due today info */}
            {hasRecurring && (
                <div className="text-muted-foreground flex items-center justify-between text-xs">
                    <span>{t('billing.due_today', { default: 'Due today' })}</span>
                    <span className="font-medium text-foreground">
                        {formattedTotal || formatPrice(total)}
                    </span>
                </div>
            )}

            {/* Recurring billing note */}
            {hasRecurring && (
                <p className="text-muted-foreground text-xs">
                    {billingPeriod === 'yearly'
                        ? t('billing.billed_yearly_note', {
                              default: 'You will be billed annually. Cancel anytime.',
                          })
                        : t('billing.billed_monthly_note', {
                              default: 'You will be billed monthly. Cancel anytime.',
                          })}
                </p>
            )}
        </div>
    );
}

/**
 * CheckoutSummarySkeleton - Loading skeleton
 */
export function CheckoutSummarySkeleton({ className }: { className?: string }) {
    return (
        <div className={cn('space-y-4', className)}>
            <div className="flex items-center justify-between">
                <div className="bg-muted h-4 w-16 animate-pulse rounded" />
                <div className="bg-muted h-4 w-20 animate-pulse rounded" />
            </div>
            <Separator />
            <div className="flex items-center justify-between">
                <div className="bg-muted h-5 w-12 animate-pulse rounded" />
                <div className="bg-muted h-6 w-24 animate-pulse rounded" />
            </div>
            <div className="bg-muted h-4 w-full animate-pulse rounded" />
        </div>
    );
}
