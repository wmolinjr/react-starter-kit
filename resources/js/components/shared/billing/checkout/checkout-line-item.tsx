import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    Package,
    Users,
    HardDrive,
    Zap,
    Globe,
    Shield,
    Crown,
    Trash2,
    Plus,
    Minus,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import type { CheckoutItem } from '@/types/billing';
import type { BillingPeriod } from '@/types/enums';

// Helper function to format price
function formatPrice(amount: number, currency = 'USD'): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: amount % 100 === 0 ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(amount / 100);
}

export interface CheckoutLineItemProps {
    /** The checkout item to display */
    item: CheckoutItem;
    /** Current billing period (for dynamic price calculation) */
    currentBillingPeriod?: BillingPeriod;
    /** Callback when removing the item */
    onRemove?: () => void;
    /** Callback when quantity changes */
    onQuantityChange?: (quantity: number) => void;
    /** Whether the item is read-only (no remove/quantity buttons) */
    readonly?: boolean;
    /** Whether to show quantity controls */
    showQuantityControls?: boolean;
    /** Compact display mode */
    compact?: boolean;
    /** Additional className */
    className?: string;
}

/**
 * CheckoutLineItem - Displays a single item in the checkout cart
 *
 * @example
 * <CheckoutLineItem
 *     item={item}
 *     onRemove={() => removeItem(item.id)}
 *     onQuantityChange={(qty) => updateQuantity(item.id, qty)}
 * />
 *
 * @example
 * // Read-only mode (e.g., in confirmation)
 * <CheckoutLineItem item={item} readonly />
 */
