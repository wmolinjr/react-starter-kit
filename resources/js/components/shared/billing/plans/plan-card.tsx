import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Check, X, ArrowUp, Crown } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Loader2 } from 'lucide-react';
import { PriceDisplay } from '../primitives/price-display';
import type { PlanResource } from '@/types/resources';
import type { PlanProduct, FeatureItem, BillingPricing } from '@/types/billing';
import type { BillingPeriod } from '@/types/enums';

export interface PlanCardProps {
    /** Plan data from API or transformed PlanProduct */
    plan: PlanResource | PlanProduct;
    /** Current billing period selection */
    billingPeriod: BillingPeriod;
    /** Slug of the current user's plan */
    currentPlanSlug?: string;
    /** Callback when user clicks to select/upgrade/downgrade */
    onSelect?: (planSlug: string) => void;
    /** Whether the action is loading */
    isLoading?: boolean;
    /** Custom CTA label override */
    ctaLabel?: string;
    /** Whether to show features list */
    showFeatures?: boolean;
    /** Max features to show before "show more" */
    maxFeatures?: number;
    /** Additional className */
    className?: string;
}

/**
 * PlanCard - Displays a plan with pricing and features
 *
 * Automatically handles:
 * - Current plan detection
 * - Upgrade/downgrade indicators
 * - Badge display from plan data
 * - Price display based on billing period
 *
 * @example
 * // Basic usage
 * <PlanCard
 *     plan={plan}
 *     billingPeriod="monthly"
 *     currentPlanSlug="starter"
 *     onSelect={(slug) => handlePlanChange(slug)}
 * />
 *
 * @example
 * // In a grid
 * <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
 *     {plans.map((plan) => (
 *         <PlanCard
 *             key={plan.slug}
 *             plan={plan}
 *             billingPeriod={period}
 *             currentPlanSlug={currentPlan.slug}
 *             onSelect={handleSelect}
 *         />
 *     ))}
 * </div>
 */
