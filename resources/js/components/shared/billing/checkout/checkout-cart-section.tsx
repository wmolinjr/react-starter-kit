import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ShoppingBag, Package } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { CheckoutLineItem } from './checkout-line-item';
import { PricingToggle } from '../primitives/pricing-toggle';
import type { CheckoutItem } from '@/types/billing';
import type { BillingPeriod } from '@/types/enums';

export interface CheckoutCartSectionProps {
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
    /** Show billing period toggle */
    showBillingToggle?: boolean;
    /** Yearly savings text */
    yearlySavings?: string;
    /** Additional className */
    className?: string;
}

/**
 * CheckoutCartSection - Cart items section for dedicated checkout page
 *
 * Displays editable cart items with quantity controls and optional billing period toggle.
 *
 * @example
 * <CheckoutCartSection
 *     items={items}
 *     billingPeriod={period}
 *     onBillingPeriodChange={setPeriod}
 *     onRemoveItem={removeItem}
 *     onUpdateQuantity={updateQuantity}
 * />
 */
export function CheckoutCartSection({
    items,
    billingPeriod,
    onBillingPeriodChange,
    onRemoveItem,
    onUpdateQuantity,
    showBillingToggle = true,
    yearlySavings,
    className,
}: CheckoutCartSectionProps) {
    const { t } = useLaravelReactI18n();

    const itemCount = items.reduce((sum, item) => sum + item.quantity, 0);
    const hasRecurring = items.some((item) => item.isRecurring);

    // Handle billing period change
    const handleBillingPeriodChange = (period: 'monthly' | 'yearly') => {
        onBillingPeriodChange?.(period as BillingPeriod);
    };

    if (items.length === 0) {
        return (
            <Card className={cn('', className)}>
                <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                    <div className="rounded-full bg-muted p-4 mb-4">
                        <ShoppingBag className="h-8 w-8 text-muted-foreground" />
                    </div>
                    <p className="font-medium">
                        {t('checkout.cart_empty', { default: 'Your cart is empty' })}
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('checkout.browse_products', {
                            default: 'Browse our add-ons to get started',
                        })}
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className={cn('', className)}>
            <CardHeader className="pb-4">
                <div className="flex items-center gap-2">
                    <Package className="h-5 w-5 text-muted-foreground" />
                    <CardTitle className="text-lg">
                        {t('checkout.your_order', { default: 'Your Order' })}
                    </CardTitle>
                </div>
                <CardDescription>
                    {t('checkout.items_count', {
                        default: ':count item(s)',
                        count: itemCount,
                    }).replace(':count', String(itemCount))}
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                {/* Billing period toggle */}
                {hasRecurring && showBillingToggle && onBillingPeriodChange && (
                    <div className="pb-2">
                        <PricingToggle
                            value={billingPeriod === 'yearly' ? 'yearly' : 'monthly'}
                            onChange={handleBillingPeriodChange}
                            savings={yearlySavings}
                            className="justify-start"
                        />
                    </div>
                )}

                {/* Items list */}
                <div className="space-y-3">
                    {items.map((item) => (
                        <CheckoutLineItem
                            key={item.id}
                            item={item}
                            currentBillingPeriod={billingPeriod}
                            onRemove={onRemoveItem ? () => onRemoveItem(item.id) : undefined}
                            onQuantityChange={
                                onUpdateQuantity
                                    ? (qty) => onUpdateQuantity(item.id, qty)
                                    : undefined
                            }
                        />
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
