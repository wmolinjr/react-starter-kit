import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ShoppingCart,
    X,
    Trash2,
    CreditCard,
    Loader2,
    ArrowRight,
    ShoppingBag,
} from 'lucide-react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { PricingToggle } from '../primitives/pricing-toggle';
import { CheckoutLineItem } from './checkout-line-item';
import { CheckoutSummary } from './checkout-summary';
import type { CheckoutItem, PlanChangeInfo } from '@/types/billing';
import type { BillingPeriod } from '@/types/enums';

export interface CheckoutSheetProps {
    /** Whether the sheet is open */
    open: boolean;
    /** Callback when open state changes */
    onOpenChange: (open: boolean) => void;
    /** Items in the cart */
    items: CheckoutItem[];
    /** Current billing period */
    billingPeriod: BillingPeriod;
    /** Callback when billing period changes */
    onBillingPeriodChange?: (period: BillingPeriod) => void;
    /** Callback when removing an item */
    onRemoveItem?: (itemId: string) => void;
    /** Callback when updating quantity */
    onUpdateQuantity?: (itemId: string, quantity: number) => void;
    /** Callback when clearing all items */
    onClearCart?: () => void;
    /** Callback when proceeding to checkout */
    onCheckout?: () => void;
    /** Whether checkout is in progress */
    isCheckingOut?: boolean;
    /** Currency code */
    currency?: string;
    /** Plan change information */
    planChange?: PlanChangeInfo;
    /** Show billing period toggle */
    showBillingToggle?: boolean;
    /** Yearly savings text (e.g., "Save 20%") */
    yearlySavings?: string;
    /** Custom checkout button label */
    checkoutLabel?: string;
    /** Side of the sheet */
    side?: 'left' | 'right';
    /** Additional className for sheet content */
    className?: string;
}

/**
 * CheckoutSheet - Sliding panel for checkout cart
 *
 * @example
 * const { items, isOpen, close, removeItem, updateQuantity, clearCart } = useCheckout();
 * const { period, setPeriod } = useBillingPeriod();
 *
 * <CheckoutSheet
 *     open={isOpen}
 *     onOpenChange={(open) => !open && close()}
 *     items={items}
 *     billingPeriod={period}
 *     onBillingPeriodChange={setPeriod}
 *     onRemoveItem={removeItem}
 *     onUpdateQuantity={updateQuantity}
 *     onClearCart={clearCart}
 *     onCheckout={() => router.post('/billing/checkout')}
 * />
 */
