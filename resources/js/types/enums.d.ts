/**
 * Enum Types - Auto-generated from PHP Enums
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan enums:generate-types
 *
 * Source of truth:
 * - app/Enums/AddonType.php
 * - app/Enums/AddonStatus.php
 * - app/Enums/BillingPeriod.php
 */
export type AddonType = 'storage' | 'users' | 'projects' | 'feature' | 'bandwidth' | 'api_calls';

export interface AddonTypeOption {
    value: AddonType;
    label: string;
    description: string;
    icon: string;
    color: string;
    category: string;
    unit: string;
    unit_label: string;
    is_metered: boolean;
    is_stackable: boolean;
    limit_key: string | null;
    increases_limit: boolean;
}

export type AddonStatus = 'pending' | 'active' | 'canceled' | 'expired' | 'failed';

export interface AddonStatusOption {
    value: AddonStatus;
    label: string;
    description: string;
    icon: string;
    color: string;
    is_usable: boolean;
    is_terminal: boolean;
}

export type BillingPeriod = 'monthly' | 'yearly' | 'one_time' | 'metered' | 'manual';

export interface BillingPeriodOption {
    value: BillingPeriod;
    label: string;
    description: string;
    icon: string;
    color: string;
    is_recurring: boolean;
}

