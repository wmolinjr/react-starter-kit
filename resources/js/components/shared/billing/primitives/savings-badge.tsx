import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Badge } from '@/components/ui/badge';
import type { SavingsBadgeProps } from '@/types/billing';

/**
 * SavingsBadge - Displays savings amount or percentage
 *
 * @example
 * // Percentage savings
 * <SavingsBadge percent={20} />
 *
 * @example
 * // Amount savings
 * <SavingsBadge amount={4000} currency="USD" />
 *
 * @example
 * // Success variant
 * <SavingsBadge percent={25} variant="success" />
 */
export function SavingsBadge({
    amount,
    percent,
    currency = 'USD',
    variant = 'default',
    size = 'md',
    className,
}: SavingsBadgeProps) {
    const { t } = useLaravelReactI18n();

    const formatAmount = (value: number): string => {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value / 100);
    };

    const getText = (): string => {
        if (percent) {
            return t('billing.save_percent', {
                default: 'Save :percent%',
                percent,
            });
        }

        if (amount) {
            return t('billing.save_amount', {
                default: 'Save :amount',
                amount: formatAmount(amount),
            });
        }

        return '';
    };

    const variantClasses = {
        default: 'bg-primary/10 text-primary border-primary/20',
        success: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 border-green-200 dark:border-green-800',
        accent: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800',
    };

    const sizeClasses = {
        sm: 'text-xs px-1.5 py-0.5',
        md: 'text-sm px-2 py-0.5',
    };

    const text = getText();
    if (!text) return null;

    return (
        <Badge
            variant="outline"
            className={cn(
                'font-medium',
                variantClasses[variant],
                sizeClasses[size],
                className
            )}
        >
            {text}
        </Badge>
    );
}