export function PlanCard({
    plan,
    billingPeriod,
    currentPlanSlug,
    onSelect,
    isLoading = false,
    ctaLabel,
    showFeatures = true,
    maxFeatures = 6,
    className,
}: PlanCardProps) {
    const { t } = useLaravelReactI18n();

    // Determine plan state
    const isCurrent = currentPlanSlug === plan.slug;
    const isHighlighted = 'is_featured' in plan ? plan.is_featured : false;

    // Get plan name (handle both PlanResource and PlanProduct)
    const planName = typeof plan.name === 'string' ? plan.name : plan.name;

    // Get price for current billing period
    const getPrice = (): { amount: number; formattedAmount?: string } => {
        // PlanProduct has pricing object with periods
        if ('pricing' in plan && plan.pricing) {
            const pricing = plan.pricing as BillingPricing;
            if (billingPeriod === 'yearly' && pricing.yearly) {
                return {
                    amount: pricing.yearly.price,
                    formattedAmount: pricing.yearly.formattedPrice,
                };
            }
            if (pricing.monthly) {
                return {
                    amount: pricing.monthly.price,
                    formattedAmount: pricing.monthly.formattedPrice,
                };
            }
        }

        // PlanResource has flat price
        if ('price' in plan) {
            return {
                amount: plan.price,
                formattedAmount: 'formatted_price' in plan ? plan.formatted_price : undefined,
            };
        }

        // Fallback
        return { amount: 0 };
    };

    const price = getPrice();

    // Get badge configuration
    const getBadge = (): { text: string; variant: string } | null => {
        if (!plan.badge) return null;

        // Handle PricingBadge object (from BillingProduct)
        if (typeof plan.badge === 'object' && 'text' in plan.badge) {
            return {
                text: plan.badge.text,
                variant: plan.badge.variant || 'default',
            };
        }

        // Handle string badge (BadgePreset from PlanResource)
        const badgeKey = plan.badge as string;
        const badgeMap: Record<string, { text: string; variant: string }> = {
            most_popular: {
                text: t('enums.badge.preset.most_popular', { default: 'Most Popular' }),
                variant: 'popular',
            },
            best_value: {
                text: t('enums.badge.preset.best_value', { default: 'Best Value' }),
                variant: 'best-value',
            },
            best_for_teams: {
                text: t('enums.badge.preset.best_for_teams', { default: 'Best for Teams' }),
                variant: 'recommended',
            },
            enterprise: {
                text: t('enums.badge.preset.enterprise', { default: 'Enterprise' }),
                variant: 'default',
            },
            recommended: {
                text: t('enums.badge.preset.recommended', { default: 'Recommended' }),
                variant: 'recommended',
            },
            new: { text: t('enums.badge.preset.new', { default: 'New' }), variant: 'new' },
            pro: { text: t('enums.badge.preset.pro', { default: 'Pro' }), variant: 'popular' },
            starter: {
                text: t('enums.badge.preset.starter', { default: 'Starter' }),
                variant: 'default',
            },
        };

        return badgeMap[badgeKey] || { text: badgeKey, variant: 'default' };
    };

    const badge = getBadge();

    // Build features list from plan limits and features
    const getFeatures = (): FeatureItem[] => {
        const features: FeatureItem[] = [];

        // Add limits as features
        if (plan.limits) {
            const limitLabels: Record<string, string> = {
                users: t('enums.plan.limit.users', { default: 'User Seats' }),
                projects: t('enums.plan.limit.projects', { default: 'Projects' }),
                storage: t('enums.plan.limit.storage', { default: 'Storage' }),
                apiCalls: t('enums.plan.limit.apiCalls', { default: 'API Calls' }),
                customRoles: t('enums.plan.limit.customRoles', { default: 'Custom Roles' }),
                locales: t('enums.plan.limit.locales', { default: 'Languages' }),
            };

            const displayOrder = ['users', 'projects', 'storage', 'apiCalls', 'customRoles', 'locales'];

            for (const key of displayOrder) {
                if (key in plan.limits) {
                    const value = plan.limits[key as keyof typeof plan.limits];
                    if (value !== null && value !== undefined) {
                        const label = limitLabels[key] || key;
                        const displayValue =
                            value === -1
                                ? t('billing.unlimited', { default: 'Unlimited' })
                                : key === 'storage'
                                  ? `${value} GB`
                                  : value.toString();

                        features.push({
                            text: `${displayValue} ${label}`,
                            included: true,
                            limit: value === -1 ? 'Unlimited' : value,
                        });
                    }
                }
            }
        }

        // Add boolean features
        if ('features' in plan && plan.features) {
            // PlanProduct has features array
            if (Array.isArray(plan.features)) {
                for (const feature of plan.features) {
                    features.push({ text: feature, included: true });
                }
            } else {
                // PlanResource has PlanFeatures object
                const featureLabels: Record<string, string> = {
                    customRoles: t('enums.plan.feature.customRoles', { default: 'Custom Roles' }),
                    apiAccess: t('enums.plan.feature.apiAccess', { default: 'API Access' }),
                    advancedReports: t('enums.plan.feature.advancedReports', {
                        default: 'Advanced Reports',
                    }),
                    sso: t('enums.plan.feature.sso', { default: 'Single Sign-On (SSO)' }),
                    whiteLabel: t('enums.plan.feature.whiteLabel', { default: 'White Label' }),
                    auditLog: t('enums.plan.feature.auditLog', { default: 'Audit Log' }),
                    prioritySupport: t('enums.plan.feature.prioritySupport', {
                        default: 'Priority Support',
                    }),
                    multiLanguage: t('enums.plan.feature.multiLanguage', {
                        default: 'Multi-Language',
                    }),
                    federation: t('enums.plan.feature.federation', { default: 'User Federation' }),
                };

                for (const [key, value] of Object.entries(plan.features)) {
                    if (key !== 'projects' && featureLabels[key]) {
                        features.push({
                            text: featureLabels[key],
                            included: value === true,
                        });
                    }
                }
            }
        }

        return features;
    };

    const features = showFeatures ? getFeatures() : [];
    const visibleFeatures = features.slice(0, maxFeatures);
    const hasMoreFeatures = features.length > maxFeatures;

    // Get CTA configuration
    const getCtaConfig = () => {
        if (isCurrent) {
            return {
                label:
                    ctaLabel || t('billing.current_plan', { default: 'Current Plan' }),
                variant: 'outline' as const,
                disabled: true,
                icon: <Crown className="mr-2 h-4 w-4" />,
            };
        }

        // Determine if upgrade or downgrade based on price
        const isUpgrade =
            'isUpgrade' in plan
                ? plan.isUpgrade
                : currentPlanSlug
                  ? price.amount > 0
                  : false;

        if (isUpgrade) {
            return {
                label: ctaLabel || t('billing.upgrade', { default: 'Upgrade' }),
                variant: 'default' as const,
                disabled: false,
                icon: <ArrowUp className="mr-2 h-4 w-4" />,
            };
        }

        return {
            label:
                ctaLabel || t('billing.select_plan', { default: 'Select Plan' }),
            variant: (isHighlighted ? 'default' : 'outline') as 'default' | 'outline',
            disabled: false,
            icon: null,
        };
    };

    const ctaConfig = getCtaConfig();

    const badgeVariantClasses: Record<string, string> = {
        default: '',
        popular: 'bg-primary text-primary-foreground',
        new: 'bg-blue-500 text-white',
        recommended: 'bg-green-500 text-white',
        'best-value': 'bg-amber-500 text-white',
    };

    return (
        <Card
            className={cn(
                'relative flex flex-col',
                isHighlighted && 'border-primary shadow-lg ring-1 ring-primary',
                isCurrent && 'border-primary/50 bg-primary/5',
                className
            )}
        >
            {badge && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                    <Badge
                        className={cn(
                            'px-3 py-1 text-xs font-semibold',
                            badgeVariantClasses[badge.variant]
                        )}
                    >
                        {badge.text}
                    </Badge>
                </div>
            )}

            <CardHeader className={cn(badge && 'pt-6')}>
                <CardTitle className="text-xl">{planName}</CardTitle>
                {plan.description && (
                    <CardDescription>{plan.description}</CardDescription>
                )}
            </CardHeader>

            <CardContent className="flex-1 space-y-6">
                {/* Price */}
                <div>
                    <PriceDisplay
                        amount={price.amount}
                        currency={'currency' in plan ? plan.currency : 'USD'}
                        period={billingPeriod === 'yearly' ? 'yearly' : 'monthly'}
                        size="lg"
                    />

                    {billingPeriod === 'yearly' && price.amount > 0 && (
                        <p className="text-muted-foreground mt-1 text-sm">
                            {t('billing.billed_yearly', { default: 'Billed yearly' })}
                        </p>
                    )}
                </div>

                {/* Features */}
                {visibleFeatures.length > 0 && (
                    <ul className="space-y-2">
                        {visibleFeatures.map((feature, index) => (
                            <li key={index} className="flex items-start gap-2 text-sm">
                                {feature.included ? (
                                    <Check className="text-primary mt-0.5 h-4 w-4 shrink-0" />
                                ) : (
                                    <X className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                                )}
                                <span
                                    className={cn(
                                        !feature.included && 'text-muted-foreground line-through'
                                    )}
                                >
                                    {feature.text}
                                </span>
                            </li>
                        ))}
                        {hasMoreFeatures && (
                            <li className="text-muted-foreground text-sm">
                                +{features.length - maxFeatures}{' '}
                                {t('billing.more_features', { default: 'more features' })}
                            </li>
                        )}
                    </ul>
                )}
            </CardContent>

            <CardFooter className="pt-4">
                <Button
                    onClick={() => onSelect?.(plan.slug)}
                    variant={ctaConfig.variant}
                    disabled={ctaConfig.disabled || isLoading}
                    className="w-full"
                >
                    {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                    {!isLoading && ctaConfig.icon}
                    {ctaConfig.label}
                </Button>
            </CardFooter>
        </Card>
    );
}

/**
 * PlanCardSkeleton - Loading skeleton for plan card
 */
export function PlanCardSkeleton({ className }: { className?: string }) {
    return (
        <Card className={cn('flex flex-col', className)}>
            <CardHeader>
                <div className="bg-muted h-6 w-24 animate-pulse rounded" />
                <div className="bg-muted mt-2 h-4 w-32 animate-pulse rounded" />
            </CardHeader>

            <CardContent className="flex-1 space-y-6">
                <div>
                    <div className="bg-muted h-10 w-28 animate-pulse rounded" />
                    <div className="bg-muted mt-1 h-4 w-20 animate-pulse rounded" />
                </div>

                <div className="space-y-2">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <div key={i} className="flex items-center gap-2">
                            <div className="bg-muted h-4 w-4 animate-pulse rounded" />
                            <div className="bg-muted h-4 flex-1 animate-pulse rounded" />
                        </div>
                    ))}
                </div>
            </CardContent>

            <CardFooter>
                <div className="bg-muted h-10 w-full animate-pulse rounded" />
            </CardFooter>
        </Card>
    );
}
