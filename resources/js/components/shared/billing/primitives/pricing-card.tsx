import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
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
import { PriceDisplay } from './price-display';
import { FeatureList } from './feature-list';
import type { PricingCardProps, PricingBadge } from '@/types/billing';

/**
 * PricingCard - Generic pricing card for plans, addons, or bundles
 *
 * @example
 * // Basic plan card
 * <PricingCard
 *     title="Professional"
 *     description="For growing teams"
 *     badge={{ text: "Popular", variant: "popular" }}
 *     price={{ amount: 2900, currency: "USD", period: "monthly" }}
 *     features={[
 *         { text: "25 users", included: true },
 *         { text: "50 GB storage", included: true },
 *         { text: "API Access", included: true },
 *     ]}
 *     cta={{ label: "Get Started", onClick: () => {} }}
 *     highlighted
 * />
 *
 * @example
 * // Current plan card
 * <PricingCard
 *     title="Starter"
 *     price={{ amount: 900, currency: "USD", period: "monthly" }}
 *     features={features}
 *     cta={{ label: "Current Plan", onClick: () => {}, disabled: true }}
 *     current
 * />
 *
 * @example
 * // With discount
 * <PricingCard
 *     title="Enterprise"
 *     price={{
 *         amount: 7920,
 *         originalAmount: 9900,
 *         currency: "USD",
 *         period: "yearly"
 *     }}
 *     features={features}
 *     cta={{ label: "Upgrade", onClick: handleUpgrade }}
 * />
 */
export function PricingCard({
    title,
    description,
    badge,
    price,
    features,
    cta,
    highlighted = false,
    current = false,
    className,
}: PricingCardProps) {
    const { t } = useLaravelReactI18n();

    const getBadgeConfig = (): PricingBadge | null => {
        if (!badge) return null;

        if (typeof badge === 'string') {
            return { text: badge, variant: 'default' };
        }

        return badge;
    };

    const badgeConfig = getBadgeConfig();

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
                highlighted && 'border-primary shadow-lg ring-1 ring-primary',
                current && 'border-primary/50 bg-primary/5',
                className
            )}
        >
            {badgeConfig && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                    <Badge
                        className={cn(
                            'px-3 py-1 text-xs font-semibold',
                            badgeVariantClasses[badgeConfig.variant]
                        )}
                    >
                        {badgeConfig.text}
                    </Badge>
                </div>
            )}

            <CardHeader className={cn(badgeConfig && 'pt-6')}>
                <CardTitle className="text-xl">{title}</CardTitle>
                {description && (
                    <CardDescription>{description}</CardDescription>
                )}
            </CardHeader>

            <CardContent className="flex-1 space-y-6">
                {/* Price */}
                <div>
                    <PriceDisplay
                        amount={price.amount}
                        currency={price.currency}
                        period={price.period}
                        originalAmount={price.originalAmount}
                        size="lg"
                    />

                    {price.period === 'yearly' && price.amount > 0 && (
                        <p className="text-muted-foreground mt-1 text-sm">
                            {t('billing.subscription.billed_yearly', {
                                default: 'Billed yearly',
                            })}
                        </p>
                    )}
                </div>

                {/* Features */}
                {features.length > 0 && (
                    <FeatureList
                        features={features}
                        variant="compact"
                        maxVisible={6}
                        showMore
                    />
                )}
            </CardContent>

            <CardFooter className="pt-4">
                <Button
                    onClick={cta.onClick}
                    variant={cta.variant || (highlighted ? 'default' : 'outline')}
                    disabled={cta.disabled || cta.loading}
                    className="w-full"
                >
                    {cta.loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                    {cta.label}
                </Button>
            </CardFooter>
        </Card>
    );
}

/**
 * PricingCardSkeleton - Loading skeleton for pricing card
 */
export function PricingCardSkeleton({ className }: { className?: string }) {
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