export function CheckoutSheet({
    open,
    onOpenChange,
    items,
    billingPeriod,
    onBillingPeriodChange,
    onRemoveItem,
    onUpdateQuantity,
    onClearCart,
    onCheckout,
    isCheckingOut = false,
    currency = 'USD',
    planChange,
    showBillingToggle = true,
    yearlySavings,
    checkoutLabel,
    side = 'right',
    className,
}: CheckoutSheetProps) {
    const { t } = useLaravelReactI18n();

    // Calculate totals
    const subtotal = items.reduce((sum, item) => sum + item.totalPrice, 0);
    const discount = 0; // Future: calculate bundle/promo discounts
    const total = subtotal - discount;

    // Format price
    const formatPrice = (amount: number): string => {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
            minimumFractionDigits: amount % 100 === 0 ? 0 : 2,
            maximumFractionDigits: 2,
        }).format(amount / 100);
    };

    // Item count
    const itemCount = items.reduce((sum, item) => sum + item.quantity, 0);
    const hasItems = items.length > 0;

    // Handle billing period change
    const handleBillingPeriodChange = (period: 'monthly' | 'yearly') => {
        onBillingPeriodChange?.(period as BillingPeriod);
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side={side}
                className={cn('flex w-full flex-col sm:max-w-lg', className)}
            >
                <SheetHeader className="space-y-1">
                    <div className="flex items-center justify-between">
                        <SheetTitle className="flex items-center gap-2">
                            <ShoppingCart className="h-5 w-5" />
                            {t('billing.cart', { default: 'Cart' })}
                            {itemCount > 0 && (
                                <Badge variant="secondary" className="ml-1">
                                    {itemCount}
                                </Badge>
                            )}
                        </SheetTitle>
                        {hasItems && onClearCart && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-muted-foreground hover:text-destructive h-8 px-2"
                                onClick={onClearCart}
                            >
                                <Trash2 className="mr-1 h-4 w-4" />
                                {t('billing.clear', { default: 'Clear' })}
                            </Button>
                        )}
                    </div>
                    <SheetDescription>
                        {hasItems
                            ? t('billing.cart_description', {
                                  default: 'Review your items before checkout',
                              })
                            : t('billing.cart_empty', { default: 'Your cart is empty' })}
                    </SheetDescription>
                </SheetHeader>

                {/* Billing period toggle */}
                {hasItems && showBillingToggle && onBillingPeriodChange && (
                    <div className="py-4">
                        <PricingToggle
                            value={billingPeriod === 'yearly' ? 'yearly' : 'monthly'}
                            onChange={handleBillingPeriodChange}
                            savings={yearlySavings}
                            className="justify-center"
                        />
                    </div>
                )}

                {/* Items list */}
                {hasItems ? (
                    <ScrollArea className="flex-1 -mx-6 px-6">
                        <div className="space-y-3 pb-4">
                            {items.map((item) => (
                                <CheckoutLineItem
                                    key={item.id}
                                    item={item}
                                    onRemove={
                                        onRemoveItem
                                            ? () => onRemoveItem(item.id)
                                            : undefined
                                    }
                                    onQuantityChange={
                                        onUpdateQuantity
                                            ? (qty) => onUpdateQuantity(item.id, qty)
                                            : undefined
                                    }
                                />
                            ))}
                        </div>
                    </ScrollArea>
                ) : (
                    <div className="flex flex-1 flex-col items-center justify-center gap-4 text-center">
                        <div className="bg-muted rounded-full p-4">
                            <ShoppingBag className="text-muted-foreground h-8 w-8" />
                        </div>
                        <div>
                            <p className="font-medium">
                                {t('billing.no_items', { default: 'No items in cart' })}
                            </p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {t('billing.browse_products', {
                                    default: 'Browse our plans and add-ons to get started',
                                })}
                            </p>
                        </div>
                        <Button variant="outline" onClick={() => onOpenChange(false)}>
                            {t('billing.continue_shopping', { default: 'Continue Shopping' })}
                        </Button>
                    </div>
                )}

                {/* Footer with summary and checkout */}
                {hasItems && (
                    <SheetFooter className="flex-col gap-4 border-t pt-4 sm:flex-col">
                        <CheckoutSummary
                            items={items}
                            billingPeriod={billingPeriod}
                            currency={currency}
                            subtotal={subtotal}
                            discount={discount}
                            total={total}
                            formattedSubtotal={formatPrice(subtotal)}
                            formattedDiscount={formatPrice(discount)}
                            formattedTotal={formatPrice(total)}
                            planChange={planChange}
                        />

                        <Button
                            className="w-full"
                            size="lg"
                            onClick={onCheckout}
                            disabled={isCheckingOut || !hasItems}
                        >
                            {isCheckingOut ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    {t('billing.processing', { default: 'Processing...' })}
                                </>
                            ) : (
                                <>
                                    <CreditCard className="mr-2 h-4 w-4" />
                                    {checkoutLabel ||
                                        t('billing.checkout', { default: 'Checkout' })}
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </>
                            )}
                        </Button>

                        <p className="text-muted-foreground text-center text-xs">
                            {t('billing.secure_checkout', {
                                default: 'Secure checkout powered by Stripe',
                            })}
                        </p>
                    </SheetFooter>
                )}
            </SheetContent>
        </Sheet>
    );
}

/**
 * CheckoutCartButton - Button to open checkout sheet with item count
 */
export interface CheckoutCartButtonProps {
    /** Number of items in cart */
    itemCount: number;
    /** Total amount */
    total?: number;
    /** Currency code */
    currency?: string;
    /** Callback when clicked */
    onClick?: () => void;
    /** Button variant */
    variant?: 'default' | 'outline' | 'ghost';
    /** Button size */
    size?: 'default' | 'sm' | 'lg' | 'icon';
    /** Additional className */
    className?: string;
}

export function CheckoutCartButton({
    itemCount,
    total,
    currency = 'USD',
    onClick,
    variant = 'outline',
    size = 'default',
    className,
}: CheckoutCartButtonProps) {
    const { t } = useLaravelReactI18n();

    const formatPrice = (amount: number): string => {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount / 100);
    };

    if (size === 'icon') {
        return (
            <Button
                variant={variant}
                size="icon"
                onClick={onClick}
                className={cn('relative', className)}
            >
                <ShoppingCart className="h-5 w-5" />
                {itemCount > 0 && (
                    <Badge
                        className="absolute -right-1 -top-1 h-5 w-5 justify-center rounded-full p-0 text-xs"
                        variant="default"
                    >
                        {itemCount}
                    </Badge>
                )}
                <span className="sr-only">
                    {t('billing.open_cart', { default: 'Open cart' })}
                </span>
            </Button>
        );
    }

    return (
        <Button variant={variant} size={size} onClick={onClick} className={className}>
            <ShoppingCart className="mr-2 h-4 w-4" />
            {t('billing.cart', { default: 'Cart' })}
            {itemCount > 0 && (
                <>
                    <Badge variant="secondary" className="ml-2">
                        {itemCount}
                    </Badge>
                    {total !== undefined && total > 0 && (
                        <span className="ml-2 font-semibold">{formatPrice(total)}</span>
                    )}
                </>
            )}
        </Button>
    );
}
