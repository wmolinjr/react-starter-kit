import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    Package,
    Check,
    ShoppingCart,
    AlertTriangle,
    Loader2,
    Plus,
} from 'lucide-react';
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
import { Alert, AlertDescription } from '@/components/ui/alert';
import { PriceDisplay } from '../primitives/price-display';
import { BundleContents } from './bundle-contents';
import { BundleSavings, BundleSavingsCompact } from './bundle-savings';
import type { BundleProduct } from '@/types/billing';
import type { BundleResource } from '@/types/resources';
import type { BillingPeriod } from '@/types/enums';

export interface BundleCardProps {
    /** Bundle data */
    bundle: BundleResource | BundleProduct;
    /** Current billing period */
    billingPeriod: BillingPeriod;
    /** Callback when purchasing (direct checkout) */
    onPurchase?: () => void;
    /** Callback when adding to cart */
    onAddToCart?: () => void;
    /** Whether the bundle is already purchased */
    isPurchased?: boolean;
    /** Whether the bundle is already in cart */
    isInCart?: boolean;
    /** Whether the user has conflicting addons (already owns some addons in bundle) */
    hasConflicts?: boolean;
    /** Conflict message to display */
    conflictMessage?: string;
    /** Whether the action is loading */
    isLoading?: boolean;
    /** Whether the card is disabled */
    disabled?: boolean;
    /** Show detailed addon list */
    showAddonDetails?: boolean;
    /** Maximum addons to show in list */
    maxAddons?: number;
    /** Additional className */
    className?: string;
}

/**
 * BundleCard - Displays a bundle with addons and pricing
 *
 * @example
 * <BundleCard
 *     bundle={bundle}
 *     billingPeriod="monthly"
 *     onPurchase={() => handlePurchase(bundle.slug)}
 * />
 *
 * @example
 * // With conflict warning
 * <BundleCard
 *     bundle={bundle}
 *     billingPeriod="monthly"
 *     hasConflicts
 *     conflictMessage="You already own 2 addons in this bundle"
 *     onPurchase={handlePurchase}
 * />
 */
