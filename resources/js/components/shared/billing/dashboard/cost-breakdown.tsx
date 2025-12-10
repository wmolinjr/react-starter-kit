import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Crown, Package, Puzzle, Receipt, TrendingUp } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Progress } from '@/components/ui/progress';
import type { CostBreakdown as CostBreakdownType } from '@/types/billing';

export interface CostBreakdownProps {
    /** Cost breakdown data */
    costs: CostBreakdownType;
    /** Whether to show as a card */
    asCard?: boolean;
    /** Whether to show detailed breakdown */
    showDetails?: boolean;
    /** Whether to show percentage bars */
    showPercentages?: boolean;
    /** Additional className */
    className?: string;
}

/**
 * CostBreakdownWidget - Displays cost breakdown by category
 *
 * @example
 * <CostBreakdownWidget
 *     costs={overview.costs}
 *     showDetails
 *     showPercentages
 * />
 */
export function CostBreakdownWidget({
    costs,
    asCard = true,
    showDetails = true,
    showPercentages = false,
    className,
}: CostBreakdownProps) {
    const { t } = useLaravelReactI18n();

    // Calculate percentages
    const total = costs.totalMonthlyCost;
    const planPercent = total > 0 ? (costs.planCost / total) * 100 : 0;
    const addonsPercent = total > 0 ? (costs.addonsCost / total) * 100 : 0;
    const bundlesPercent = total > 0 ? (costs.bundlesCost / total) * 100 : 0;

    const content = (
        <div className={cn('space-y-4', !asCard && className)}>
            {/* Total */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <Receipt className="text-muted-foreground h-5 w-5" />
                    <span className="font-medium">
                        {t('billing.monthly_total', { default: 'Monthly Total' })}
                    </span>
                </div>
                <span className="text-2xl font-bold">{costs.formattedTotal}</span>
            </div>

            {showDetails && (
                <>
                    <Separator />

                    {/* Plan cost */}
                    <div className="space-y-2">
                        <div className="flex items-center justify-between text-sm">
                            <div className="flex items-center gap-2">
                                <Crown className="h-4 w-4 text-purple-500" />
                                <span>{t('billing.plan', { default: 'Plan' })}</span>
                            </div>
                            <span className="font-medium">{costs.formattedPlanCost}</span>
                        </div>
                        {showPercentages && planPercent > 0 && (
                            <Progress value={planPercent} className="h-1.5" />
                        )}
                    </div>

                    {/* Addons cost */}
                    {costs.addonsCost > 0 && (
                        <div className="space-y-2">
                            <div className="flex items-center justify-between text-sm">
                                <div className="flex items-center gap-2">
                                    <Puzzle className="h-4 w-4 text-blue-500" />
                                    <span>{t('billing.addons', { default: 'Add-ons' })}</span>
                                </div>
                                <span className="font-medium">{costs.formattedAddonsCost}</span>
                            </div>
                            {showPercentages && addonsPercent > 0 && (
                                <Progress value={addonsPercent} className="h-1.5" />
                            )}
                        </div>
                    )}

                    {/* Bundles cost */}
                    {costs.bundlesCost > 0 && (
                        <div className="space-y-2">
                            <div className="flex items-center justify-between text-sm">
                                <div className="flex items-center gap-2">
                                    <Package className="h-4 w-4 text-amber-500" />
                                    <span>{t('billing.bundles', { default: 'Bundles' })}</span>
                                </div>
                                <span className="font-medium">{costs.formattedBundlesCost}</span>
                            </div>
                            {showPercentages && bundlesPercent > 0 && (
                                <Progress value={bundlesPercent} className="h-1.5" />
                            )}
                        </div>
                    )}
                </>
            )}
        </div>
    );

    if (!asCard) {
        return content;
    }

    return (
        <Card className={className}>
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-base">
                    <TrendingUp className="h-4 w-4" />
                    {t('billing.cost_breakdown', { default: 'Cost Breakdown' })}
                </CardTitle>
                <CardDescription>
                    {t('billing.monthly_billing', { default: 'Your monthly billing' })}
                </CardDescription>
            </CardHeader>
            <CardContent>{content}</CardContent>
        </Card>
    );
}

/**
 * CostBreakdownSkeleton - Loading skeleton
 */
export function CostBreakdownSkeleton({
    asCard = true,
    className,
}: {
    asCard?: boolean;
    className?: string;
}) {
    const content = (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="bg-muted h-5 w-28 animate-pulse rounded" />
                <div className="bg-muted h-8 w-24 animate-pulse rounded" />
            </div>
            <Separator />
            {Array.from({ length: 2 }).map((_, i) => (
                <div key={i} className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="bg-muted h-4 w-4 animate-pulse rounded" />
                        <div className="bg-muted h-4 w-20 animate-pulse rounded" />
                    </div>
                    <div className="bg-muted h-4 w-16 animate-pulse rounded" />
                </div>
            ))}
        </div>
    );

    if (!asCard) {
        return <div className={className}>{content}</div>;
    }

    return (
        <Card className={className}>
            <CardHeader className="pb-2">
                <div className="bg-muted h-5 w-32 animate-pulse rounded" />
                <div className="bg-muted mt-1 h-4 w-40 animate-pulse rounded" />
            </CardHeader>
            <CardContent>{content}</CardContent>
        </Card>
    );
}
