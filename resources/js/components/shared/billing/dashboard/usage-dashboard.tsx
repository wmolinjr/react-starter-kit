import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    Users,
    FolderKanban,
    HardDrive,
    Zap,
    Shield,
    Globe,
    Activity,
    AlertTriangle,
    TrendingUp,
    Infinity as InfinityIcon,
} from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { UsageMetric } from '@/types/billing';

export interface UsageDashboardProps {
    /** Usage metrics */
    usage: Record<string, UsageMetric>;
    /** Whether to show as a card */
    asCard?: boolean;
    /** Layout variant */
    variant?: 'list' | 'grid' | 'compact';
    /** Show only metrics that are near/over limit */
    showOnlyAlerts?: boolean;
    /** Maximum metrics to show */
    maxItems?: number;
    /** Additional className */
    className?: string;
}

/**
 * UsageDashboard - Displays usage metrics with progress bars
 *
 * @example
 * <UsageDashboard
 *     usage={overview.usage}
 *     variant="grid"
 * />
 *
 * @example
 * // Compact variant for sidebar
 * <UsageDashboard
 *     usage={overview.usage}
 *     variant="compact"
 *     maxItems={3}
 * />
 */
export function UsageDashboard({
    usage,
    asCard = true,
    variant = 'list',
    showOnlyAlerts = false,
    maxItems,
    className,
}: UsageDashboardProps) {
    const { t } = useLaravelReactI18n();

    // Get icon for metric
    const getMetricIcon = (key: string) => {
        const iconMap: Record<string, React.ReactNode> = {
            users: <Users className="h-4 w-4" />,
            projects: <FolderKanban className="h-4 w-4" />,
            storage: <HardDrive className="h-4 w-4" />,
            apiCalls: <Zap className="h-4 w-4" />,
            api_calls: <Zap className="h-4 w-4" />,
            customRoles: <Shield className="h-4 w-4" />,
            custom_roles: <Shield className="h-4 w-4" />,
            locales: <Globe className="h-4 w-4" />,
        };

        return iconMap[key] || <Activity className="h-4 w-4" />;
    };

    // Get progress bar color
    const getProgressColor = (metric: UsageMetric): string => {
        if (metric.isOverLimit) return 'bg-red-500';
        if (metric.isNearLimit) return 'bg-amber-500';
        return 'bg-primary';
    };

    // Get status badge
    const getStatusBadge = (metric: UsageMetric) => {
        if (metric.isUnlimited) {
            return (
                <Badge variant="outline" className="gap-1 text-xs">
                    <InfinityIcon className="h-3 w-3" />
                    {t('billing.usage.unlimited', { default: 'Unlimited' })}
                </Badge>
            );
        }

        if (metric.isOverLimit) {
            return (
                <Badge variant="destructive" className="gap-1 text-xs">
                    <AlertTriangle className="h-3 w-3" />
                    {t('billing.usage.over_limit', { default: 'Over limit' })}
                </Badge>
            );
        }

        if (metric.isNearLimit) {
            return (
                <Badge
                    variant="outline"
                    className="gap-1 border-amber-500 text-xs text-amber-600"
                >
                    <AlertTriangle className="h-3 w-3" />
                    {t('billing.usage.near_limit', { default: 'Near limit' })}
                </Badge>
            );
        }

        return null;
    };

    // Filter and sort metrics
    let metrics = Object.values(usage);

    if (showOnlyAlerts) {
        metrics = metrics.filter((m) => m.isNearLimit || m.isOverLimit);
    }

    // Sort: over limit first, then near limit, then by percentage
    metrics.sort((a, b) => {
        if (a.isOverLimit && !b.isOverLimit) return -1;
        if (!a.isOverLimit && b.isOverLimit) return 1;
        if (a.isNearLimit && !b.isNearLimit) return -1;
        if (!a.isNearLimit && b.isNearLimit) return 1;
        return b.percentage - a.percentage;
    });

    if (maxItems) {
        metrics = metrics.slice(0, maxItems);
    }

    // Count alerts
    const alertCount = Object.values(usage).filter(
        (m) => m.isNearLimit || m.isOverLimit
    ).length;

    const content = (
        <TooltipProvider>
            <div
                className={cn(
                    variant === 'grid' && 'grid grid-cols-2 gap-4',
                    variant === 'list' && 'space-y-4',
                    variant === 'compact' && 'space-y-3',
                    !asCard && className
                )}
            >
                {metrics.length === 0 ? (
                    <div className="text-muted-foreground flex items-center justify-center py-4 text-sm">
                        {showOnlyAlerts
                            ? t('billing.usage.no_alerts', { default: 'No usage alerts' })
                            : t('billing.usage.no_data', { default: 'No usage data available' })}
                    </div>
                ) : (
                    metrics.map((metric) => (
                        <UsageMetricItem
                            key={metric.key}
                            metric={metric}
                            icon={getMetricIcon(metric.key)}
                            progressColor={getProgressColor(metric)}
                            statusBadge={getStatusBadge(metric)}
                            compact={variant === 'compact'}
                        />
                    ))
                )}
            </div>
        </TooltipProvider>
    );

    if (!asCard) {
        return content;
    }

    return (
        <Card className={className}>
            <CardHeader className="pb-2">
                <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <TrendingUp className="h-4 w-4" />
                        {t('billing.usage.title', { default: 'Usage' })}
                    </CardTitle>
                    {alertCount > 0 && (
                        <Badge variant="destructive" className="text-xs">
                            {alertCount} {t('billing.usage.alerts', { default: 'alerts' })}
                        </Badge>
                    )}
                </div>
                <CardDescription>
                    {t('billing.usage.resource_usage', { default: 'Your resource usage' })}
                </CardDescription>
            </CardHeader>
            <CardContent>{content}</CardContent>
        </Card>
    );
}

