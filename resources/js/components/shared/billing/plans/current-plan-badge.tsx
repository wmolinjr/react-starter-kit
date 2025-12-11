import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Crown, Calendar, AlertTriangle, Clock, ArrowUp, Settings } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { PriceDisplay } from '../primitives/price-display';
import type { PlanResource } from '@/types/resources';
import type { SubscriptionInfo } from '@/types/billing';
import type { BillingPeriod } from '@/types/enums';

export interface CurrentPlanBadgeProps {
    /** Current plan data */
    plan: PlanResource;
    /** Billing period (monthly/yearly) */
    billingPeriod?: BillingPeriod;
    /** Whether to show compact version */
    compact?: boolean;
    /** Additional className */
    className?: string;
}

/**
 * CurrentPlanBadge - Simple badge showing current plan name
 *
 * @example
 * <CurrentPlanBadge plan={plan} />
 *
 * @example
 * <CurrentPlanBadge plan={plan} compact />
 */
export function CurrentPlanBadge({
    plan,
    billingPeriod = 'monthly',
    compact = false,
    className,
}: CurrentPlanBadgeProps) {
    if (compact) {
        return (
            <Badge variant="secondary" className={cn('gap-1', className)}>
                <Crown className="h-3 w-3" />
                {plan.name}
            </Badge>
        );
    }

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <Badge variant="outline" className="gap-1.5 px-3 py-1">
                <Crown className="text-primary h-4 w-4" />
                <span className="font-medium">{plan.name}</span>
            </Badge>
            <PriceDisplay
                amount={plan.price}
                currency={plan.currency}
                period={billingPeriod === 'yearly' ? 'yearly' : 'monthly'}
                size="sm"
            />
        </div>
    );
}

export interface CurrentPlanBannerProps {
    /** Current plan data */
    plan: PlanResource;
    /** Subscription information */
    subscription?: SubscriptionInfo | null;
    /** Billing period */
    billingPeriod?: BillingPeriod;
    /** Trial end date (ISO string) */
    trialEndsAt?: string | null;
    /** Callback to manage subscription */
    onManage?: () => void;
    /** Callback to upgrade plan */
    onUpgrade?: () => void;
    /** Additional className */
    className?: string;
}

/**
 * CurrentPlanBanner - Full banner showing current plan with status
 *
 * Displays:
 * - Plan name and price
 * - Subscription status (active, trial, canceled)
 * - Next billing date
 * - Quick actions (manage, upgrade)
 *
 * @example
 * <CurrentPlanBanner
 *     plan={plan}
 *     subscription={subscription}
 *     onManage={() => router.visit('/billing/manage')}
 *     onUpgrade={() => router.visit('/billing/upgrade')}
 * />
 */
