import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    Crown,
    Calendar,
    Settings,
    ArrowUp,
    FileText,
    Package,
    Puzzle,
    AlertTriangle,
    Clock,
    CheckCircle2,
    ExternalLink,
} from 'lucide-react';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { CostBreakdownWidget } from './cost-breakdown';
import { UsageDashboard, UsageAlert } from './usage-dashboard';
import type {
    SubscriptionOverview as SubscriptionOverviewType,
    SubscriptionInfo,
} from '@/types/billing';
import type { PlanResource } from '@/types/resources';

export interface SubscriptionOverviewProps {
    /** Complete subscription overview data */
    overview: SubscriptionOverviewType;
    /** Alternative: just plan data (for simpler use cases) */
    plan?: PlanResource;
    /** Alternative: subscription info */
    subscription?: SubscriptionInfo | null;
    /** Trial end date */
    trialEndsAt?: string | null;
    /** Callback to upgrade plan */
    onUpgrade?: () => void;
    /** Callback to manage addons */
    onManageAddons?: () => void;
    /** Callback to view invoices */
    onViewInvoices?: () => void;
    /** Callback to manage subscription (Stripe portal) */
    onManageSubscription?: () => void;
    /** Layout variant */
    variant?: 'full' | 'compact' | 'minimal';
    /** Additional className */
    className?: string;
}

/**
 * SubscriptionOverviewWidget - Main billing dashboard widget
 *
 * @example
 * <SubscriptionOverviewWidget
 *     overview={subscriptionOverview}
 *     onUpgrade={() => router.visit('/billing/upgrade')}
 *     onManageAddons={() => router.visit('/billing/addons')}
 *     onViewInvoices={() => router.visit('/billing/invoices')}
 * />
 */
