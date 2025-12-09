/**
 * Addon Types - API Resource interfaces
 *
 * Enum types (AddonType, AddonStatus, BillingPeriod) are in @/types/enums
 * Generated from PHP enums via: sail artisan enums:generate-types
 */
import type { AddonType } from './enums';

export interface AddonSubscription {
    id: string; // UUID
    slug: string;
    name: string;
    type: string;
    quantity: number;
    price: string;
    total_price: string;
    billing_period: string;
    status: string;
    started_at: string | null;
    expires_at: string | null;
    is_recurring: boolean;
    is_metered: boolean;
    metered_usage?: number;
}

export interface AddonBillingTier {
    price: number;
    formatted_price: string;
    stripe_price_id?: string;
}

export interface AddonCatalogItem {
    slug: string;
    name: string;
    description: string;
    type: AddonType;
    unit_value?: number;
    billing: {
        monthly?: AddonBillingTier;
        yearly?: AddonBillingTier;
        one_time?: AddonBillingTier;
        metered?: AddonBillingTier & { unit_price_display?: string };
    };
    available_for_plans: string[];
    min_quantity: number;
    max_quantity: number;
    features?: string[];
    badge?: string;
    is_available: boolean;
    current_quantity: number;
}

export interface AddonUsage {
    type: string;
    used: number;
    limit: number;
    percentage: number;
    overage?: number;
}

export interface AddonsData {
    active: AddonSubscription[];
    catalog: AddonCatalogItem[];
    usage: Record<string, AddonUsage>;
    monthly_cost: number;
    formatted_monthly_cost: string;
}
