import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    Puzzle,
    Check,
    ShoppingCart,
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
import { PriceDisplay } from '../primitives/price-display';
import { FeatureList } from '../primitives/feature-list';
import type { AddonResource } from '@/types/resources';
import type { BillingPeriod } from '@/types/enums';

export interface AddonCardProps {
    /** Addon data */
    addon: AddonResource;
    /** Current billing period */
    billingPeriod: BillingPeriod;
    /** Callback when purchasing (direct checkout) */
    onPurchase?: (slug: string, quantity: number) => void;
    /** Callback when adding to cart */
    onAddToCart?: (slug: string, quantity: number) => void;
    /** Whether the addon is already purchased */
    isPurchased?: boolean;
    /** Whether the addon is already in cart */
    isInCart?: boolean;
    /** Current quantity owned */
    currentQuantity?: number;
    /** Whether the action is loading */
    isLoading?: boolean;
    /** Whether the card is disabled */
    disabled?: boolean;
    /** Show features list */
    showFeatures?: boolean;
    /** Additional className */
    className?: string;
}

/**
 * AddonCard - Displays an addon with pricing and features
 */
export function AddonCard({
    addon,
    billingPeriod,
    onPurchase,
    onAddToCart,
    isPurchased = false,
    isInCart = false,
    currentQuantity = 0,
    isLoading = false,
    disabled = false,
    showFeatures = true,
    className,
}: AddonCardProps) {
    const { t } = useLaravelReactI18n();

    // Get price based on billing period
    const price = billingPeriod === 'yearly' && addon.price_yearly
        ? addon.price_yearly
        : addon.price_monthly;

    // Determine the period type for PriceDisplay
    const pricePeriod: 'monthly' | 'yearly' | 'one_time' | null =
        addon.price_one_time !== null && addon.price_one_time !== undefined
            ? 'one_time'
            : billingPeriod === 'yearly'
                ? 'yearly'
                : 'monthly';

    // Check if addon can be purchased (quantity limits)
    const canPurchase = !isPurchased || (
        addon.max_quantity > 1 && currentQuantity < addon.max_quantity
    );

    // Check if addon is a one-time purchase
    const isOneTime = pricePeriod === 'one_time';

    // Get type label from enum translations
    const typeLabel = addon.type === 'feature'
        ? t('enums.addon.type.feature', { default: 'Feature' })
        : addon.type === 'quota'
            ? t('enums.addon.type.quota', { default: 'Quota Increase' })
            : t('enums.addon.type.metered', { default: 'Usage-Based' });

    // Features list from addon (FeatureItem expects 'text' not 'label')
    const features = addon.features
        ? Object.entries(addon.features)
            .filter(([, enabled]) => enabled)
            .map(([key]) => ({
                text: key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
                included: true,
            }))
        : [];

    return (
        <Card
            className={cn(
                'relative flex h-full flex-col transition-all',
                isPurchased && 'border-green-500/50 bg-green-50/30 dark:bg-green-950/10',
                className
            )}
        >
            {/* Badge */}
            {addon.badge && (
                <div className="absolute -top-2 right-4">
                    <Badge
                        variant={addon.badge === 'popular' ? 'default' : 'secondary'}
                        className="text-xs"
                    >
                        {addon.badge === 'popular'
                            ? t('billing.popular', { default: 'Popular' })
                            : addon.badge === 'new'
                                ? t('billing.new', { default: 'New' })
                                : addon.badge}
                    </Badge>
                </div>
            )}

            <CardHeader className="pb-2">
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-2">
                        <div className="bg-primary/10 text-primary rounded-lg p-2">
                            <Puzzle className="h-5 w-5" />
                        </div>
                        <div>
                            <CardTitle className="text-lg">{addon.name}</CardTitle>
                            <Badge variant="outline" className="mt-1 text-xs">
                                {typeLabel}
                            </Badge>
                        </div>
                    </div>
                    {isPurchased && (
                        <Badge variant="secondary" className="gap-1">
                            <Check className="h-3 w-3" />
                            {t('billing.owned', { default: 'Owned' })}
                            {currentQuantity > 1 && ` (×${currentQuantity})`}
                        </Badge>
                    )}
                </div>
                {addon.description && (
                    <CardDescription className="mt-2">
                        {addon.description}
                    </CardDescription>
                )}
            </CardHeader>

            <CardContent className="flex-1 space-y-4">
                {/* Price */}
                <div className="space-y-1">
                    <PriceDisplay
                        amount={price ?? 0}
                        currency={addon.currency}
                        period={pricePeriod}
                        size="lg"
                    />
                    {isOneTime && (
                        <p className="text-muted-foreground text-sm">
                            {t('billing.one_time_purchase', { default: 'One-time purchase' })}
                        </p>
                    )}
                </div>

                {/* Unit value (for quota addons) */}
                {addon.unit_value && addon.type === 'quota' && (
                    <div className="text-muted-foreground text-sm">
                        +{addon.unit_value} {addon.unit_label || t('billing.units', { default: 'units' })}
                    </div>
                )}

                {/* Features */}
                {showFeatures && features.length > 0 && (
                    <FeatureList features={features} maxVisible={4} />
                )}

                {/* Quantity info */}
                {addon.max_quantity > 1 && (
                    <p className="text-muted-foreground text-xs">
                        {t('billing.max_quantity', {
                            default: 'Max: :max',
                            max: addon.max_quantity,
                        }).replace(':max', String(addon.max_quantity))}
                    </p>
                )}
            </CardContent>

            <CardFooter className="flex gap-2">
                {/* Add to Cart button */}
                {onAddToCart && (
                    <Button
                        className="flex-1"
                        variant={isInCart ? 'secondary' : 'outline'}
                        onClick={() => onAddToCart(addon.slug, 1)}
                        disabled={disabled || isLoading || !canPurchase}
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
                        className={onAddToCart ? 'flex-1' : 'w-full'}
                        onClick={() => onPurchase(addon.slug, 1)}
                        disabled={disabled || isLoading || !canPurchase}
                        variant={isPurchased ? 'outline' : 'default'}
                    >
                        {isLoading ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                {t('common.loading', { default: 'Loading...' })}
                            </>
                        ) : isPurchased ? (
                            canPurchase ? (
                                <>
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('billing.add_more', { default: 'Add More' })}
                                </>
                            ) : (
                                <>
                                    <Check className="mr-2 h-4 w-4" />
                                    {t('billing.already_owned', { default: 'Already Owned' })}
                                </>
                            )
                        ) : (
                            <>
                                <ShoppingCart className="mr-2 h-4 w-4" />
                                {t('billing.purchase', { default: 'Purchase' })}
                            </>
                        )}
                    </Button>
                )}
            </CardFooter>
        </Card>
    );
}