export function BundleCard({
    bundle,
    billingPeriod,
    onPurchase,
    onAddToCart,
    isPurchased: propIsPurchased,
    isInCart = false,
    hasConflicts = false,
    conflictMessage,
    isLoading = false,
    disabled = false,
    showAddonDetails = true,
    maxAddons = 4,
    className,
}: BundleCardProps) {
    const { t } = useLaravelReactI18n();

    // Determine if purchased (from prop or bundle data)
    const isPurchased = propIsPurchased ?? ('isPurchased' in bundle && bundle.isPurchased);

    // Get bundle name
    const bundleName =
        'name_display' in bundle
            ? bundle.name_display
            : typeof bundle.name === 'string'
              ? bundle.name
              : bundle.name;

    // Get bundle description
    const getBundleDescription = (): string | null => {
        if ('description' in bundle) {
            if (typeof bundle.description === 'string') {
                return bundle.description;
            }
            // Translations object - get first available
            if (bundle.description && typeof bundle.description === 'object') {
                const desc = bundle.description as Record<string, string>;
                return desc.en || desc.es || Object.values(desc)[0] || null;
            }
        }
        return null;
    };

    // Get price for current billing period
    const getPrice = (): { amount: number; originalAmount?: number } => {
        if ('pricing' in bundle && bundle.pricing) {
            // BundleProduct
            const pricing = bundle.pricing;
            if (billingPeriod === 'yearly' && pricing.yearly) {
                return {
                    amount: pricing.yearly.price,
                    originalAmount: bundle.basePriceYearly,
                };
            }
            if (pricing.monthly) {
                return {
                    amount: pricing.monthly.price,
                    originalAmount: bundle.basePriceMonthly,
                };
            }
        }

        // BundleResource
        if ('price_monthly_effective' in bundle) {
            if (billingPeriod === 'yearly') {
                return {
                    amount: bundle.price_yearly_effective,
                    originalAmount: bundle.base_price_monthly * 12, // Approximate yearly base
                };
            }
            return {
                amount: bundle.price_monthly_effective,
                originalAmount: bundle.base_price_monthly,
            };
        }

        return { amount: 0 };
    };

    const price = getPrice();

    // Get savings
    const getSavings = (): { amount: number; percent: number } => {
        if ('savingsMonthly' in bundle) {
            // BundleProduct
            return {
                amount:
                    billingPeriod === 'yearly' ? bundle.savingsYearly : bundle.savingsMonthly,
                percent: bundle.discountPercent,
            };
        }

        if ('savings_monthly' in bundle) {
            // BundleResource
            return {
                amount: bundle.savings_monthly,
                percent: bundle.discount_percent,
            };
        }

        return { amount: 0, percent: 0 };
    };

    const savings = getSavings();

    // Get badge configuration
    const getBadge = (): { text: string; variant: string } | null => {
        const badgeValue = bundle.badge;
        if (!badgeValue) return null;

        if (typeof badgeValue === 'object' && 'text' in badgeValue) {
            return {
                text: badgeValue.text,
                variant: badgeValue.variant || 'default',
            };
        }

        const badgeKey = badgeValue as string;
        const badgeMap: Record<string, { text: string; variant: string }> = {
            best_value: {
                text: t('billing.badge.best_value', { default: 'Best Value' }),
                variant: 'best-value',
            },
            most_popular: {
                text: t('billing.badge.most_popular', { default: 'Most Popular' }),
                variant: 'popular',
            },
            recommended: {
                text: t('billing.badge.recommended', { default: 'Recommended' }),
                variant: 'recommended',
            },
            new: { text: t('billing.badge.new', { default: 'New' }), variant: 'new' },
            limited_time: {
                text: t('billing.badge.limited_time', { default: 'Limited Time' }),
                variant: 'destructive',
            },
        };

        return badgeMap[badgeKey] || { text: badgeKey, variant: 'default' };
    };

    const badge = getBadge();

    // Get addon count
    const addonCount = 'addon_count' in bundle ? bundle.addon_count : bundle.addonCount;

    // Get addons list
    const addons = bundle.addons || [];

    const badgeVariantClasses: Record<string, string> = {
        default: '',
        popular: 'bg-primary text-primary-foreground',
        new: 'bg-blue-500 text-white',
        recommended: 'bg-green-500 text-white',
        'best-value': 'bg-amber-500 text-white',
        destructive: 'bg-red-500 text-white',
    };

    return (
        <Card
            className={cn(
                'relative flex flex-col',
                isPurchased && 'border-green-500/50 bg-green-50/50 dark:bg-green-950/20',
                hasConflicts && !isPurchased && 'border-amber-500/50',
                className
            )}
        >
            {/* Badge */}
            {badge && !isPurchased && (
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

            {/* Purchased badge */}
            {isPurchased && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                    <Badge className="gap-1 bg-green-500 px-3 py-1 text-xs font-semibold text-white">
                        <Check className="h-3 w-3" />
                        {t('billing.purchased', { default: 'Purchased' })}
                    </Badge>
                </div>
            )}

            <CardHeader className={cn((badge || isPurchased) && 'pt-6')}>
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-2">
                        <div className="bg-primary/10 text-primary rounded-lg p-2">
                            <Package className="h-5 w-5" />
                        </div>
                        <div>
                            <CardTitle className="text-lg">{bundleName}</CardTitle>
                            <p className="text-muted-foreground text-xs">
                                {addonCount}{' '}
                                {t('billing.addons_included', { default: 'add-ons included' })}
                            </p>
                        </div>
                    </div>
                    {savings.percent > 0 && (
                        <BundleSavingsCompact percent={savings.percent} size="sm" />
                    )}
                </div>
                {getBundleDescription() && (
                    <CardDescription className="mt-2">
                        {getBundleDescription()}
                    </CardDescription>
                )}
            </CardHeader>

            <CardContent className="flex-1 space-y-4">
                {/* Price */}
                <div className="flex items-baseline gap-2">
                    <PriceDisplay
                        amount={price.amount}
                        currency="USD"
                        period={billingPeriod === 'yearly' ? 'yearly' : 'monthly'}
                        originalAmount={price.originalAmount}
                        size="lg"
                    />
                </div>

                {/* Conflict warning */}
                {hasConflicts && !isPurchased && (
                    <Alert variant="default" className="border-amber-500 bg-amber-50 dark:bg-amber-950/20">
                        <AlertTriangle className="h-4 w-4 text-amber-500" />
                        <AlertDescription className="text-amber-700 dark:text-amber-400">
                            {conflictMessage ||
                                t('billing.bundle_conflict', {
                                    default:
                                        'You already own some add-ons in this bundle. They will be replaced.',
                                })}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Addon list */}
                {showAddonDetails && addons.length > 0 && (
                    <div>
                        <p className="text-muted-foreground mb-2 text-xs font-medium uppercase">
                            {t('billing.whats_included', { default: "What's included" })}
                        </p>
                        <BundleContents
                            addons={addons}
                            variant="list"
                            maxItems={maxAddons}
                            showValues
                        />
                    </div>
                )}

                {/* Savings detail */}
                {savings.amount > 0 && !isPurchased && (
                    <BundleSavings
                        individualPrice={price.originalAmount || 0}
                        bundlePrice={price.amount}
                        period={billingPeriod === 'yearly' ? 'yearly' : 'monthly'}
                        variant="inline"
                        size="sm"
                    />
                )}
            </CardContent>

            <CardFooter className="flex gap-2 pt-4">
                {isPurchased ? (
                    <Button variant="outline" disabled className="w-full">
                        <Check className="mr-2 h-4 w-4" />
                        {t('billing.already_purchased', { default: 'Already Purchased' })}
                    </Button>
                ) : (
                    <>
                        {/* Add to Cart button */}
                        {onAddToCart && (
                            <Button
                                className="flex-1"
                                variant={isInCart ? 'secondary' : 'outline'}
                                onClick={onAddToCart}
                                disabled={disabled || isLoading}
                            >
                                {isInCart ? (
                                    <>
                                        <Check className="mr-2 h-4 w-4" />
                                        {t('billing.in_cart', { default: 'In Cart' })}
                                    </>
                                ) : (
                                    <>
                                        <Plus className="mr-2 h-4 w-4" />
                                        {t('billing.add_to_cart', { default: 'Add to Cart' })}
                                    </>
                                )}
                            </Button>
                        )}

                        {/* Direct Purchase button */}
                        {onPurchase && (
                            <Button
                                onClick={onPurchase}
                                disabled={disabled || isLoading}
                                variant={hasConflicts ? 'outline' : 'default'}
                                className={onAddToCart ? 'flex-1' : 'w-full'}
                            >
                                {isLoading ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <ShoppingCart className="mr-2 h-4 w-4" />
                                )}
                                {hasConflicts
                                    ? t('billing.replace_and_purchase', {
                                          default: 'Replace & Purchase',
                                      })
                                    : t('billing.purchase', { default: 'Purchase' })}
                            </Button>
                        )}
                    </>
                )}
            </CardFooter>
        </Card>
    );
}

/**
 * BundleCardSkeleton - Loading skeleton
 */
export function BundleCardSkeleton({ className }: { className?: string }) {
    return (
        <Card className={cn('flex flex-col', className)}>
            <CardHeader>
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-2">
                        <div className="bg-muted h-9 w-9 animate-pulse rounded-lg" />
                        <div>
                            <div className="bg-muted h-5 w-32 animate-pulse rounded" />
                            <div className="bg-muted mt-1 h-3 w-24 animate-pulse rounded" />
                        </div>
                    </div>
                    <div className="bg-muted h-5 w-12 animate-pulse rounded-full" />
                </div>
            </CardHeader>

            <CardContent className="flex-1 space-y-4">
                <div className="bg-muted h-10 w-28 animate-pulse rounded" />

                <div className="space-y-2">
                    {Array.from({ length: 3 }).map((_, i) => (
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
