import { useState, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ShoppingCart,
    Trash2,
    Loader2,
    ArrowRight,
    ShoppingBag,
    ArrowLeft,
    CheckCircle2,
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
import { PricingToggle } from '../primitives/pricing-toggle';
import { CheckoutLineItem } from './checkout-line-item';
import { CheckoutSummary } from './checkout-summary';
import {
    PaymentMethodSelector,
    type PaymentMethod,
} from '../payment-method-selector';
import { PixPayment } from '../pix-payment';
import { BoletoPayment } from '../boleto-payment';
import { AsaasCardForm } from '../asaas-card-form';
import type { CheckoutItem, PlanChangeInfo } from '@/types/billing';
import type { BillingPeriod } from '@/types/enums';
import type { PaymentConfigResource } from '@/types';
import { cartCheckout, cartPaymentStatus, asaasCardPayment } from '@/routes/tenant/admin/billing';

type CheckoutStep = 'cart' | 'payment' | 'async-payment' | 'asaas-card' | 'success';

// Animation configuration for step transitions
const stepAnimationClasses = {
    enter: 'animate-in fade-in-0 slide-in-from-right-4 duration-300',
    exit: 'animate-out fade-out-0 slide-out-to-left-4 duration-200',
    back: 'animate-in fade-in-0 slide-in-from-left-4 duration-300',
};

interface AsyncPaymentResult {
    type: 'pix' | 'boleto';
    payment_id: string;
    purchase_id: string;
    amount: number;
    pix?: {
        qr_code?: string;
        qr_code_base64?: string;
        copy_paste?: string;
        payload?: string;
        expiration?: string;
        expiration_date?: string;
    };
    boleto?: {
        url?: string;
        bank_slip_url?: string;
        barcode?: string;
        bar_code?: string;
        digitable_line?: string;
        identification_field?: string;
    };
    due_date?: string;
}

interface AsaasCardPaymentResult {
    type: 'asaas_card';
    purchase_id: string;
    amount: number;
    gateway: string;
    requires_card_data: boolean;
    required_fields?: {
        card: string[];
        holder: string[];
    };
}

export interface CheckoutPaymentSheetProps {
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
    /** Currency code */
    currency?: string;
    /** Plan change information */
    planChange?: PlanChangeInfo;
    /** Show billing period toggle */
    showBillingToggle?: boolean;
    /** Yearly savings text (e.g., "Save 20%") */
    yearlySavings?: string;
    /**
     * Payment configuration from backend (preferred).
     * Takes precedence over availableMethods.
     */
    paymentConfig?: PaymentConfigResource;
    /**
     * @deprecated Use paymentConfig instead. Kept for backward compatibility.
     */
    availableMethods?: PaymentMethod[];
    /** Whether recurring items are in cart (disables pix/boleto) */
    hasRecurring?: boolean;
    /** Side of the sheet */
    side?: 'left' | 'right';
    /** Additional className for sheet content */
    className?: string;
}

/**
 * CheckoutPaymentSheet - Enhanced checkout with multi-payment support
 *
 * This component combines the cart view with payment method selection,
 * supporting card (Stripe redirect), PIX, and Boleto payment flows.
 *
 * @example
 * const { items, clearCart } = useCheckout();
 *
 * <CheckoutPaymentSheet
 *     open={isOpen}
 *     onOpenChange={setIsOpen}
 *     items={items}
 *     billingPeriod="monthly"
 *     onClearCart={clearCart}
 * />
 */
export function CheckoutPaymentSheet({
    open,
    onOpenChange,
    items,
    billingPeriod,
    onBillingPeriodChange,
    onRemoveItem,
    onUpdateQuantity,
    onClearCart,
    currency = 'BRL',
    planChange,
    showBillingToggle = true,
    yearlySavings,
    paymentConfig,
    availableMethods: legacyAvailableMethods = ['card', 'pix', 'boleto'],
    hasRecurring = false,
    side = 'right',
    className,
}: CheckoutPaymentSheetProps) {
    const { t } = useLaravelReactI18n();
    const [step, setStep] = useState<CheckoutStep>('cart');
    const [selectedPaymentMethod, setSelectedPaymentMethod] =
        useState<PaymentMethod>('card');
    const [isCheckingOut, setIsCheckingOut] = useState(false);
    const [asyncPaymentResult, setAsyncPaymentResult] =
        useState<AsyncPaymentResult | null>(null);
    const [asaasCardResult, setAsaasCardResult] =
        useState<AsaasCardPaymentResult | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [animationDirection, setAnimationDirection] = useState<'forward' | 'back'>('forward');
    const [isAnimating, setIsAnimating] = useState(false);

    // Use paymentConfig from backend if available, otherwise fall back to legacy prop
    const availableMethods: PaymentMethod[] = paymentConfig
        ? (paymentConfig.available_methods as PaymentMethod[])
        : legacyAvailableMethods;

    // Handle animated step transition
    const goToStep = useCallback((newStep: CheckoutStep, direction: 'forward' | 'back' = 'forward') => {
        setAnimationDirection(direction);
        setIsAnimating(true);

        // Short delay for exit animation
        setTimeout(() => {
            setStep(newStep);
            setIsAnimating(false);
        }, 150);
    }, []);

    // Filter out pix/boleto if cart has recurring items
    // Also respect paymentConfig.has_recurring_support if available
    const effectiveAvailableMethods = hasRecurring
        ? availableMethods.filter((m) => m === 'card')
        : availableMethods;

    // Helper to get item price based on current billing period
    const getItemPrice = (item: CheckoutItem): number => {
        if (item.pricingByPeriod && item.isRecurring) {
            const periodKey = billingPeriod === 'yearly' ? 'yearly' : 'monthly';
            const periodPricing = item.pricingByPeriod[periodKey];
            if (periodPricing) {
                return periodPricing.price * item.quantity;
            }
        }
        return item.totalPrice;
    };

    // Calculate totals
    const subtotal = items.reduce((sum, item) => sum + getItemPrice(item), 0);
    const discount = 0;
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

    // Build cart items for API
    const buildCartItems = useCallback(() => {
        return items.map((item) => ({
            type: item.product.type === 'bundle' ? 'bundle' : 'addon',
            slug: item.product.slug,
            quantity: item.quantity,
            billing_period: item.isRecurring ? billingPeriod : 'one_time',
        }));
    }, [items, billingPeriod]);

    // Proceed to payment method selection
    const handleProceedToPayment = () => {
        setError(null);
        goToStep('payment', 'forward');
    };

    // Handle checkout submission
    const handleCheckout = async () => {
        setIsCheckingOut(true);
        setError(null);

        const cartItems = buildCartItems();

        // For all payment methods, make a fetch request first
        // This allows us to handle both Stripe redirect and Asaas card form
        try {
            const response = await fetch(cartCheckout.url(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN':
                        document.cookie
                            .split('; ')
                            .find((row) => row.startsWith('XSRF-TOKEN='))
                            ?.split('=')[1]
                            ?.replace(/%3D/g, '=') || '',
                },
                body: JSON.stringify({
                    items: cartItems,
                    payment_method: selectedPaymentMethod,
                }),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(
                    errorData.message ||
                        t('billing.errors.checkout_failed', {
                            default: 'Checkout failed',
                        }),
                );
            }

            const result = await response.json();

            // Handle different response types
            if (result.type === 'redirect') {
                // Stripe card checkout - redirect to Stripe hosted page
                window.location.href = result.url;
                return;
            }

            if (result.type === 'asaas_card') {
                // Asaas card checkout - show card form
                setAsaasCardResult(result as AsaasCardPaymentResult);
                goToStep('asaas-card', 'forward');
                setIsCheckingOut(false);
                return;
            }

            // PIX or Boleto - show async payment UI
            setAsyncPaymentResult(result as AsyncPaymentResult);
            goToStep('async-payment', 'forward');
        } catch (err) {
            setError(
                err instanceof Error
                    ? err.message
                    : t('billing.errors.checkout_failed', {
                          default: 'Checkout failed',
                      }),
            );
        } finally {
            setIsCheckingOut(false);
        }
    };

    // Handle async payment success
    const handleAsyncPaymentSuccess = () => {
        goToStep('success', 'forward');
        // Clear cart after showing success
        setTimeout(() => {
            onClearCart?.();
        }, 500);
    };

    // Handle closing from success state
    const handleSuccessClose = () => {
        onOpenChange(false);
        router.visit('/admin/billing', {
            preserveScroll: true,
        });
    };

    // Handle going back
    const handleBack = () => {
        if (step === 'payment') {
            goToStep('cart', 'back');
        } else if (step === 'async-payment') {
            goToStep('payment', 'back');
            setAsyncPaymentResult(null);
        } else if (step === 'asaas-card') {
            goToStep('payment', 'back');
            setAsaasCardResult(null);
        }
    };

    // Handle Asaas card payment success
    const handleAsaasCardSuccess = () => {
        goToStep('success', 'forward');
        // Clear cart after showing success
        setTimeout(() => {
            onClearCart?.();
        }, 500);
    };

    // Reset state when closing
    const handleOpenChange = (isOpen: boolean) => {
        if (!isOpen) {
            setStep('cart');
            setAsyncPaymentResult(null);
            setAsaasCardResult(null);
            setError(null);
        }
        onOpenChange(isOpen);
    };

    // Get title based on step
    const getTitle = () => {
        switch (step) {
            case 'cart':
                return t('billing.cart', { default: 'Cart' });
            case 'payment':
                return t('billing.payment_method', {
                    default: 'Payment Method',
                });
            case 'async-payment':
                return asyncPaymentResult?.type === 'pix'
                    ? t('billing.pix_payment', { default: 'PIX Payment' })
                    : t('billing.boleto_payment', {
                          default: 'Boleto Payment',
                      });
            case 'asaas-card':
                return t('billing.card_payment', {
                    default: 'Card Payment',
                });
            case 'success':
                return t('billing.payment_successful', {
                    default: 'Payment Successful',
                });
        }
    };

    // Get animation class based on direction
    const getAnimationClass = () => {
        if (isAnimating) {
            return 'opacity-0 transition-opacity duration-150';
        }
        return animationDirection === 'forward'
            ? stepAnimationClasses.enter
            : stepAnimationClasses.back;
    };

    // Render PIX payment
    const renderPixPayment = () => {
        if (!asyncPaymentResult?.pix) return null;

        const pix = asyncPaymentResult.pix;
        const qrCodeUrl = pix.qr_code_base64
            ? `data:image/png;base64,${pix.qr_code_base64}`
            : pix.qr_code || '';
        const qrCodeData = pix.copy_paste || pix.payload || '';
        const expiration =
            pix.expiration_date ||
            pix.expiration ||
            new Date(Date.now() + 30 * 60 * 1000).toISOString();

        return (
            <PixPayment
                qrCodeUrl={qrCodeUrl}
                qrCodeData={qrCodeData}
                expiresAt={expiration}
                purchaseId={asyncPaymentResult.purchase_id}
                onSuccess={handleAsyncPaymentSuccess}
                statusEndpoint={cartPaymentStatus.url()}
                pollInterval={5000}
            />
        );
    };

    // Render Boleto payment
    const renderBoletoPayment = () => {
        if (!asyncPaymentResult?.boleto) return null;

        const boleto = asyncPaymentResult.boleto;
        const boletoUrl = boleto.url || boleto.bank_slip_url || '';
        const barcode =
            boleto.digitable_line ||
            boleto.identification_field ||
            boleto.barcode ||
            boleto.bar_code ||
            '';

        return (
            <BoletoPayment
                boletoUrl={boletoUrl}
                barcode={barcode}
                dueDate={asyncPaymentResult.due_date || ''}
                amount={formatPrice(asyncPaymentResult.amount)}
            />
        );
    };

    // Render success step
    const renderSuccess = () => {
        return (
            <div className="flex flex-1 flex-col items-center justify-center gap-6 text-center">
                {/* Animated checkmark */}
                <div className="relative">
                    <div className="animate-in zoom-in-50 duration-500 ease-out flex h-20 w-20 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                        <CheckCircle2 className="animate-in fade-in-0 zoom-in-75 delay-200 duration-500 h-10 w-10 text-green-600 dark:text-green-400" />
                    </div>
                    {/* Ripple effect */}
                    <div className="animate-ping absolute inset-0 rounded-full bg-green-400/30 dark:bg-green-400/20" style={{ animationDuration: '1s', animationIterationCount: '2' }} />
                </div>

                <div className="animate-in fade-in-0 slide-in-from-bottom-4 delay-300 duration-500 space-y-2">
                    <h3 className="text-xl font-semibold text-foreground">
                        {t('billing.payment_confirmed', { default: 'Payment Confirmed!' })}
                    </h3>
                    <p className="text-sm text-muted-foreground max-w-xs">
                        {t('billing.payment_success_message', {
                            default: 'Your purchase has been completed successfully. Your add-ons are now active.',
                        })}
                    </p>
                </div>

                <div className="animate-in fade-in-0 slide-in-from-bottom-4 delay-500 duration-500 flex flex-col gap-2 w-full max-w-xs">
                    <Button onClick={handleSuccessClose} size="lg" className="w-full">
                        {t('billing.view_billing', { default: 'View Billing' })}
                        <ArrowRight className="ml-2 h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        className="w-full"
                    >
                        {t('billing.continue_shopping', { default: 'Continue Shopping' })}
                    </Button>
                </div>
            </div>
        );
    };

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetContent
                side={side}
                className={cn('flex w-full flex-col sm:max-w-lg', className)}
            >
                <SheetHeader className="space-y-1 pb-0">
                    <div className="flex items-center justify-between pr-8">
                        <div className="flex items-center gap-2">
                            {step !== 'cart' && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-8 w-8"
                                    onClick={handleBack}
                                >
                                    <ArrowLeft className="h-4 w-4" />
                                </Button>
                            )}
                            <SheetTitle className="flex items-center gap-2">
                                <ShoppingCart className="h-5 w-5" />
                                {getTitle()}
                                {step === 'cart' && itemCount > 0 && (
                                    <Badge variant="secondary" className="ml-1">
                                        {itemCount}
                                    </Badge>
                                )}
                            </SheetTitle>
                        </div>
                        {step === 'cart' && hasItems && onClearCart && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-destructive hover:text-destructive hover:bg-destructive/10 h-8 px-2"
                                onClick={onClearCart}
                            >
                                <Trash2 className="mr-1 h-4 w-4" />
                                {t('billing.clear', { default: 'Clear' })}
                            </Button>
                        )}
                    </div>
                    <SheetDescription>
                        {step === 'cart' &&
                            (hasItems
                                ? t('billing.cart_description', {
                                      default:
                                          'Review your items before checkout',
                                  })
                                : t('billing.cart_empty', {
                                      default: 'Your cart is empty',
                                  }))}
                        {step === 'payment' &&
                            t('billing.select_payment_method', {
                                default: 'Select how you want to pay',
                            })}
                        {step === 'async-payment' &&
                            t('billing.complete_payment', {
                                default:
                                    'Complete your payment to finish the purchase',
                            })}
                    </SheetDescription>
                </SheetHeader>

                {/* Error message */}
                {error && (
                    <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                        {error}
                    </div>
                )}

                {/* Step: Cart */}
                {step === 'cart' && (
                    <>
                        {/* Billing period toggle */}
                        {hasItems &&
                            showBillingToggle &&
                            onBillingPeriodChange && (
                                <div>
                                    <PricingToggle
                                        value={
                                            billingPeriod === 'yearly'
                                                ? 'yearly'
                                                : 'monthly'
                                        }
                                        onChange={handleBillingPeriodChange}
                                        savings={yearlySavings}
                                        className="justify-center"
                                    />
                                </div>
                            )}

                        {/* Items list */}
                        {hasItems ? (
                            <ScrollArea className="-mx-6 flex-1 px-6">
                                <div className="space-y-3 pb-4">
                                    {items.map((item) => (
                                        <CheckoutLineItem
                                            key={item.id}
                                            item={item}
                                            currentBillingPeriod={billingPeriod}
                                            onRemove={
                                                onRemoveItem
                                                    ? () =>
                                                          onRemoveItem(item.id)
                                                    : undefined
                                            }
                                            onQuantityChange={
                                                onUpdateQuantity
                                                    ? (qty) =>
                                                          onUpdateQuantity(
                                                              item.id,
                                                              qty,
                                                          )
                                                    : undefined
                                            }
                                        />
                                    ))}
                                </div>
                            </ScrollArea>
                        ) : (
                            <div className="flex flex-1 flex-col items-center justify-center gap-4 text-center">
                                <div className="rounded-full bg-muted p-4">
                                    <ShoppingBag className="h-8 w-8 text-muted-foreground" />
                                </div>
                                <div>
                                    <p className="font-medium">
                                        {t('billing.no_items', {
                                            default: 'No items in cart',
                                        })}
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {t('billing.browse_products', {
                                            default:
                                                'Browse our plans and add-ons to get started',
                                        })}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    onClick={() => handleOpenChange(false)}
                                >
                                    {t('billing.continue_shopping', {
                                        default: 'Continue Shopping',
                                    })}
                                </Button>
                            </div>
                        )}

                        {/* Footer with summary */}
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
                                    onClick={handleProceedToPayment}
                                    disabled={!hasItems}
                                >
                                    {t('billing.proceed_to_payment', {
                                        default: 'Proceed to Payment',
                                    })}
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Button>
                            </SheetFooter>
                        )}
                    </>
                )}

                {/* Step: Payment Method Selection */}
                {step === 'payment' && (
                    <>
                        <div className="flex-1 space-y-4">
                            <PaymentMethodSelector
                                value={selectedPaymentMethod}
                                onChange={setSelectedPaymentMethod}
                                availableMethods={effectiveAvailableMethods}
                                disabled={isCheckingOut}
                            />

                            {hasRecurring &&
                                effectiveAvailableMethods.length === 1 && (
                                    <p className="text-sm text-muted-foreground">
                                        {t('billing.recurring_card_only', {
                                            default:
                                                'Credit card is required for recurring subscriptions.',
                                        })}
                                    </p>
                                )}

                            {/* Order summary */}
                            <div className="rounded-lg border p-4">
                                <h4 className="mb-2 font-medium">
                                    {t('billing.order_summary', {
                                        default: 'Order Summary',
                                    })}
                                </h4>
                                <div className="space-y-1 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            {t('billing.items', {
                                                default: 'Items',
                                            })}
                                        </span>
                                        <span>{itemCount}</span>
                                    </div>
                                    <div className="flex justify-between font-semibold">
                                        <span>
                                            {t('billing.total', {
                                                default: 'Total',
                                            })}
                                        </span>
                                        <span>{formatPrice(total)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <SheetFooter className="flex-col gap-4 border-t pt-4 sm:flex-col">
                            <Button
                                className="w-full"
                                size="lg"
                                onClick={handleCheckout}
                                disabled={isCheckingOut}
                            >
                                {isCheckingOut ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        {t('billing.processing', {
                                            default: 'Processing...',
                                        })}
                                    </>
                                ) : (
                                    <>
                                        {selectedPaymentMethod === 'card'
                                            ? t('billing.pay_with_card', {
                                                  default: 'Pay with Card',
                                              })
                                            : selectedPaymentMethod === 'pix'
                                              ? t('billing.generate_pix', {
                                                    default: 'Generate PIX',
                                                })
                                              : t('billing.generate_boleto', {
                                                    default: 'Generate Boleto',
                                                })}
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </>
                                )}
                            </Button>
                        </SheetFooter>
                    </>
                )}

                {/* Step: Async Payment (PIX/Boleto) */}
                {step === 'async-payment' && asyncPaymentResult && (
                    <div className={cn('flex-1', getAnimationClass())}>
                        {asyncPaymentResult.type === 'pix'
                            ? renderPixPayment()
                            : renderBoletoPayment()}
                    </div>
                )}

                {/* Step: Asaas Card Payment */}
                {step === 'asaas-card' && asaasCardResult && (
                    <ScrollArea className={cn('-mx-6 flex-1 px-6', getAnimationClass())}>
                        <AsaasCardForm
                            purchaseId={asaasCardResult.purchase_id}
                            amount={asaasCardResult.amount}
                            formattedAmount={formatPrice(asaasCardResult.amount)}
                            submitEndpoint={asaasCardPayment.url()}
                            onSuccess={handleAsaasCardSuccess}
                            onError={(err) => setError(err)}
                        />
                    </ScrollArea>
                )}

                {/* Step: Success */}
                {step === 'success' && (
                    <div className={cn('flex-1', getAnimationClass())}>
                        {renderSuccess()}
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
