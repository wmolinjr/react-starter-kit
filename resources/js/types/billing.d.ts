/**
 * Billing Types
 *
 * Types for the unified billing experience including plans, addons, bundles, and checkout.
 * These types are used by components in @/components/shared/billing/
 */

import type { BillingPeriod, AddonType, AddonStatus } from './enums';

// =============================================================================
// Pricing Primitives
// =============================================================================

/**
 * Generic price tier for a billing period
 */
export interface PriceTier {
    price: number;
    formattedPrice: string;
    stripePriceId?: string;
}

/**
 * Pricing information for all billing periods
 */
export interface BillingPricing {
    monthly?: PriceTier & {
        yearlyEquivalent?: number;
    };
    yearly?: PriceTier & {
        monthlyEquivalent?: number;
        savingsPercent?: number;
    };
    oneTime?: PriceTier;
    metered?: PriceTier & {
        unitPriceDisplay?: string;
    };
}

/**
 * Feature item for feature lists
 */
export interface FeatureItem {
    /** Feature text/label */
    text: string;
    /** Whether the feature is included */
    included: boolean;
    /** Optional tooltip with more details */
    tooltip?: string;
    /** Optional limit value (e.g., "10 users", 100, "Unlimited") */
    limit?: string | number;
    /** Optional icon name from Lucide */
    icon?: string;
}

/**
 * Badge configuration for pricing cards
 */
export interface PricingBadge {
    text: string;
    variant: 'default' | 'popular' | 'new' | 'recommended' | 'best-value';
}

// =============================================================================
// Products (Plan, Addon, Bundle)
// =============================================================================

/**
 * Base product interface (shared by plans, addons, bundles)
 */
export interface BillingProduct {
    id: string;
    type: 'plan' | 'addon' | 'bundle';
    slug: string;
    name: string;
    description?: string;
    icon?: string;
    iconColor?: string;
    badge?: PricingBadge | string;
}

/**
 * Plan product for plan comparison and selection
 */
export interface PlanProduct extends BillingProduct {
    type: 'plan';
    pricing: BillingPricing;
    limits: Record<string, number | null>;
    features: string[];
    featureMatrix?: FeatureMatrix;
    isCurrent?: boolean;
    isUpgrade?: boolean;
    isDowngrade?: boolean;
}

/**
 * Addon product for addon catalog
 */
export interface AddonProduct extends BillingProduct {
    type: 'addon';
    addonType: AddonType;
    pricing: BillingPricing;
    unitValue?: number;
    unitLabel?: string;
    minQuantity: number;
    maxQuantity: number;
    features?: string[];
    isAvailable: boolean;
    currentQuantity: number;
    isRecurring: boolean;
    isStackable: boolean;
}

/**
 * Bundle product for bundle catalog
 */
export interface BundleProduct extends BillingProduct {
    type: 'bundle';
    pricing: BillingPricing;
    discountPercent: number;
    addons: BundleAddonItem[];
    addonCount: number;
    features?: string[];
    basePriceMonthly: number;
    basePriceYearly: number;
    savingsMonthly: number;
    savingsYearly: number;
    formattedSavingsMonthly: string;
    formattedSavingsYearly: string;
    isAvailable: boolean;
    isPurchased: boolean;
}

/**
 * Addon item within a bundle
 */
export interface BundleAddonItem {
    slug: string;
    name: string;
    type: AddonType;
    quantity: number;
    unitValue?: number;
}

// =============================================================================
// Feature Matrix (for plan comparison)
// =============================================================================

/**
 * Feature matrix for comparing plans side by side
 */
export interface FeatureMatrix {
    limits: Record<string, FeatureMatrixItem>;
    features: Record<string, FeatureMatrixItem>;
}

/**
 * Single item in feature matrix
 */
export interface FeatureMatrixItem {
    label: string;
    description?: string;
    value: boolean | string | number | null;
    formatted?: string;
    category?: string;
}

// =============================================================================
// Checkout
// =============================================================================

/**
 * Price info for a specific billing period
 */
export interface PeriodPricing {
    price: number;
    formattedPrice: string;
}

/**
 * Item in the checkout cart
 */
export interface CheckoutItem {
    id: string;
    product: BillingProduct;
    quantity: number;
    billingPeriod: BillingPeriod;
    unitPrice: number;
    totalPrice: number;
    isRecurring: boolean;
    formattedUnitPrice: string;
    formattedTotalPrice: string;
    /** Pricing for different billing periods (for dynamic price updates) */
    pricingByPeriod?: {
        monthly?: PeriodPricing;
        yearly?: PeriodPricing;
    };
}

/**
 * Plan change information for checkout
 */
export interface PlanChangeInfo {
    from: PlanProduct;
    to: PlanProduct;
    prorationAmount: number;
    formattedProration: string;
    effectiveDate: string;
}

/**
 * Complete checkout state
 */
