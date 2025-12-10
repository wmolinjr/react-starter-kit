/**
 * Shared Billing Components
 *
 * Reusable components for billing, pricing, and checkout experiences.
 * Designed to be used across tenant admin, central admin, and public store.
 *
 * @example
 * // Import primitives
 * import { PricingCard, PricingToggle, FeatureList } from '@/components/shared/billing';
 *
 * // Import plan components
 * import { PlanCard, PlanComparisonTable, CurrentPlanBanner } from '@/components/shared/billing';
 *
 * // Import addon components
 * import { AddonCard, ActiveAddonCard } from '@/components/shared/billing';
 *
 * // Import bundle components
 * import { BundleCard, BundleContents, BundleSavings } from '@/components/shared/billing';
 *
 * // Import checkout components
 * import { CheckoutSheet, CheckoutLineItem, CheckoutSummary } from '@/components/shared/billing';
 *
 * // Import dashboard components
 * import { SubscriptionOverviewWidget, CostBreakdownWidget, UsageDashboard } from '@/components/shared/billing';
 *
 * // Import payment components
 * import { PixPayment, BoletoPayment, PaymentMethodSelector } from '@/components/shared/billing';
 *
 * // Import from specific category
 * import { PricingCard } from '@/components/shared/billing/primitives';
 * import { PlanCard } from '@/components/shared/billing/plans';
 * import { AddonCard } from '@/components/shared/billing/addons';
 * import { BundleCard } from '@/components/shared/billing/bundles';
 * import { CheckoutSheet } from '@/components/shared/billing/checkout';
 * import { SubscriptionOverviewWidget } from '@/components/shared/billing/dashboard';
 */

// Primitives - Base UI components
export * from './primitives';

// Plan components - Plan display and comparison
export * from './plans';

// Addon components - Add-on display and purchase
export * from './addons';

// Bundle components - Add-on bundle display
export * from './bundles';

// Checkout components - Cart and order summary
export * from './checkout';

// Dashboard components - Subscription overview and usage
export * from './dashboard';

// Payment method components - PIX, Boleto, Card selection
export { PixPayment, type PixPaymentProps } from './pix-payment';
export { BoletoPayment, type BoletoPaymentProps } from './boleto-payment';
export {
    PaymentMethodSelector,
    type PaymentMethodSelectorProps,
    type PaymentMethod,
} from './payment-method-selector';

// Cart status components - Cart indicators and warnings
export { CartStatusBadge } from './cart-status-badge';
export { CartExpiryWarning } from './cart-expiry-warning';
