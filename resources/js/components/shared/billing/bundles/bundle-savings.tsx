import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { TrendingDown, Percent, DollarSign } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

export interface BundleSavingsProps {
    /** Price if bought individually */
    individualPrice: number;
    /** Bundle price */
    bundlePrice: number;
    /** Currency code */
    currency?: string;
    /** Billing period for display */
    period?: 'monthly' | 'yearly';
    /** Display variant */
    variant?: 'badge' | 'inline' | 'detailed';
    /** Size */
    size?: 'sm' | 'md' | 'lg';
    /** Additional className */
    className?: string;
}

/**
 * BundleSavings - Displays the savings from purchasing a bundle
 *
 * @example
 * // Badge variant (default)
 * <BundleSavings
 *     individualPrice={5000}
 *     bundlePrice={4000}
 *     currency="USD"
 * />
 *
 * @example
 * // Detailed variant
 * <BundleSavings
 *     individualPrice={5000}
 *     bundlePrice={4000}
 *     variant="detailed"
 *     period="monthly"
 * />
 */
export function BundleSavings({
    individualPrice,
    bundlePrice,
    currency = 'USD',
    period,
    variant = 'badge',
    size = 'md',
    className,
}: BundleSavingsProps) {
    const { t } = useLaravelReactI18n();

    // Calculate savings
    const savingsAmount = individualPrice - bundlePrice;
    const savingsPercent = individualPrice > 0
        ? Math.round((savingsAmount / individualPrice) * 100)
        : 0;

    // Don't show if no savings
    if (savingsAmount <= 0) {
        return null;
    }

    // Format price
    const formatPrice = (amount: number): string => {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
            minimumFractionDigits: amount % 100 === 0 ? 0 : 2,
            maximumFractionDigits: 2,
        }).format(amount / 100);
    };

    // Period suffix
    const periodSuffix = period === 'yearly'
        ? t('billing.per_year', { default: '/yr' })
        : period === 'monthly'
          ? t('billing.per_month', { default: '/mo' })
          : '';

    // Size classes
    const sizeClasses = {
        sm: 'text-xs',
        md: 'text-sm',
        lg: 'text-base',
    };

    // Badge variant
    if (variant === 'badge') {
        return (
            <Badge
                className={cn(
                    'gap-1 bg-green-500 text-white hover:bg-green-600',
                    sizeClasses[size],
                    className
                )}
            >
                <TrendingDown className="h-3 w-3" />
                {t('billing.save', { default: 'Save' })} {savingsPercent}%
            </Badge>
        );
    }

    // Inline variant
    if (variant === 'inline') {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1 font-medium text-green-600',
                    sizeClasses[size],
                    className
                )}
            >
                <TrendingDown className="h-3.5 w-3.5" />
                {t('billing.save', { default: 'Save' })} {formatPrice(savingsAmount)}
                {periodSuffix}
            </span>
        );
    }

    // Detailed variant
    return (
        <div className={cn('space-y-2', className)}>
            {/* Savings badge */}
            <div className="flex items-center gap-2">
                <Badge className="gap-1 bg-green-500 text-white hover:bg-green-600">
                    <Percent className="h-3 w-3" />
                    {savingsPercent}% {t('billing.off', { default: 'OFF' })}
                </Badge>
                <span className="text-muted-foreground text-sm">
                    {t('billing.bundle_discount', { default: 'Bundle Discount' })}
                </span>
            </div>

            {/* Price comparison */}
            <div className="flex items-baseline gap-3">
                <div className="flex items-center gap-1">
                    <DollarSign className="text-muted-foreground h-4 w-4" />
                    <span className="text-muted-foreground text-sm line-through">
                        {formatPrice(individualPrice)}
                    </span>
                </div>
                <div className="flex items-center gap-1">
                    <span className="text-lg font-semibold text-green-600">
                        {formatPrice(bundlePrice)}
                    </span>
                    {periodSuffix && (
                        <span className="text-muted-foreground text-sm">{periodSuffix}</span>
                    )}
                </div>
            </div>

            {/* Savings amount */}
            <p className="text-sm text-green-600">
                {t('billing.you_save', { default: 'You save' })}{' '}
                <span className="font-semibold">{formatPrice(savingsAmount)}</span>
                {periodSuffix}
            </p>
        </div>
    );
}

export interface BundleSavingsCompactProps {
    /** Savings percentage */
    percent: number;
    /** Display size */
    size?: 'sm' | 'md';
    /** Additional className */
    className?: string;
}

/**
 * BundleSavingsCompact - Simple percentage badge
 *
 * @example
 * <BundleSavingsCompact percent={20} />
 */
export function BundleSavingsCompact({
    percent,
    size = 'md',
    className,
}: BundleSavingsCompactProps) {
    if (percent <= 0) return null;

    return (
        <Badge
            className={cn(
                'gap-1 bg-green-500 text-white hover:bg-green-600',
                size === 'sm' ? 'text-xs px-1.5 py-0.5' : 'text-sm',
                className
            )}
        >
            -{percent}%
        </Badge>
    );
}