export function SubscriptionOverviewWidget({
    overview,
    plan: propPlan,
    subscription: propSubscription,
    trialEndsAt,
    onUpgrade,
    onManageAddons,
    onViewInvoices,
    onManageSubscription,
    variant = 'full',
    className,
}: SubscriptionOverviewProps) {
    const { t } = useLaravelReactI18n();

    // Use overview data or fallback to props
    const plan = overview?.plan || propPlan;
    const subscription = overview?.subscription || propSubscription;
    const costs = overview?.costs;
    const usage = overview?.usage;
    const nextInvoice = overview?.nextInvoice;
    const addons = overview?.addons || [];
    const bundles = overview?.bundles || [];

    // Determine subscription status
    const isOnTrial = !!trialEndsAt || subscription?.status === 'trialing';
    const isCanceled = subscription?.cancelAtPeriodEnd || subscription?.status === 'canceled';
    const isPastDue = subscription?.status === 'past_due';
    void isPastDue; // Used in UI rendering below

    // Format date
    const formatDate = (dateStr: string | null | undefined): string => {
        if (!dateStr) return '';
        return new Date(dateStr).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    // Calculate trial days remaining
    const getTrialDaysRemaining = (): number | null => {
        const trialEnd = trialEndsAt || subscription?.trialEndsAt;
        if (!trialEnd) return null;
        const now = new Date();
        const end = new Date(trialEnd);
        const diff = Math.ceil((end.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
        return diff > 0 ? diff : 0;
    };

    const trialDaysRemaining = getTrialDaysRemaining();

    // Status badge
    const getStatusBadge = () => {
        if (isPastDue) {
            return (
                <Badge variant="destructive" className="gap-1">
                    <AlertTriangle className="h-3 w-3" />
                    {t('billing.status.past_due', { default: 'Past Due' })}
                </Badge>
            );
        }

        if (isCanceled) {
            return (
                <Badge variant="secondary" className="gap-1">
                    <Clock className="h-3 w-3" />
                    {t('billing.status.canceled', { default: 'Canceled' })}
                </Badge>
            );
        }

        if (isOnTrial) {
            return (
                <Badge variant="outline" className="gap-1 border-amber-500 text-amber-600">
                    <Clock className="h-3 w-3" />
                    {t('billing.status.trial', { default: 'Trial' })}
                    {trialDaysRemaining !== null && ` (${trialDaysRemaining}d)`}
                </Badge>
            );
        }

        return (
            <Badge variant="outline" className="gap-1 border-green-500 text-green-600">
                <CheckCircle2 className="h-3 w-3" />
                {t('billing.status.active', { default: 'Active' })}
            </Badge>
        );
    };

    // Get plan name
    const planName = plan
        ? typeof plan.name === 'string'
            ? plan.name
            : plan.name
        : t('billing.plan.no_plan', { default: 'No Plan' });

    // Minimal variant
    if (variant === 'minimal') {
        return (
            <Card className={className}>
                <CardContent className="flex items-center justify-between p-4">
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 text-primary rounded-lg p-2">
                            <Crown className="h-5 w-5" />
                        </div>
                        <div>
                            <p className="font-medium">{planName}</p>
                            {costs && (
                                <p className="text-muted-foreground text-sm">
                                    {costs.formattedTotal}
                                    {t('billing.price.per_month', { default: '/mo' })}
                                </p>
                            )}
                        </div>
                    </div>
                    {getStatusBadge()}
                </CardContent>
            </Card>
        );
    }

    // Compact variant
    if (variant === 'compact') {
        return (
            <Card className={className}>
                <CardHeader className="pb-2">
                    <div className="flex items-center justify-between">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Crown className="h-4 w-4" />
                            {planName}
                        </CardTitle>
                        {getStatusBadge()}
                    </div>
                </CardHeader>
                <CardContent className="space-y-3">
                    {costs && (
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground text-sm">
                                {t('billing.price.monthly_cost', { default: 'Monthly cost' })}
                            </span>
                            <span className="font-semibold">{costs.formattedTotal}</span>
                        </div>
                    )}

                    {nextInvoice && (
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                {t('billing.invoice.next', { default: 'Next invoice' })}
                            </span>
                            <span>{formatDate(nextInvoice.date)}</span>
                        </div>
                    )}

                    {usage && Object.keys(usage).length > 0 && (
                        <UsageAlert usage={usage} />
                    )}
                </CardContent>
                <CardFooter className="flex gap-2 pt-0">
                    {onUpgrade && (
                        <Button size="sm" onClick={onUpgrade}>
                            <ArrowUp className="mr-1 h-3 w-3" />
                            {t('billing.plan.upgrade', { default: 'Upgrade' })}
                        </Button>
                    )}
                    {onManageSubscription && (
                        <Button size="sm" variant="outline" onClick={onManageSubscription}>
                            <Settings className="mr-1 h-3 w-3" />
                            {t('billing.subscription.manage', { default: 'Manage' })}
                        </Button>
                    )}
                </CardFooter>
            </Card>
        );
    }

    // Full variant
    return (
        <div className={cn('space-y-6', className)}>
            {/* Alerts */}
            {isPastDue && (
                <Alert variant="destructive">
                    <AlertTriangle className="h-4 w-4" />
                    <AlertTitle>
                        {t('billing.payment.failed', { default: 'Payment Failed' })}
                    </AlertTitle>
                    <AlertDescription>
                        {t('billing.payment.update_method', {
                            default: 'Please update your payment method to continue service.',
                        })}
                    </AlertDescription>
                </Alert>
            )}

            {isOnTrial && trialDaysRemaining !== null && trialDaysRemaining <= 7 && (
                <Alert
                    variant={trialDaysRemaining <= 3 ? 'destructive' : 'default'}
                    className={
                        trialDaysRemaining > 3
                            ? 'border-amber-500 bg-amber-50 dark:bg-amber-950/20'
                            : undefined
                    }
                >
                    <Clock className="h-4 w-4" />
                    <AlertTitle>
                        {trialDaysRemaining === 0
                            ? t('billing.trial.ends_today', { default: 'Trial ends today' })
                            : t('billing.trial.ending_soon', {
                                  default: 'Trial ending soon',
                              })}
                    </AlertTitle>
                    <AlertDescription>
                        {t('billing.trial.days_remaining', {
                            default: ':days days remaining in your trial',
                            days: trialDaysRemaining,
                        }).replace(':days', String(trialDaysRemaining))}
                    </AlertDescription>
                </Alert>
            )}

            {isCanceled && (
                <Alert>
                    <Clock className="h-4 w-4" />
                    <AlertTitle>
                        {t('billing.subscription.canceled', {
                            default: 'Subscription Canceled',
                        })}
                    </AlertTitle>
                    <AlertDescription>
                        {subscription?.endsAt
                            ? t('billing.addon.access_until', {
                                  default: 'You will have access until :date',
                                  date: formatDate(subscription.endsAt),
                              }).replace(':date', formatDate(subscription.endsAt))
                            : t('billing.subscription.will_not_renew', {
                                  default: 'Your subscription will not renew.',
                              })}
                    </AlertDescription>
                </Alert>
            )}

            {/* Main grid */}
            <div className="grid gap-6 md:grid-cols-2">
                {/* Plan card */}
                <Card>
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2">
                                <Crown className="h-5 w-5" />
                                {t('billing.plan.current', { default: 'Current Plan' })}
                            </CardTitle>
                            {getStatusBadge()}
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div>
                            <h3 className="text-2xl font-bold">{planName}</h3>
                            {plan?.description && (
                                <p className="text-muted-foreground text-sm">
                                    {plan.description}
                                </p>
                            )}
                        </div>

                        {nextInvoice && (
                            <div className="bg-muted/50 flex items-center justify-between rounded-lg p-3">
                                <div className="flex items-center gap-2">
                                    <Calendar className="text-muted-foreground h-4 w-4" />
                                    <span className="text-sm">
                                        {t('billing.invoice.next', { default: 'Next invoice' })}
                                    </span>
                                </div>
                                <div className="text-right">
                                    <p className="font-semibold">
                                        {nextInvoice.formattedAmount}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {formatDate(nextInvoice.date)}
                                    </p>
                                </div>
                            </div>
                        )}
                    </CardContent>
                    <CardFooter className="flex gap-2">
                        {onUpgrade && (
                            <Button onClick={onUpgrade}>
                                <ArrowUp className="mr-2 h-4 w-4" />
                                {t('billing.plan.upgrade', { default: 'Upgrade' })}
                            </Button>
                        )}
                        {onManageSubscription && (
                            <Button variant="outline" onClick={onManageSubscription}>
                                <ExternalLink className="mr-2 h-4 w-4" />
                                {t('billing.portal.title', { default: 'Billing Portal' })}
                            </Button>
                        )}
                    </CardFooter>
                </Card>

                {/* Cost breakdown */}
                {costs && <CostBreakdownWidget costs={costs} showDetails showPercentages />}
            </div>

            {/* Usage */}
            {usage && Object.keys(usage).length > 0 && (
                <UsageDashboard usage={usage} variant="grid" />
            )}

            {/* Active addons and bundles */}
            {(addons.length > 0 || bundles.length > 0) && (
                <Card>
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Puzzle className="h-4 w-4" />
                                {t('billing.addon.active', { default: 'Active Add-ons' })}
                            </CardTitle>
                            {onManageAddons && (
                                <Button variant="ghost" size="sm" onClick={onManageAddons}>
                                    {t('billing.subscription.manage', { default: 'Manage' })}
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {bundles.map((bundle) => (
                                <div
                                    key={bundle.id}
                                    className="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div className="flex items-center gap-2">
                                        <Package className="text-muted-foreground h-4 w-4" />
                                        <div>
                                            <p className="font-medium">{bundle.name}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {bundle.addonCount}{' '}
                                                {t('billing.addon.included', {
                                                    default: 'add-ons',
                                                })}
                                            </p>
                                        </div>
                                    </div>
                                    <span className="font-medium">
                                        {bundle.formattedPrice}
                                    </span>
                                </div>
                            ))}
                            {addons.map((addon) => (
                                <div
                                    key={addon.id}
                                    className="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div className="flex items-center gap-2">
                                        <Puzzle className="text-muted-foreground h-4 w-4" />
                                        <div>
                                            <p className="font-medium">{addon.name}</p>
                                            {addon.quantity > 1 && (
                                                <p className="text-muted-foreground text-xs">
                                                    x{addon.quantity}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    <span className="font-medium">
                                        {addon.formattedPrice}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Quick actions */}
            <div className="flex flex-wrap gap-2">
                {onViewInvoices && (
                    <Button variant="outline" onClick={onViewInvoices}>
                        <FileText className="mr-2 h-4 w-4" />
                        {t('billing.invoice.view_all', { default: 'View Invoices' })}
                    </Button>
                )}
                {onManageAddons && (
                    <Button variant="outline" onClick={onManageAddons}>
                        <Puzzle className="mr-2 h-4 w-4" />
                        {t('billing.addon.browse', { default: 'Browse Add-ons' })}
                    </Button>
                )}
            </div>
        </div>
    );
}

/**
 * SubscriptionOverviewSkeleton - Loading skeleton
 */
export function SubscriptionOverviewSkeleton({
    variant = 'full',
    className,
}: {
    variant?: 'full' | 'compact' | 'minimal';
    className?: string;
}) {
    if (variant === 'minimal') {
        return (
            <Card className={className}>
                <CardContent className="flex items-center justify-between p-4">
                    <div className="flex items-center gap-3">
                        <div className="bg-muted h-9 w-9 animate-pulse rounded-lg" />
                        <div>
                            <div className="bg-muted h-5 w-24 animate-pulse rounded" />
                            <div className="bg-muted mt-1 h-4 w-16 animate-pulse rounded" />
                        </div>
                    </div>
                    <div className="bg-muted h-5 w-16 animate-pulse rounded-full" />
                </CardContent>
            </Card>
        );
    }

    if (variant === 'compact') {
        return (
            <Card className={className}>
                <CardHeader className="pb-2">
                    <div className="flex items-center justify-between">
                        <div className="bg-muted h-5 w-32 animate-pulse rounded" />
                        <div className="bg-muted h-5 w-16 animate-pulse rounded-full" />
                    </div>
                </CardHeader>
                <CardContent className="space-y-3">
                    <div className="flex items-center justify-between">
                        <div className="bg-muted h-4 w-24 animate-pulse rounded" />
                        <div className="bg-muted h-5 w-20 animate-pulse rounded" />
                    </div>
                    <div className="flex items-center justify-between">
                        <div className="bg-muted h-4 w-20 animate-pulse rounded" />
                        <div className="bg-muted h-4 w-24 animate-pulse rounded" />
                    </div>
                </CardContent>
                <CardFooter className="flex gap-2 pt-0">
                    <div className="bg-muted h-8 w-20 animate-pulse rounded" />
                    <div className="bg-muted h-8 w-20 animate-pulse rounded" />
                </CardFooter>
            </Card>
        );
    }

    return (
        <div className={cn('space-y-6', className)}>
            <div className="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader className="pb-2">
                        <div className="bg-muted h-6 w-32 animate-pulse rounded" />
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="bg-muted h-8 w-40 animate-pulse rounded" />
                        <div className="bg-muted h-16 animate-pulse rounded-lg" />
                    </CardContent>
                    <CardFooter className="flex gap-2">
                        <div className="bg-muted h-10 w-24 animate-pulse rounded" />
                        <div className="bg-muted h-10 w-32 animate-pulse rounded" />
                    </CardFooter>
                </Card>
                <Card>
                    <CardHeader className="pb-2">
                        <div className="bg-muted h-5 w-28 animate-pulse rounded" />
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <div key={i} className="flex items-center justify-between">
                                <div className="bg-muted h-4 w-20 animate-pulse rounded" />
                                <div className="bg-muted h-4 w-16 animate-pulse rounded" />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
