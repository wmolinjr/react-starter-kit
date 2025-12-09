/**
 * Common Types - Shared interfaces used across Resources
 *
 * These types are referenced by auto-generated Resource types
 * but represent inline/anonymous structures that aren't full Resources.
 *
 * NOTE: Types for enums (BillingPeriod, TenantRole, etc.) are in enums.d.ts
 * NOTE: Types for plans (PlanFeatures, PlanLimits, PlanUsage) are in plan.d.ts
 */

import type {
    TenantRole,
    FederationSyncStrategy,
    FederatedUserLinkSyncStatus,
} from './enums';

// =============================================================================
// Translation Types
// =============================================================================

/**
 * Translations object for translatable fields
 * Keys are locale codes (en, pt_BR, etc.)
 */
export interface Translations {
    en?: string;
    pt_BR?: string;
    [key: string]: string | undefined;
}

// =============================================================================
// Project Related Types
// =============================================================================

/**
 * Project attachment (file)
 */
export interface ProjectAttachment {
    id: string;
    name: string;
    size: string;
    mime_type: string;
    url: string;
}

/**
 * Project image with thumbnail
 */
export interface ProjectImage {
    id: string;
    name: string;
    size: string;
    url: string;
    thumb_url: string;
}

// =============================================================================
// Activity Log Types
// =============================================================================

/**
 * Causer info in activity log
 */
export interface ActivityCauser {
    id: string;
    name: string;
    email: string;
}

/**
 * Activity properties (changes)
 */
export interface ActivityProperties {
    old: Record<string, unknown> | null;
    new: Record<string, unknown> | null;
    extra: Record<string, unknown>;
}

// =============================================================================
// Tenant Related Types
// =============================================================================

/**
 * Simple plan info for tenant views
 */
export interface TenantPlanSummary {
    id: string;
    name: string;
}

/**
 * User info in tenant detail view
 */
export interface TenantUser {
    id: string;
    name: string;
    email: string;
    role: TenantRole | null;
}

/**
 * Invitation inviter info
 */
export interface InvitedByUser {
    id: string;
    name: string;
}

// =============================================================================
// Addon Types
// =============================================================================

/**
 * Simple addon info for plan/tenant views
 */
export interface AddonSummary {
    id: string;
    name: string;
    slug: string;
}

// =============================================================================
// Federation Types
// =============================================================================

/**
 * Tenant in federation group
 */
export interface FederationGroupTenant {
    id: string;
    name: string;
    slug: string;
    is_master: boolean;
    sync_enabled: boolean;
    joined_at: string | null;
    left_at: string | null;
    settings: Record<string, unknown>;
}

/**
 * Tenant's view of a federation group
 */
export interface TenantFederationGroup {
    id: string;
    name: string;
    description: string | null;
    sync_strategy: FederationSyncStrategy;
    is_active: boolean;
    federated_users_count: number;
    master_tenant_id: string | null;
    is_master: boolean;
    master_tenant: { id: string; name: string } | null;
    sync_enabled: boolean;
    joined_at: string | null;
    left_at: string | null;
}

/**
 * Federation group statistics (list view)
 */
export interface FederationGroupStats {
    total_users: number;
    synced_users: number;
    pending_users: number;
    conflicts_count: number;
    last_sync_at: string | null;
}

/**
 * Federation group stats for show page (detailed)
 */
export interface FederationGroupShowStats {
    total_users: number;
    active_syncs: number;
    pending_conflicts: number;
    failed_syncs: number;
}

/**
 * Federated user synced data
 */
export interface FederatedUserSyncedData {
    name: string | null;
    locale: string | null;
    two_factor_enabled: boolean;
    password_changed_at: string | null;
}

/**
 * Link between federated user and tenant user
 */
export interface FederatedUserLink {
    id: string;
    tenant_id: string;
    tenant_name: string | null;
    tenant_user_id: string | null;
    sync_status: FederatedUserLinkSyncStatus;
    sync_attempts: number;
    last_synced_at: string | null;
    last_sync_error: string | null;
    is_master: boolean;
    created_via: string;
}

// =============================================================================
// User Federation Info Types (for UserFederationInfoResource)
// =============================================================================

/**
 * Federated user info in user federation detail view
 */
export interface UserFederationInfoFederatedUser {
    id: string;
    email: string;
    synced_data: Record<string, unknown>;
    last_synced_at: string | null;
    created_at: string;
}

/**
 * Federation link info in user federation detail view
 */
export interface UserFederationInfoLink {
    id: string;
    status: string;
    sync_enabled: boolean;
    last_synced_at: string | null;
    linked_at: string;
}

/**
 * Federation group info in user federation detail view
 */
export interface UserFederationInfoGroup {
    id: string;
    name: string;
    sync_strategy: FederationSyncStrategy;
}

// =============================================================================
// Team Types
// =============================================================================

/**
 * Team usage statistics
 */
export interface TeamStats {
    max_users: number | null;
    current_users: number;
}

// =============================================================================
// Addon Subscription Types
// =============================================================================

/**
 * Tenant info in addon subscription views
 */
export interface AddonSubscriptionTenant {
    id: string;
    name: string;
}

/**
 * Addon management statistics
 */
export interface AddonManagementStats {
    total_addons: number;
    active_addons: number;
    total_revenue: number;
    tenants_with_addons: number;
}

/**
 * Revenue breakdown by addon type
 */
export interface RevenueByType {
    addon_type: string;
    addon_type_label: string;
    total: number;
    formatted_total: string;
}

// =============================================================================
// Plan/Addon Form Types (shared across plan and addon forms)
// =============================================================================

/**
 * Feature definition from backend (used in plan/addon forms)
 */
export interface FeatureDefinition {
    id: string;
    key: string;
    name: string;
    description: string | null;
    category: string | null;
    icon: string | null;
}

/**
 * Limit definition from backend (used in plan/addon forms)
 */
export interface LimitDefinition {
    id: string;
    key: string;
    name: string;
    description: string | null;
    unit: string | null;
    unit_label: string | null;
    default_value: number;
    allows_unlimited: boolean;
    icon: string | null;
}

/**
 * Category option for feature/limit grouping
 */
export interface CategoryOption {
    value: string;
    label: string;
}

/**
 * Simple addon info for plan forms
 */
export interface AddonOptionForPlan {
    id: string;
    name: string;
    slug: string;
}

/**
 * Addon type info from backend (used in addon forms)
 */
export interface AddonTypeInfo {
    value: string;
    label: string;
    description?: string;
    icon?: string;
    color?: string;
    is_stackable?: boolean;
    is_recurring?: boolean;
    is_one_time?: boolean;
    has_validity?: boolean;
}

// =============================================================================
// Dashboard Stats Types
// =============================================================================

/**
 * Central admin dashboard statistics
 */
export interface CentralDashboardStats {
    total_tenants: number;
    total_admins: number;
    total_addons: number;
    total_plans: number;
}

// =============================================================================
// Bundle Types
// =============================================================================

/**
 * Plan summary for bundle views
 */
export interface BundlePlanSummary {
    id: string;
    name: string;
    slug: string;
}