/**
 * ActiveAddonCard - Displays an active addon subscription
 */
export interface ActiveAddonCardProps {
    /** Active addon data */
    addon: {
        id: string;
        slug: string;
        name: string;
        description?: string;
        type: string;
        quantity: number;
        price: number;
        formattedPrice: string;
        totalPrice: number;
        formattedTotalPrice: string;
        billingPeriod: string;
        status: string;
        startedAt: string | null;
        expiresAt: string | null;
    };
    /** Callback when canceling */
    onCancel?: (id: string) => void;
    /** Callback when updating quantity */
    onUpdateQuantity?: (id: string, quantity: number) => void;
    /** Whether an action is loading */
    isLoading?: boolean;
    /** Additional className */
    className?: string;
}

export function ActiveAddonCard({
    addon,
    onCancel,
    onUpdateQuantity: _onUpdateQuantity,
    isLoading = false,
    className,
}: ActiveAddonCardProps) {
    // TODO: Implement quantity update functionality
    void _onUpdateQuantity;
    const { t } = useLaravelReactI18n();

    const periodLabel = addon.billingPeriod === 'yearly'
        ? t('billing.yearly', { default: 'Yearly' })
        : addon.billingPeriod === 'one_time'
            ? t('billing.one_time', { default: 'One-time' })
            : t('billing.monthly', { default: 'Monthly' });

    return (
        <Card className={cn('relative', className)}>
            <CardHeader className="pb-2">
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-2">
                        <div className="bg-green-500/10 rounded-lg p-2 text-green-600">
                            <Puzzle className="h-5 w-5" />
                        </div>
                        <div>
                            <CardTitle className="text-base">{addon.name}</CardTitle>
                            <div className="flex items-center gap-2">
                                <Badge variant="outline" className="text-xs">
                                    {periodLabel}
                                </Badge>
                                {addon.quantity > 1 && (
                                    <Badge variant="secondary" className="text-xs">
                                        ×{addon.quantity}
                                    </Badge>
                                )}
                            </div>
                        </div>
                    </div>
                    <Badge
                        variant={addon.status === 'active' ? 'default' : 'secondary'}
                        className="text-xs"
                    >
                        {addon.status === 'active'
                            ? t('billing.status.active', { default: 'Active' })
                            : addon.status}
                    </Badge>
                </div>
            </CardHeader>

            <CardContent className="space-y-3">
                {addon.description && (
                    <p className="text-muted-foreground text-sm">{addon.description}</p>
                )}

                <div className="flex items-center justify-between">
                    <span className="text-muted-foreground text-sm">
                        {t('billing.cost', { default: 'Cost' })}
                    </span>
                    <span className="font-semibold">{addon.formattedTotalPrice}</span>
                </div>

                {addon.expiresAt && (
                    <div className="flex items-center justify-between">
                        <span className="text-muted-foreground text-sm">
                            {t('billing.expires', { default: 'Expires' })}
                        </span>
                        <span className="text-sm">
                            {new Date(addon.expiresAt).toLocaleDateString()}
                        </span>
                    </div>
                )}
            </CardContent>

            <CardFooter className="gap-2">
                {addon.billingPeriod !== 'one_time' && onCancel && (
                    <Button
                        variant="outline"
                        size="sm"
                        className="flex-1"
                        onClick={() => onCancel(addon.id)}
                        disabled={isLoading}
                    >
                        {t('billing.cancel', { default: 'Cancel' })}
                    </Button>
                )}
            </CardFooter>
        </Card>
    );
}

/**
 * AddonCardSkeleton - Loading skeleton
 */
export function AddonCardSkeleton() {
    return (
        <Card>
            <CardHeader className="pb-2">
                <div className="flex items-center gap-2">
                    <div className="bg-muted h-9 w-9 animate-pulse rounded-lg" />
                    <div className="space-y-2">
                        <div className="bg-muted h-5 w-24 animate-pulse rounded" />
                        <div className="bg-muted h-4 w-16 animate-pulse rounded" />
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="bg-muted h-8 w-20 animate-pulse rounded" />
                <div className="space-y-2">
                    <div className="bg-muted h-4 w-full animate-pulse rounded" />
                    <div className="bg-muted h-4 w-3/4 animate-pulse rounded" />
                </div>
            </CardContent>
            <CardFooter>
                <div className="bg-muted h-10 w-full animate-pulse rounded" />
            </CardFooter>
        </Card>
    );
}
