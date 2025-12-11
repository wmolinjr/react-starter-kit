/**
 * Checkout Components
 *
 * Components for the checkout cart, order summary, and dedicated checkout page.
 *
 * @example
 * import { CheckoutSheet, CheckoutPaymentSheet, CheckoutLineItem, CheckoutSummary } from '@/components/shared/billing/checkout';
 *
 * // For dedicated checkout page sections:
 * import { CheckoutCartSection, CheckoutPaymentSection, CheckoutSummarySection } from '@/components/shared/billing/checkout';
 */

export {
    CheckoutLineItem,
    CheckoutLineItemSkeleton,
    type CheckoutLineItemProps,
} from './checkout-line-item';

export {
    CheckoutSummary,
    CheckoutSummarySkeleton,
    type CheckoutSummaryProps,
} from './checkout-summary';

export {
    CheckoutSheet,
    CheckoutCartButton,
    type CheckoutSheetProps,
    type CheckoutCartButtonProps,
} from './checkout-sheet';

export {
    CheckoutPaymentSheet,
    type CheckoutPaymentSheetProps,
} from './checkout-payment-sheet';

// Dedicated checkout page sections
export {
    CheckoutCartSection,
    type CheckoutCartSectionProps,
} from './checkout-cart-section';

export {
    CheckoutPaymentSection,
    type CheckoutPaymentSectionProps,
} from './checkout-payment-section';

export {
    CheckoutSummarySection,
    type CheckoutSummarySectionProps,
} from './checkout-summary-section';

export {
    CheckoutBenefitsSection,
    type CheckoutBenefitsSectionProps,
} from './checkout-benefits-section';

export {
    CheckoutPoliciesSection,
    type CheckoutPoliciesSectionProps,
} from './checkout-policies-section';