export function CheckoutLineItem({
    item,
    currentBillingPeriod,
    onRemove,
    onQuantityChange,
    readonly = false,
    showQuantityControls = true,
    compact = false,
    className,
}: CheckoutLineItemProps) {
    const { t } = useLaravelReactI18n();

    // Calculate current pricing based on billing period
    const getCurrentPricing = () => {
        // If we have pricingByPeriod and a different current period, use that
        if (item.pricingByPeriod && currentBillingPeriod && item.isRecurring) {
            const periodKey = currentBillingPeriod === 'yearly' ? 'yearly' : 'monthly';
            const periodPricing = item.pricingByPeriod[periodKey];
            if (periodPricing) {
                const totalPrice = periodPricing.price * item.quantity;
                return {
                    unitPrice: periodPricing.price,
                    totalPrice,
                    formattedUnitPrice: periodPricing.formattedPrice,
                    formattedTotalPrice: formatPrice(totalPrice),
                    billingPeriod: currentBillingPeriod,
                };
            }
        }
        // Fallback to stored pricing
        return {
            unitPrice: item.unitPrice,
            totalPrice: item.totalPrice,
            formattedUnitPrice: item.formattedUnitPrice,
            formattedTotalPrice: item.formattedTotalPrice,
            billingPeriod: item.billingPeriod,
        };
    };

    const pricing = getCurrentPricing();

    // Get icon for product type
    const getProductIcon = () => {
        const { product } = item;

        if (product.type === 'plan') {
            return <Crown className="h-4 w-4" />;
        }

        if (product.type === 'bundle') {
            return <Package className="h-4 w-4" />;
        }

        // Addon - get icon based on addon type or icon property
        if (product.icon) {
            const iconMap: Record<string, React.ReactNode> = {
                users: <Users className="h-4 w-4" />,
                storage: <HardDrive className="h-4 w-4" />,
                api: <Zap className="h-4 w-4" />,
                globe: <Globe className="h-4 w-4" />,
                shield: <Shield className="h-4 w-4" />,
            };
            return iconMap[product.icon.toLowerCase()] || <Package className="h-4 w-4" />;
        }

        return <Package className="h-4 w-4" />;
    };

    // Get product type badge
    const getTypeBadge = () => {
        const typeLabels: Record<string, string> = {
            plan: t('billing.type.plan', { default: 'Plan' }),
            addon: t('billing.type.addon', { default: 'Add-on' }),
            bundle: t('billing.type.bundle', { default: 'Bundle' }),
        };

        const typeColors: Record<string, string> = {
            plan: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
            addon: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            bundle: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        };

        return (
            <Badge
                variant="secondary"
                className={cn('text-xs font-normal', typeColors[item.product.type])}
            >
                {typeLabels[item.product.type]}
            </Badge>
        );
    };

    // Get billing period label
    const getBillingLabel = () => {
        if (!item.isRecurring) {
            return t('billing.price.one_time', { default: 'One-time' });
        }

        return pricing.billingPeriod === 'yearly'
            ? t('billing.price.per_year', { default: '/year' })
            : t('billing.price.per_month', { default: '/month' });
    };

    // Handle quantity change
    const handleQuantityChange = (delta: number) => {
        const newQuantity = Math.max(1, item.quantity + delta);
        onQuantityChange?.(newQuantity);
    };

    // Can show quantity controls (not for plans)
    const canChangeQuantity =
        showQuantityControls && !readonly && item.product.type !== 'plan';

    if (compact) {
        return (
            <div className={cn('flex items-center justify-between gap-2 py-2', className)}>
                <div className="flex items-center gap-2 min-w-0">
                    <div className="text-muted-foreground shrink-0">
                        {getProductIcon()}
                    </div>
                    <span className="truncate text-sm">{item.product.name}</span>
                    {item.quantity > 1 && (
                        <span className="text-muted-foreground text-xs">
                            x{item.quantity}
                        </span>
                    )}
                </div>
                <span className="shrink-0 text-sm font-medium">
                    {pricing.formattedTotalPrice}
                </span>
            </div>
        );
    }

    return (
        <div
            className={cn(
                'rounded-lg border p-3',
                className
            )}
        >
            {/* Header row: Icon + Name + Badge + Price + Remove */}
            <div className="flex items-start gap-2">
                {/* Icon */}
                <div className="bg-muted text-muted-foreground shrink-0 rounded-md p-1.5 mt-0.5">
                    {getProductIcon()}
                </div>

                {/* Name, badge and description */}
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-1.5 flex-wrap">
                        <h4 className="text-sm font-medium leading-tight">{item.product.name}</h4>
                        {getTypeBadge()}
                    </div>
                    {item.product.description && (
                        <p className="text-muted-foreground mt-0.5 text-xs line-clamp-2">
                            {item.product.description}
                        </p>
                    )}
                </div>

                {/* Price - always visible, fixed position */}
                <div className="shrink-0 text-right">
                    <div className="font-semibold">{pricing.formattedTotalPrice}</div>
                    {item.isRecurring && (
                        <div className="text-muted-foreground text-xs">
                            {getBillingLabel()}
                        </div>
                    )}
                </div>

                {/* Remove button */}
                {!readonly && onRemove && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="text-muted-foreground hover:text-destructive h-6 w-6 shrink-0 -mr-1"
                        onClick={onRemove}
                    >
                        <Trash2 className="h-3.5 w-3.5" />
                        <span className="sr-only">
                            {t('common.remove', { default: 'Remove' })}
                        </span>
                    </Button>
                )}
            </div>

            {/* Quantity row */}
            <div className="mt-2 flex items-center">
                {canChangeQuantity ? (
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-6 w-6"
                            onClick={() => handleQuantityChange(-1)}
                            disabled={item.quantity <= 1}
                        >
                            <Minus className="h-3 w-3" />
                        </Button>
                        <span className="w-6 text-center text-sm font-medium">
                            {item.quantity}
                        </span>
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-6 w-6"
                            onClick={() => handleQuantityChange(1)}
                        >
                            <Plus className="h-3 w-3" />
                        </Button>
                    </div>
                ) : (
                    item.quantity > 1 && (
                        <span className="text-muted-foreground text-xs">
                            {item.quantity} x {pricing.formattedUnitPrice}
                        </span>
                    )
                )}
            </div>
        </div>
    );
}

/**
 * CheckoutLineItemSkeleton - Loading skeleton
 */
export function CheckoutLineItemSkeleton({
    compact = false,
    className,
}: {
    compact?: boolean;
    className?: string;
}) {
    if (compact) {
        return (
            <div className={cn('flex items-center justify-between gap-2 py-2', className)}>
                <div className="flex items-center gap-2">
                    <div className="bg-muted h-4 w-4 animate-pulse rounded" />
                    <div className="bg-muted h-4 w-24 animate-pulse rounded" />
                </div>
                <div className="bg-muted h-4 w-16 animate-pulse rounded" />
            </div>
        );
    }

    return (
        <div className={cn('flex items-start gap-3 rounded-lg border p-3', className)}>
            <div className="bg-muted h-9 w-9 animate-pulse rounded-lg" />
            <div className="flex-1 space-y-2">
                <div className="flex items-center gap-2">
                    <div className="bg-muted h-5 w-32 animate-pulse rounded" />
                    <div className="bg-muted h-5 w-16 animate-pulse rounded-full" />
                </div>
                <div className="bg-muted h-4 w-48 animate-pulse rounded" />
                <div className="flex items-center justify-between">
                    <div className="bg-muted h-7 w-20 animate-pulse rounded" />
                    <div className="bg-muted h-5 w-16 animate-pulse rounded" />
                </div>
            </div>
        </div>
    );
}
