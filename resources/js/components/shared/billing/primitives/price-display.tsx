import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { PriceDisplayProps } from '@/types/billing';

/**
 * PriceDisplay - Displays formatted price with optional period and original price
 *
 * @example
 * // Basic price
 * <PriceDisplay amount={2900} currency="USD" period="monthly" />
 *
 * @example
 * // With discount
 * <PriceDisplay amount={2320} originalAmount={2900} currency="USD" period="monthly" />
 *
 * @example
 * // Large size for hero
 * <PriceDisplay amount={2900} currency="USD" period="monthly" size="xl" />
 */
export function PriceDisplay({
    amount,
    currency = 'USD',
    period = null,
    originalAmount,
    size = 'md',
    showPeriod = true,
    className,
}: PriceDisplayProps) {
    const { t } = useLaravelReactI18n();

    const formatPrice = (value: number): string => {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
            minimumFractionDigits: value % 100 === 0 ? 0 : 2,
            maximumFractionDigits: 2,
        }).format(value / 100);
    };

    const getPeriodLabel = (): string => {
        if (!period || !showPeriod) return '';

        const labels: Record<string, string> = {
            monthly: t('enums.billing.period.monthly.short', { default: '/mo' }),
            yearly: t('enums.billing.period.yearly.short', { default: '/yr' }),
            one_time: t('enums.billing.period.one_time.short', { default: 'one-time' }),
        };

        return labels[period] || '';
    };

    const sizeClasses = {
        sm: {
            price: 'text-lg font-semibold',
            period: 'text-xs',
            original: 'text-xs',
        },
        md: {
            price: 'text-2xl font-bold',
            period: 'text-sm',
            original: 'text-sm',
        },
        lg: {
            price: 'text-3xl font-bold',
            period: 'text-base',
            original: 'text-base',
        },
        xl: {
            price: 'text-4xl font-bold tracking-tight',
            period: 'text-lg',
            original: 'text-lg',
        },
    };

    const styles = sizeClasses[size];

    return (
        <div className={cn('flex items-baseline gap-1', className)}>
            {originalAmount && originalAmount > amount && (
                <span
                    className={cn(
                        'text-muted-foreground line-through',
                        styles.original
                    )}
                >
                    {formatPrice(originalAmount)}
                </span>
            )}

            <span className={styles.price}>{formatPrice(amount)}</span>

            {showPeriod && period && period !== 'one_time' && (
                <span className={cn('text-muted-foreground', styles.period)}>
                    {getPeriodLabel()}
                </span>
            )}

            {showPeriod && period === 'one_time' && (
                <span className={cn('text-muted-foreground', styles.period)}>
                    {getPeriodLabel()}
                </span>
            )}
        </div>
    );
}
