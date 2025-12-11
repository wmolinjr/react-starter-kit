import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CreditCard } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { PaymentMethodSelector, type PaymentMethod } from '../payment-method-selector';
import type { PaymentConfigResource } from '@/types';

export interface CheckoutPaymentSectionProps {
    /** Currently selected payment method */
    paymentMethod: PaymentMethod;
    /** Callback when payment method changes */
    onPaymentMethodChange: (method: PaymentMethod) => void;
    /** Payment configuration from backend */
    paymentConfig?: PaymentConfigResource;
    /** Whether cart has recurring items */
    hasRecurring?: boolean;
    /** Whether the selector is disabled */
    disabled?: boolean;
    /** Additional className */
    className?: string;
}

/**
 * CheckoutPaymentSection - Payment method selection for dedicated checkout page
 *
 * Displays available payment methods based on backend configuration.
 * Filters out PIX/Boleto when cart has recurring items.
 *
 * @example
 * <CheckoutPaymentSection
 *     paymentMethod={selectedMethod}
 *     onPaymentMethodChange={setSelectedMethod}
 *     paymentConfig={paymentConfig}
 *     hasRecurring={hasRecurringItems}
 * />
 */
export function CheckoutPaymentSection({
    paymentMethod,
    onPaymentMethodChange,
    paymentConfig,
    hasRecurring = false,
    disabled = false,
    className,
}: CheckoutPaymentSectionProps) {
    const { t } = useLaravelReactI18n();

    // Get available methods from config or default to card only
    const availableMethods: PaymentMethod[] = paymentConfig
        ? (paymentConfig.available_methods as PaymentMethod[])
        : ['card'];

    // Filter out pix/boleto if cart has recurring items
    const effectiveAvailableMethods = hasRecurring
        ? availableMethods.filter((m) => m === 'card')
        : availableMethods;

    return (
        <Card className={cn('', className)}>
            <CardHeader className="pb-4">
                <div className="flex items-center gap-2">
                    <CreditCard className="h-5 w-5 text-muted-foreground" />
                    <CardTitle className="text-lg">
                        {t('checkout.payment_method', { default: 'Payment Method' })}
                    </CardTitle>
                </div>
                <CardDescription>
                    {t('checkout.select_payment_method', {
                        default: 'Select how you want to pay',
                    })}
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                <PaymentMethodSelector
                    value={paymentMethod}
                    onChange={onPaymentMethodChange}
                    availableMethods={effectiveAvailableMethods}
                    disabled={disabled}
                />

                {/* Recurring notice */}
                {hasRecurring && effectiveAvailableMethods.length === 1 && (
                    <p className="text-sm text-muted-foreground">
                        {t('checkout.recurring_card_only', {
                            default: 'Credit card is required for recurring subscriptions.',
                        })}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