interface UsageMetricItemProps {
    metric: UsageMetric;
    icon: React.ReactNode;
    progressColor: string;
    statusBadge: React.ReactNode;
    compact?: boolean;
}

function UsageMetricItem({
    metric,
    icon,
    progressColor,
    statusBadge,
    compact = false,
}: UsageMetricItemProps) {
    const { t } = useLaravelReactI18n();

    if (compact) {
        return (
            <div className="space-y-1">
                <div className="flex items-center justify-between text-sm">
                    <div className="flex items-center gap-1.5">
                        <span className="text-muted-foreground">{icon}</span>
                        <span className="truncate">{metric.label}</span>
                    </div>
                    <span className="text-muted-foreground shrink-0 text-xs">
                        {metric.isUnlimited ? (
                            <InfinityIcon className="h-3 w-3" />
                        ) : (
                            `${metric.formattedUsed}/${metric.formattedLimit}`
                        )}
                    </span>
                </div>
                {!metric.isUnlimited && (
                    <Progress
                        value={Math.min(metric.percentage, 100)}
                        className={cn('h-1', progressColor)}
                    />
                )}
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <span className="text-muted-foreground">{icon}</span>
                    <span className="font-medium">{metric.label}</span>
                </div>
                {statusBadge}
            </div>

            <div className="flex items-center gap-3">
                {!metric.isUnlimited && (
                    <Progress
                        value={Math.min(metric.percentage, 100)}
                        className={cn('h-2 flex-1', progressColor)}
                    />
                )}
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span className="text-muted-foreground shrink-0 text-sm">
                            {metric.isUnlimited ? (
                                <span className="flex items-center gap-1">
                                    {metric.formattedUsed}
                                    <InfinityIcon className="h-3 w-3" />
                                </span>
                            ) : (
                                `${metric.formattedUsed} / ${metric.formattedLimit}`
                            )}
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>
                        {metric.isUnlimited
                            ? t('billing.usage.unlimited_usage', { default: 'Unlimited usage' })
                            : `${metric.percentage}% ${t('billing.usage.used', { default: 'used' })}`}
                    </TooltipContent>
                </Tooltip>
            </div>
        </div>
    );
}

/**
 * UsageDashboardSkeleton - Loading skeleton
 */
export function UsageDashboardSkeleton({
    asCard = true,
    items = 4,
    className,
}: {
    asCard?: boolean;
    items?: number;
    className?: string;
}) {
    const content = (
        <div className="space-y-4">
            {Array.from({ length: items }).map((_, i) => (
                <div key={i} className="space-y-2">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="bg-muted h-4 w-4 animate-pulse rounded" />
                            <div className="bg-muted h-4 w-24 animate-pulse rounded" />
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="bg-muted h-2 flex-1 animate-pulse rounded" />
                        <div className="bg-muted h-4 w-16 animate-pulse rounded" />
                    </div>
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
                <div className="bg-muted h-5 w-20 animate-pulse rounded" />
                <div className="bg-muted mt-1 h-4 w-32 animate-pulse rounded" />
            </CardHeader>
            <CardContent>{content}</CardContent>
        </Card>
    );
}

/**
 * UsageAlert - Compact alert for usage warnings
 */
export interface UsageAlertProps {
    /** Usage metrics */
    usage: Record<string, UsageMetric>;
    /** Additional className */
    className?: string;
}

export function UsageAlert({ usage, className }: UsageAlertProps) {
    const { t } = useLaravelReactI18n();

    const alerts = Object.values(usage).filter(
        (m) => m.isNearLimit || m.isOverLimit
    );

    if (alerts.length === 0) return null;

    const overLimit = alerts.filter((m) => m.isOverLimit);
    const nearLimit = alerts.filter((m) => m.isNearLimit && !m.isOverLimit);

    return (
        <div
            className={cn(
                'flex items-center gap-2 rounded-lg p-3',
                overLimit.length > 0
                    ? 'bg-red-50 text-red-700 dark:bg-red-950/20 dark:text-red-400'
                    : 'bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400',
                className
            )}
        >
            <AlertTriangle className="h-4 w-4 shrink-0" />
            <div className="min-w-0 flex-1 text-sm">
                {overLimit.length > 0 && (
                    <p>
                        {t('billing.usage.over_limit_alert', {
                            default: ':count resource(s) over limit',
                            count: overLimit.length,
                        }).replace(':count', String(overLimit.length))}
                    </p>
                )}
                {nearLimit.length > 0 && (
                    <p>
                        {t('billing.usage.near_limit_alert', {
                            default: ':count resource(s) near limit',
                            count: nearLimit.length,
                        }).replace(':count', String(nearLimit.length))}
                    </p>
                )}
            </div>
        </div>
    );
}