export interface CheckoutState {
    items: CheckoutItem[];
    planChange?: PlanChangeInfo;
    billingPeriod: BillingPeriod;
    subtotal: number;
    discount: number;
    total: number;
    formattedSubtotal: string;
    formattedDiscount: string;
    formattedTotal: string;
    currency: string;
}

// =============================================================================
// Subscription Overview
// =============================================================================

/**
 * Active subscription information
 */
export interface SubscriptionInfo {
    id: string;
    status: 'active' | 'trialing' | 'past_due' | 'canceled' | 'incomplete';
    currentPeriodStart: string;
    currentPeriodEnd: string;
    cancelAtPeriodEnd: boolean;
    trialEndsAt?: string;
    endsAt?: string;
}

/**
 * Usage metric for a specific resource
 */
export interface UsageMetric {
    key: string;
    label: string;
    used: number;
    limit: number | null;
    percentage: number;
    isUnlimited: boolean;
    isNearLimit: boolean;
    isOverLimit: boolean;
    formattedUsed: string;
    formattedLimit: string;
}

/**
 * Cost breakdown by category
 */
export interface CostBreakdown {
    planCost: number;
    addonsCost: number;
    bundlesCost: number;
    totalMonthlyCost: number;
    formattedPlanCost: string;
    formattedAddonsCost: string;
    formattedBundlesCost: string;
    formattedTotal: string;
    currency: string;
}

/**
 * Next invoice preview
 */
export interface NextInvoicePreview {
    date: string;
    estimatedAmount: number;
    formattedAmount: string;
    lineItems?: {
        description: string;
        amount: number;
        formattedAmount: string;
    }[];
}

/**
 * Complete subscription overview
 */
export interface SubscriptionOverview {
    plan: PlanProduct;
    subscription: SubscriptionInfo | null;
    addons: AddonSubscriptionInfo[];
    bundles: BundleSubscriptionInfo[];
    usage: Record<string, UsageMetric>;
    costs: CostBreakdown;
    nextInvoice: NextInvoicePreview | null;
}

/**
 * Active addon subscription
 */
export interface AddonSubscriptionInfo {
    id: string;
    slug: string;
    name: string;
    type: AddonType;
    quantity: number;
    price: number;
    formattedPrice: string;
    billingPeriod: BillingPeriod;
    status: AddonStatus;
    startedAt: string | null;
    expiresAt: string | null;
    isRecurring: boolean;
    meteredUsage?: number;
}

/**
 * Active bundle subscription
 */
export interface BundleSubscriptionInfo {
    id: string;
    slug: string;
    name: string;
    price: number;
    formattedPrice: string;
    billingPeriod: BillingPeriod;
    status: AddonStatus;
    addonCount: number;
    startedAt: string | null;
}

// =============================================================================
// Component Props Interfaces
// =============================================================================

/**
 * Props for PricingCard component
 */
export interface PricingCardProps {
    title: string;
    description?: string;
    badge?: PricingBadge | string;
    price: {
        amount: number;
        currency: string;
        period?: 'monthly' | 'yearly' | 'one_time';
        originalAmount?: number;
        formattedAmount?: string;
        formattedOriginal?: string;
    };
    features: FeatureItem[];
    cta: {
        label: string;
        onClick: () => void;
        variant?: 'default' | 'outline' | 'ghost' | 'secondary';
        disabled?: boolean;
        loading?: boolean;
    };
    highlighted?: boolean;
    current?: boolean;
    className?: string;
}

/**
 * Props for PricingToggle component
 */
export interface PricingToggleProps {
    value: 'monthly' | 'yearly';
    onChange: (value: 'monthly' | 'yearly') => void;
    savings?: string;
    monthlyLabel?: string;
    yearlyLabel?: string;
    disabled?: boolean;
    className?: string;
}

/**
 * Props for FeatureList component
 */
export interface FeatureListProps {
    features: FeatureItem[];
    columns?: 1 | 2;
    maxVisible?: number;
    variant?: 'compact' | 'detailed';
    showMore?: boolean;
    className?: string;
}

/**
 * Props for PriceDisplay component
 */
export interface PriceDisplayProps {
    amount: number;
    currency?: string;
    period?: 'monthly' | 'yearly' | 'one_time' | null;
    originalAmount?: number;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    showPeriod?: boolean;
    className?: string;
}

/**
 * Props for SavingsBadge component
 */
export interface SavingsBadgeProps {
    amount?: number;
    percent?: number;
    currency?: string;
    variant?: 'default' | 'success' | 'accent';
    size?: 'sm' | 'md';
    className?: string;
}

/**
 * Props for UsageProgress component
 */
export interface UsageProgressProps {
    label: string;
    used: number;
    limit: number | null;
    formatValue?: (value: number) => string;
    showPercentage?: boolean;
    showValues?: boolean;
    warningThreshold?: number;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}