export function CurrentPlanBanner({
    plan,
    subscription,
    billingPeriod = 'monthly',
    trialEndsAt,
    onManage,
    onUpgrade,
    className,
}: CurrentPlanBannerProps) {
    const { t } = useLaravelReactI18n();

    // Determine subscription status
    const isOnTrial = !!trialEndsAt || subscription?.status === 'trialing';
    const isCanceled = subscription?.cancelAtPeriodEnd || subscription?.status === 'canceled';
    const isPastDue = subscription?.status === 'past_due';

    // Format dates
    const formatDate = (dateStr: string | null | undefined): string => {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    const nextBillingDate = subscription?.currentPeriodEnd
        ? formatDate(subscription.currentPeriodEnd)
        : null;

    const trialEndDate = trialEndsAt ? formatDate(trialEndsAt) : null;

    // Calculate days remaining in trial
    const getTrialDaysRemaining = (): number | null => {
        if (!trialEndsAt) return null;
        const now = new Date();
        const end = new Date(trialEndsAt);
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
                </Badge>
            );
        }

        return (
            <Badge variant="outline" className="gap-1 border-green-500 text-green-600">
                <Crown className="h-3 w-3" />
                {t('billing.status.active', { default: 'Active' })}
            </Badge>
        );
    };

    return (
        <Card className={className}>
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <CardTitle className="text-xl">{plan.name}</CardTitle>
                            {getStatusBadge()}
                        </div>
                        {plan.description && (
                            <CardDescription>{plan.description}</CardDescription>
                        )}
                    </div>
                    <PriceDisplay
                        amount={plan.price}
                        currency={plan.currency}
                        period={billingPeriod === 'yearly' ? 'yearly' : 'monthly'}
                        size="lg"
                    />
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                {/* Trial warning */}
                {isOnTrial && trialDaysRemaining !== null && (
                    <Alert variant={trialDaysRemaining <= 3 ? 'destructive' : 'default'}>
                        <Clock className="h-4 w-4" />
                        <AlertTitle>
                            {trialDaysRemaining === 0
                                ? t('billing.trial.ends_today', { default: 'Trial ends today' })
                                : t('billing.trial.ends_in', {
                                      default: ':days days left in trial',
                                      days: trialDaysRemaining,
                                  }).replace(':days', String(trialDaysRemaining))}
                        </AlertTitle>
                        <AlertDescription>
                            {trialEndDate &&
                                t('billing.trial.ends_on', {
                                    default: 'Your trial ends on :date',
                                    date: trialEndDate,
                                }).replace(':date', trialEndDate)}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Canceled warning */}
                {isCanceled && subscription?.endsAt && (
                    <Alert variant="destructive">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertTitle>
                            {t('billing.subscription.canceled', {
                                default: 'Subscription canceled',
                            })}
                        </AlertTitle>
                        <AlertDescription>
                            {t('billing.addon.access_until', {
                                default: 'You will have access until :date',
                                date: formatDate(subscription.endsAt),
                            }).replace(':date', formatDate(subscription.endsAt))}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Past due warning */}
                {isPastDue && (
                    <Alert variant="destructive">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertTitle>
                            {t('billing.payment.failed', { default: 'Payment failed' })}
                        </AlertTitle>
                        <AlertDescription>
                            {t('billing.payment.update', {
                                default: 'Please update your payment method to continue service.',
                            })}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Next billing info */}
                {!isCanceled && nextBillingDate && (
                    <div className="text-muted-foreground flex items-center gap-2 text-sm">
                        <Calendar className="h-4 w-4" />
                        <span>
                            {t('billing.subscription.next_billing', {
                                default: 'Next billing: :date',
                                date: nextBillingDate,
                            }).replace(':date', nextBillingDate)}
                        </span>
                    </div>
                )}

                {/* Actions */}
                <div className="flex flex-wrap gap-2">
                    {onManage && (
                        <Button variant="outline" size="sm" onClick={onManage}>
                            <Settings className="mr-2 h-4 w-4" />
                            {t('billing.subscription.manage', { default: 'Manage Subscription' })}
                        </Button>
                    )}
                    {onUpgrade && (
                        <Button size="sm" onClick={onUpgrade}>
                            <ArrowUp className="mr-2 h-4 w-4" />
                            {t('billing.plan.upgrade', { default: 'Upgrade' })}
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

/**
 * CurrentPlanBannerSkeleton - Loading skeleton
 */
export function CurrentPlanBannerSkeleton({ className }: { className?: string }) {
    return (
        <Card className={className}>
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div className="space-y-2">
                        <div className="flex items-center gap-2">
                            <div className="bg-muted h-6 w-32 animate-pulse rounded" />
                            <div className="bg-muted h-5 w-16 animate-pulse rounded" />
                        </div>
                        <div className="bg-muted h-4 w-48 animate-pulse rounded" />
                    </div>
                    <div className="bg-muted h-10 w-24 animate-pulse rounded" />
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="bg-muted h-4 w-40 animate-pulse rounded" />
                <div className="flex gap-2">
                    <div className="bg-muted h-9 w-36 animate-pulse rounded" />
                    <div className="bg-muted h-9 w-24 animate-pulse rounded" />
                </div>
            </CardContent>
        </Card>
    );
}
