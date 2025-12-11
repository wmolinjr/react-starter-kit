import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Badge } from '@/components/ui/badge';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { PricingToggleProps } from '@/types/billing';

/**
 * PricingToggle - Toggle between monthly and yearly billing periods
 *
 * @example
 * // Basic usage
 * <PricingToggle
 *     value={period}
 *     onChange={setPeriod}
 *     savings="Save 20%"
 * />
 *
 * @example
 * // Custom labels
 * <PricingToggle
 *     value={period}
 *     onChange={setPeriod}
 *     monthlyLabel="Pay Monthly"
 *     yearlyLabel="Pay Yearly"
 *     savings="2 months free"
 * />
 */
export function PricingToggle({
    value,
    onChange,
    savings,
    monthlyLabel,
    yearlyLabel,
    disabled = false,
    className,
}: PricingToggleProps) {
    const { t } = useLaravelReactI18n();

    const defaultMonthlyLabel = t('enums.billing.period.monthly', { default: 'Monthly' });
    const defaultYearlyLabel = t('enums.billing.period.yearly', { default: 'Yearly' });

    return (
        <div className={cn('flex items-center gap-3', className)}>
            <ToggleGroup
                type="single"
                value={value}
                onValueChange={(val) => {
                    if (val && !disabled) {
                        onChange(val as 'monthly' | 'yearly');
                    }
                }}
                disabled={disabled}
                className="bg-muted rounded-lg p-1"
            >
                <ToggleGroupItem
                    value="monthly"
                    aria-label={monthlyLabel || defaultMonthlyLabel}
                    className={cn(
                        'rounded-md px-4 py-2 text-sm font-medium transition-all',
                        'data-[state=on]:bg-background data-[state=on]:shadow-sm'
                    )}
                >
                    {monthlyLabel || defaultMonthlyLabel}
                </ToggleGroupItem>

                <ToggleGroupItem
                    value="yearly"
                    aria-label={yearlyLabel || defaultYearlyLabel}
                    className={cn(
                        'rounded-md px-4 py-2 text-sm font-medium transition-all',
                        'data-[state=on]:bg-background data-[state=on]:shadow-sm'
                    )}
                >
                    {yearlyLabel || defaultYearlyLabel}
                </ToggleGroupItem>
            </ToggleGroup>

            {savings && value === 'yearly' && (
                <Badge variant="secondary" className="bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                    {savings}
                </Badge>
            )}
        </div>
    );
}
