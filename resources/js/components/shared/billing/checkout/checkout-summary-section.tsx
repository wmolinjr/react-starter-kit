import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Receipt, RefreshCw, CreditCard, Calendar } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { CheckoutItem, PlanChangeInfo } from '@/types/billing';
import type { BillingPeriod } from '@/types/enums';

export interface CheckoutSummarySectionProps {
    /** Items in the checkout */
    items: CheckoutItem[];
    /** Current billing period */
    billingPeriod: BillingPeriod;
    /** Currency code */
    currency?: string;
    /** Plan change information */
    planChange?: PlanChangeInfo;
    /** Additional className */
    className?: string;
}

/**
 * CheckoutSummarySection - Detailed order summary for dedicated checkout page
 *
 * Shows breakdown by recurring vs one-time items, next billing date,
 * and total amount due today.
 *
 * @example
 * <CheckoutSummarySection
 *     items={items}
 *     billingPeriod={period}
 *     currency="BRL"
 * />
 */
export function CheckoutSummarySection({
    items,
    billingPeriod,
    currency = 'BRL',
    planChange,
    className,
}: CheckoutSummarySectionProps) {
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

    // Separate recurring and one-time items
    const recurringItems = items.filter((item) => item.isRecurring);
    const oneTimeItems = items.filter((item) => !item.isRecurring);

    // Calculate totals
    const recurringTotal = recurringItems.reduce((sum, item) => sum + getItemPrice(item), 0);
    const oneTimeTotal = oneTimeItems.reduce((sum, item) => sum + getItemPrice(item), 0);
    const total = recurringTotal + oneTimeTotal;

    // Get billing period label
    const periodLabel =
        billingPeriod === 'yearly'
            ? t('checkout.per_year', { default: '/year' })
            : t('checkout.per_month', { default: '/month' });

    // Calculate next billing date
    const getNextBillingDate = (): string => {
        const date = new Date();
        if (billingPeriod === 'yearly') {
            date.setFullYear(date.getFullYear() + 1);
        } else {
            date.setMonth(date.getMonth() + 1);
        }
        return date.toLocaleDateString();
    };

    return (
        <Card className={cn('', className)}>
            <CardHeader className="pb-4">
                <div className="flex items-center gap-2">
                    <Receipt className="h-5 w-5 text-muted-foreground" />
                    <CardTitle className="text-lg">
                        {t('checkout.order_summary', { default: 'Order Summary' })}
                    </CardTitle>
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                {/* Plan change info */}
                {planChange && (
                    <div className="bg-muted/50 rounded-lg p-3">
                        <div className="mb-2 flex items-center gap-2 text-sm font-medium">
                            <RefreshCw className="h-4 w-4" />
                            {t('checkout.plan_change', { default: 'Plan Change' })}
                        </div>
                        <div className="text-sm">
                            <span className="text-muted-foreground">{planChange.from.name}</span>
                            <span className="mx-2">→</span>
                            <span className="font-medium">{planChange.to.name}</span>
                        </div>
                        {planChange.prorationAmount !== 0 && (
                            <div className="mt-2 flex justify-between text-sm">
                                <span className="text-muted-foreground">
                                    {t('checkout.proration', { default: 'Proration' })}
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
                    </div>
                )}

                {/* Recurring items */}
                {recurringItems.length > 0 && (
                    <div className="space-y-2">
                        <div className="flex items-center gap-1 text-sm font-medium">
                            <RefreshCw className="h-3.5 w-3.5 text-muted-foreground" />
                            {t('checkout.subscriptions', { default: 'Subscriptions' })}
                        </div>
                        {recurringItems.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between text-sm"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="truncate">{item.product.name}</span>
                                    {item.quantity > 1 && (
                                        <span className="text-muted-foreground">
                                            x{item.quantity}
                                        </span>
                                    )}
                                </div>
                                <span>
                                    {formatPrice(getItemPrice(item))}
                                    <span className="text-muted-foreground text-xs ml-0.5">
                                        {periodLabel}
                                    </span>
                                </span>
                            </div>
                        ))}
                        <div className="flex items-center gap-1 text-xs text-muted-foreground pt-1">
                            <Calendar className="h-3 w-3" />
                            {t('checkout.next_billing', { default: 'Next billing' })}:{' '}
                            {getNextBillingDate()}
                        </div>
                    </div>
                )}

                {/* One-time items */}
                {oneTimeItems.length > 0 && (
                    <div className="space-y-2">
                        <div className="flex items-center gap-1 text-sm font-medium">
                            <CreditCard className="h-3.5 w-3.5 text-muted-foreground" />
                            {t('checkout.one_time_purchases', { default: 'One-time Purchases' })}
                        </div>
                        {oneTimeItems.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between text-sm"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="truncate">{item.product.name}</span>
                                    {item.quantity > 1 && (
                                        <span className="text-muted-foreground">
                                            x{item.quantity}
                                        </span>
                                    )}
                                </div>
                                <span>{formatPrice(getItemPrice(item))}</span>
                            </div>
                        ))}
                    </div>
                )}

                <Separator />

                {/* Breakdown */}
                {recurringItems.length > 0 && oneTimeItems.length > 0 && (
                    <div className="space-y-1 text-sm">
                        <div className="flex justify-between text-muted-foreground">
                            <span>{t('checkout.recurring_subtotal', { default: 'Recurring' })}</span>
                            <span>
                                {formatPrice(recurringTotal)}
                                {periodLabel}
                            </span>
                        </div>
                        <div className="flex justify-between text-muted-foreground">
                            <span>{t('checkout.one_time_subtotal', { default: 'One-time' })}</span>
                            <span>{formatPrice(oneTimeTotal)}</span>
                        </div>
                    </div>
                )}

                {/* Total */}
                <div className="flex items-center justify-between pt-2">
                    <span className="text-lg font-semibold">
                        {t('checkout.total_today', { default: 'Total Today' })}
                    </span>
                    <span className="text-2xl font-bold">{formatPrice(total)}</span>
                </div>

                {/* Recurring billing note */}
                {recurringItems.length > 0 && (
                    <p className="text-xs text-muted-foreground">
                        {billingPeriod === 'yearly'
                            ? t('checkout.billed_yearly_note', {
                                  default: 'You will be billed annually. Cancel anytime.',
                              })
                            : t('checkout.billed_monthly_note', {
                                  default: 'You will be billed monthly. Cancel anytime.',
                              })}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
