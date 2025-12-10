/**
 * Billing Hooks
 *
 * Custom hooks for billing functionality including:
 * - Billing period state (monthly/yearly toggle)
 * - Checkout cart state
 * - Payment methods availability
 *
 * These hooks use React Context for state management,
 * which integrates well with Inertia.js and Laravel.
 */

export {
    BillingPeriodProvider,
    useBillingPeriod,
    useBillingPeriodSafe,
    getBillingPeriodPrice,
    calculateYearlySavings,
} from './use-billing-period';

export {
    CheckoutProvider,
    useCheckout,
    useCheckoutSafe,
    createCheckoutItem,
} from './use-checkout';

export {
    usePaymentMethods,
    supportsRecurring,
    isAsyncPaymentMethod,
    getProcessingTime,
} from './use-payment-methods';
