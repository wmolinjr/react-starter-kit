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
 * - app/Enums/FederatedUserStatus.php
 * - app/Enums/FederatedUserLinkSyncStatus.php
 * - app/Enums/FederationConflictStatus.php
 * - app/Enums/FederationSyncStrategy.php
 */
export type AddonType = 'quota' | 'feature' | 'metered' | 'credit';

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

export type FederatedUserStatus = 'active' | 'suspended' | 'pending_review' | 'pending_master_sync';

export interface FederatedUserStatusOption {
    value: FederatedUserStatus;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    can_sync: boolean;
    is_pending: boolean;
}

export type FederatedUserLinkSyncStatus = 'synced' | 'pending_sync' | 'sync_failed' | 'conflict' | 'disabled';

export interface FederatedUserLinkSyncStatusOption {
    value: FederatedUserLinkSyncStatus;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    needs_sync: boolean;
    has_issue: boolean;
}

export type FederationConflictStatus = 'pending' | 'resolved' | 'dismissed';

export interface FederationConflictStatusOption {
    value: FederationConflictStatus;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    requires_action: boolean;
    is_terminal: boolean;
}

export type FederationSyncStrategy = 'master_wins' | 'last_write_wins' | 'manual_review';

export interface FederationSyncStrategyOption {
    value: FederationSyncStrategy;
    label: string;
    description: string;
    icon: string;
    color: string;
    creates_conflicts: boolean;
    auto_resolves: boolean;
}

