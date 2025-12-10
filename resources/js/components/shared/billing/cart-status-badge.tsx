import { ShoppingCart } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCheckoutSafe } from '@/hooks/billing';
import { useLaravelReactI18n } from 'laravel-react-i18n';

interface CartStatusBadgeProps {
    /** Click handler to open cart */
    onClick?: () => void;
    /** Additional className */
    className?: string;
    /** Show only when cart has items */
    showOnlyWithItems?: boolean;
    /** Variant style */
    variant?: 'icon' | 'button';
}

/**
 * CartStatusBadge - Shows cart status with item count
 *
 * Use this in navigation or header to show cart status.
 * Integrates with CheckoutProvider context.
 *
 * @example
 * // In navigation
 * <CartStatusBadge onClick={() => setCartOpen(true)} />
 *
 * @example
 * // Icon only when cart has items
 * <CartStatusBadge showOnlyWithItems variant="icon" onClick={openCart} />
 */
export function CartStatusBadge({
    onClick,
    className,
    showOnlyWithItems = false,
    variant = 'icon',
}: CartStatusBadgeProps) {
    const { t } = useLaravelReactI18n();
    const { itemCount, total, hasItems, isRestoring, expiresInMinutes } = useCheckoutSafe();

    // Don't render during restoration or if configured to hide when empty
    if (isRestoring || (showOnlyWithItems && !hasItems)) {
        return null;
    }

    // Format total for display
    const formatPrice = (amount: number): string => {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: 'BRL',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount / 100);
    };

    // Build tooltip content
    const tooltipContent = hasItems
        ? expiresInMinutes !== null && expiresInMinutes < 60
            ? t('billing.cart_expires_soon', {
                  default: 'Cart expires in :minutes minutes',
                  minutes: expiresInMinutes,
              })
            : t('billing.items_in_cart', {
                  default: ':count item(s) in cart',
                  count: itemCount,
              })
        : t('billing.cart_empty', { default: 'Your cart is empty' });

    if (variant === 'icon') {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={onClick}
                        className={cn('relative', className)}
                    >
                        <ShoppingCart className="h-5 w-5" />
                        {hasItems && (
                            <Badge
                                className={cn(
                                    'absolute -right-1 -top-1 h-5 w-5 justify-center rounded-full p-0 text-xs',
                                    expiresInMinutes !== null && expiresInMinutes < 60
                                        ? 'bg-amber-500 hover:bg-amber-600'
                                        : ''
                                )}
                                variant="default"
                            >
                                {itemCount}
                            </Badge>
                        )}
                        <span className="sr-only">
                            {t('billing.open_cart', { default: 'Open cart' })}
                        </span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>
                    <p>{tooltipContent}</p>
                </TooltipContent>
            </Tooltip>
        );
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={onClick}
                    className={cn('gap-2', className)}
                >
                    <ShoppingCart className="h-4 w-4" />
                    {t('billing.cart', { default: 'Cart' })}
                    {hasItems && (
                        <>
                            <Badge
                                variant="secondary"
                                className={cn(
                                    expiresInMinutes !== null && expiresInMinutes < 60
                                        ? 'bg-amber-100 text-amber-800'
                                        : ''
                                )}
                            >
                                {itemCount}
                            </Badge>
                            <span className="font-semibold">{formatPrice(total)}</span>
                        </>
                    )}
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                <p>{tooltipContent}</p>
            </TooltipContent>
        </Tooltip>
    );
}
