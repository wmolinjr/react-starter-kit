import { useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import type { PaymentMethod } from '@/components/shared/billing/payment-method-selector';

interface PaymentConfig {
    stripe_enabled?: boolean;
    asaas_enabled?: boolean;
    available_methods?: PaymentMethod[];
}

interface UsePaymentMethodsOptions {
    /** Whether the cart has recurring items (subscriptions) */
    hasRecurringItems?: boolean;
    /** Country code for region-specific methods */
    countryCode?: string;
    /** Override the default available methods */
    overrideMethods?: PaymentMethod[];
}

interface UsePaymentMethodsReturn {
    /** Available payment methods based on configuration */
    availableMethods: PaymentMethod[];
    /** Whether PIX is available */
    hasPix: boolean;
    /** Whether Boleto is available */
    hasBoleto: boolean;
    /** Whether Card is available */
    hasCard: boolean;
    /** Whether multiple methods are available */
    hasMultipleMethods: boolean;
    /** Default payment method */
    defaultMethod: PaymentMethod;
}

/**
 * usePaymentMethods - Hook to manage available payment methods
 *
 * Determines which payment methods are available based on:
 * - System configuration (Stripe/Asaas enabled)
 * - Cart contents (recurring items can only use card)
 * - Country/region settings
 * - Tenant configuration
 *
 * @example
 * const { availableMethods, hasPix, defaultMethod } = usePaymentMethods({
 *     hasRecurringItems: cart.hasRecurringItems,
 * });
 *
 * <PaymentMethodSelector
 *     availableMethods={availableMethods}
 *     value={selectedMethod}
 *     onChange={setSelectedMethod}
 * />
 */
export function usePaymentMethods({
    hasRecurringItems = false,
    countryCode,
    overrideMethods,
}: UsePaymentMethodsOptions = {}): UsePaymentMethodsReturn {
    // Get payment config from page props if available
    const { props } = usePage<{
        paymentConfig?: PaymentConfig;
    }>();

    const paymentConfig = props.paymentConfig;

    // Determine base available methods
    const baseMethods = useMemo<PaymentMethod[]>(() => {
        // If override methods are provided, use them
        if (overrideMethods && overrideMethods.length > 0) {
            return overrideMethods;
        }

        // If config provides available methods, use them
        if (paymentConfig?.available_methods && paymentConfig.available_methods.length > 0) {
            return paymentConfig.available_methods;
        }

        // Default: all methods available
        const methods: PaymentMethod[] = [];

        // Card is always available if Stripe is enabled
        const stripeEnabled = paymentConfig?.stripe_enabled ?? true;
        if (stripeEnabled) {
            methods.push('card');
        }

        // PIX and Boleto are available if Asaas is enabled
        const asaasEnabled = paymentConfig?.asaas_enabled ?? true;
        if (asaasEnabled) {
            methods.push('pix', 'boleto');
        }

        // If no methods are available from config, default to all
        if (methods.length === 0) {
            return ['card', 'pix', 'boleto'];
        }

        return methods;
    }, [overrideMethods, paymentConfig]);

    // Filter methods based on cart contents and region
    const availableMethods = useMemo<PaymentMethod[]>(() => {
        let methods = [...baseMethods];

        // Recurring items (subscriptions) can only use card
        if (hasRecurringItems) {
            methods = methods.filter((m) => m === 'card');
        }

        // PIX and Boleto are only available in Brazil
        if (countryCode && countryCode !== 'BR') {
            methods = methods.filter((m) => m === 'card');
        }

        // Ensure at least card is available
        if (methods.length === 0 && baseMethods.includes('card')) {
            methods = ['card'];
        }

        return methods;
    }, [baseMethods, hasRecurringItems, countryCode]);

    // Computed properties
    const hasPix = availableMethods.includes('pix');
    const hasBoleto = availableMethods.includes('boleto');
    const hasCard = availableMethods.includes('card');
    const hasMultipleMethods = availableMethods.length > 1;

    // Default method (prefer card for reliability)
    const defaultMethod: PaymentMethod = hasCard ? 'card' : availableMethods[0] ?? 'card';

    return {
        availableMethods,
        hasPix,
        hasBoleto,
        hasCard,
        hasMultipleMethods,
        defaultMethod,
    };
}

/**
 * Check if a payment method supports recurring billing
 */
export function supportsRecurring(method: PaymentMethod): boolean {
    return method === 'card';
}

/**
 * Check if a payment method is async (requires polling/webhook)
 */
export function isAsyncPaymentMethod(method: PaymentMethod): boolean {
    return method === 'pix' || method === 'boleto';
}

/**
 * Get the estimated processing time for a payment method
 */
export function getProcessingTime(method: PaymentMethod): string {
    switch (method) {
        case 'card':
            return 'instant';
        case 'pix':
            return '30 minutes';
        case 'boleto':
            return '1-3 business days';
        default:
            return 'varies';
    }
}
