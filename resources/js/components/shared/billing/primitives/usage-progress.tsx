import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Progress } from '@/components/ui/progress';
import type { UsageProgressProps } from '@/types/billing';

/**
 * UsageProgress - Progress bar showing resource usage vs limit
 *
 * @example
 * // Basic usage
 * <UsageProgress label="Users" used={12} limit={25} />
 *
 * @example
 * // With custom formatting
 * <UsageProgress
 *     label="Storage"
 *     used={15360}
 *     limit={51200}
 *     formatValue={(v) => `${(v / 1024).toFixed(1)} GB`}
 * />
 *
 * @example
 * // Unlimited
 * <UsageProgress label="Projects" used={42} limit={null} />
 */
export function UsageProgress({
    label,
    used,
    limit,
    formatValue,
    showPercentage = true,
    showValues = true,
    warningThreshold = 80,
    size = 'md',
    className,
}: UsageProgressProps) {
    const { t } = useLaravelReactI18n();

    const isUnlimited = limit === null || limit === -1;
    const percentage = isUnlimited ? 0 : Math.min((used / limit) * 100, 100);
    const isNearLimit = !isUnlimited && percentage >= warningThreshold;
    const isOverLimit = !isUnlimited && used > limit;

    const formatNumber = (value: number): string => {
        if (formatValue) return formatValue(value);

        // Smart formatting for large numbers
        if (value >= 1000000) {
            return `${(value / 1000000).toFixed(1)}M`;
        }
        if (value >= 1000) {
            return `${(value / 1000).toFixed(1)}K`;
        }
        return value.toLocaleString();
    };

    const getUsageText = (): string => {
        if (isUnlimited) {
            return `${formatNumber(used)} / ${t('billing.unlimited', { default: 'Unlimited' })}`;
        }
        return `${formatNumber(used)} / ${formatNumber(limit)}`;
    };

    const getProgressColor = (): string => {
        if (isOverLimit) return 'bg-red-500';
        if (isNearLimit) return 'bg-amber-500';
        return 'bg-primary';
    };

    const sizeClasses = {
        sm: {
            label: 'text-xs',
            progress: 'h-1.5',
            values: 'text-xs',
        },
        md: {
            label: 'text-sm',
            progress: 'h-2',
            values: 'text-sm',
        },
        lg: {
            label: 'text-base',
            progress: 'h-3',
            values: 'text-base',
        },
    };

    const styles = sizeClasses[size];

    return (
        <div className={cn('space-y-1.5', className)}>
            <div className="flex items-center justify-between">
                <span className={cn('font-medium', styles.label)}>{label}</span>

                <div className="flex items-center gap-2">
                    {showValues && (
                        <span
                            className={cn(
                                'text-muted-foreground',
                                styles.values,
                                isOverLimit && 'text-red-500 font-medium',
                                isNearLimit && !isOverLimit && 'text-amber-500'
                            )}
                        >
                            {getUsageText()}
                        </span>
                    )}

                    {showPercentage && !isUnlimited && (
                        <span
                            className={cn(
                                'text-muted-foreground',
                                styles.values,
                                isOverLimit && 'text-red-500 font-medium',
                                isNearLimit && !isOverLimit && 'text-amber-500'
                            )}
                        >
                            ({Math.round(percentage)}%)
                        </span>
                    )}
                </div>
            </div>

            <div className="relative">
                <Progress
                    value={isUnlimited ? 0 : percentage}
                    className={cn(styles.progress, 'bg-muted')}
                />
                {/* Custom color overlay */}
                <div
                    className={cn(
                        'absolute inset-y-0 left-0 rounded-full transition-all',
                        styles.progress,
                        getProgressColor()
                    )}
                    style={{ width: `${Math.min(percentage, 100)}%` }}
                />
            </div>

            {isOverLimit && (
                <p className="text-xs text-red-500">
                    {t('billing.over_limit', {
                        default: 'You have exceeded your limit by :amount',
                        amount: formatNumber(used - limit),
                    })}
                </p>
            )}
        </div>
    );
}

/**
 * UsageProgressCompact - Compact version for inline usage display
 */
export interface UsageProgressCompactProps {
    used: number;
    limit: number | null;
    formatValue?: (value: number) => string;
    warningThreshold?: number;
    className?: string;
}

export function UsageProgressCompact({
    used,
    limit,
    formatValue,
    warningThreshold = 80,
    className,
}: UsageProgressCompactProps) {
    const { t } = useLaravelReactI18n();

    const isUnlimited = limit === null || limit === -1;
    const percentage = isUnlimited ? 0 : Math.min((used / limit) * 100, 100);
    const isNearLimit = !isUnlimited && percentage >= warningThreshold;
    const isOverLimit = !isUnlimited && used > limit;

    const formatNumber = (value: number): string => {
        if (formatValue) return formatValue(value);
        return value.toLocaleString();
    };

    return (
        <span
            className={cn(
                'text-sm',
                isOverLimit && 'text-red-500 font-medium',
                isNearLimit && !isOverLimit && 'text-amber-500',
                !isNearLimit && !isOverLimit && 'text-muted-foreground',
                className
            )}
        >
            {formatNumber(used)}
            {' / '}
            {isUnlimited
                ? t('billing.unlimited', { default: 'Unlimited' })
                : formatNumber(limit)}
        </span>
    );
}
