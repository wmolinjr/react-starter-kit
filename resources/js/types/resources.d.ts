/**
 * API Resource Types - Auto-generated from PHP Resources
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan types:generate
 *
 * Resources using the HasTypescriptType trait are automatically discovered
 * and their TypeScript interfaces are generated here.
 *
 * Source of truth: app/Http/Resources/ (with HasTypescriptType trait)
 */

// Import enums
import type {
    BillingPeriod,
    BadgePreset,
    TenantRole,
    FederationSyncStrategy,
    FederatedUserStatus,
    FederationConflictStatus,
} from './enums';

// Import plan types
import type { PlanFeatures, PlanLimits, PlanUsage } from './plan';

// Import common types
import type {
    Translations,
    ProjectAttachment,
    ProjectImage,
    ActivityCauser,
    ActivityProperties,
    TenantPlanSummary,
    TenantUser,
    InvitedByUser,
    AddonSummary,
    FederationGroupTenant,
    TenantFederationGroup,
    FederationGroupStats,
    FederatedUserSyncedData,
    FederatedUserLink,
} from './common';

// =============================================================================
// Shared Resources
// =============================================================================

export interface AddonOptionForPlanResource {
    id: string;
    name: string;
    slug: string;
}

export interface AddonResource {
    id: string;
    slug: string;
    name: string;
    description: string | null;
    type: AddonType;
    type_label: string;
    active: boolean;
    price_monthly: number | null;
    price_yearly: number | null;
    price_one_time: number | null;
    price_metered: number | null;
    formatted_price_monthly: string | null;
    formatted_price_yearly: string | null;
    formatted_price_one_time: string | null;
    currency: string;
    min_quantity: number;
    max_quantity: number;
    stackable: boolean;
    unit_value: number | null;
    unit_label: string | null;
    limit_key: string | null;
    features: Record<string, boolean> | null;
    icon: string | null;
    icon_color: string | null;
    badge: string | null;
    sort_order: number;
    validity_months: number | null;
}

export interface AddonSubscriptionResource {
    id: string;
    addon_slug: string;
    addon_type: AddonType;
    name: string;
    description: string | null;
    quantity: number;
    price: number;
    currency: string;
    total_price: number;
    formatted_price: string;
    formatted_total_price: string;
    billing_period: BillingPeriod;
    billing_period_label: string;
    status: AddonStatus;
    status_label: string;
    started_at: string | null;
    expires_at: string | null;
    canceled_at: string | null;
    is_active: boolean;
    is_recurring: boolean;
    is_metered: boolean;
    metered_usage: number | null;
    provider: string | null;
    provider_item_id: string | null;
    tenant: AddonSubscriptionTenant | null;
    created_at: string;
}

export interface BundleAddonResource {
    id: string;
    addon_id: string;
    slug: string;
    name: string;
    type: AddonType;
    type_label: string;
    price_monthly: number;
    quantity: number;
}

export interface BundleResource {
    id: string;
    slug: string;
    name: Translations;
    name_display: string;
    description: Translations;
    active: boolean;
    discount_percent: number;
    price_monthly: number | null;
    price_yearly: number | null;
    price_monthly_effective: number;
    price_yearly_effective: number;
    base_price_monthly: number;
    savings_monthly: number;
    badge: BadgePreset | null;
    icon: string;
    icon_color: string | null;
    features: Translations[];
    sort_order: number;
    addon_count: number;
    addons: BundleAddonResource[];
    plan_ids: string[];
    plans: BundlePlanSummary[];
    stripe_product_id: string | null;
    stripe_price_monthly_id: string | null;
    stripe_price_yearly_id: string | null;
    is_synced: boolean;
}

export interface CentralDashboardStatsResource {
    total_tenants: number;
    total_admins: number;
    total_addons: number;
    total_plans: number;
}

export interface CentralUserDetailResource {
    id: string;
    name: string;
    email: string;
    locale: string | null;
    email_verified_at: string | null;
    two_factor_confirmed_at: string | null;
    created_at: string;
    updated_at: string;
    role: string | null;
    role_display_name: string | null;
    roles: RoleResource[] | undefined;
    permissions: string[] | undefined;
    is_super_admin: boolean;
    has_2fa: boolean;
}

export interface CentralUserResource {
    id: string;
    name: string;
    email: string;
    locale: string | null;
    email_verified_at: string | null;
    two_factor_confirmed_at: string | null;
    created_at: string;
    updated_at: string;
    role: string | null;
    role_display_name: string | null;
    roles: string[] | undefined;
    permissions: string[] | undefined;
    is_super_admin: boolean;
    has_2fa: boolean;
}

export interface DomainResource {
    id: string;
    domain: string;
    is_primary: boolean;
    created_at: string;
}

export interface FederatedUserDetailResource {
    id: string;
    federation_group_id: string;
    global_email: string;
    status: FederatedUserStatus;
    sync_version: number;
    last_synced_at: string | null;
    last_sync_source: string | null;
    created_at: string;
    updated_at: string;
    synced_data: FederatedUserSyncedData;
    master_tenant: TenantSummaryResource | undefined;
    federation_group: FederationGroupResource | undefined;
    links: FederatedUserLink[] | undefined;
    links_count: number;
}

export interface FederatedUserResource {
    id: string;
    global_email: string;
    name: string | null;
    status: FederatedUserStatus;
    sync_version: number;
    last_synced_at: string | null;
    created_at: string;
    master_tenant: TenantSummaryResource | undefined;
    links_count: number;
    two_factor_enabled: boolean;
}

export interface FederationConflictResource {
    id: string;
    federated_user_id: string;
    field: string;
    conflicting_values: Record<string, unknown>[];
    status: FederationConflictStatus;
    resolved_value: unknown | null;
    resolution: string | null;
    resolved_by: string | null;
    resolved_at: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
    federated_user: FederatedUserResource | undefined;
}

export interface FederationGroupDetailResource {
    id: string;
    name: string;
    description: string | null;
    sync_strategy: FederationSyncStrategy;
    master_tenant_id: string | null;
    settings: Record<string, unknown>;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    master_tenant: TenantSummaryResource | undefined;
    tenants: FederationGroupTenant[] | undefined;
    federated_users: FederatedUserResource[] | undefined;
    tenants_count: number;
    federated_users_count: number;
    stats: FederationGroupStats | undefined;
}

export interface FederationGroupResource {
    id: string;
    name: string;
    description: string | null;
    sync_strategy: FederationSyncStrategy;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    master_tenant: TenantSummaryResource | undefined;
    tenants_count: number;
    federated_users_count: number;
}

export interface ImpersonationTenantResource {
    id: string;
    name: string;
    slug: string;
    domain: string | null;
}

export interface ImpersonationUserResource {
    id: string;
    name: string;
    email: string;
    created_at: string | null;
    roles: string[];
}

export interface PaymentAdminResource {
    id: string;
    tenant_id: string | null;
    customer_id: string | null;
    payment_method_id: string | null;
    provider: string;
    provider_payment_id: string | null;
    provider_data: Record<string, unknown> | null;
    amount: number;
    formatted_amount: string;
    currency: string;
    refunded_amount: number;
    formatted_refunded_amount: string;
    status: 'pending' | 'processing' | 'succeeded' | 'failed' | 'canceled' | 'refunded' | 'partially_refunded';
    status_label: string;
    status_color: string;
    payment_method: string | null;
    payment_method_label: string;
    description: string | null;
    metadata: Record<string, unknown> | null;
    paid_at: string | null;
    failed_at: string | null;
    refunded_at: string | null;
    created_at: string;
    tenant: { id: string; name: string } | undefined;
    customer: { id: string; name: string; email: string } | undefined;
    payment_method_details: { type: string; brand: string | null; last_four: string | null } | undefined;
    can_refund: boolean;
    refundable_amount: number;
    formatted_refundable_amount: string;
}

export interface PaymentConfigResource {
    available_methods: ('card' | 'pix' | 'boleto')[];
    default_method: 'card' | 'pix' | 'boleto';
    gateways: Record<'card' | 'pix' | 'boleto', string>;
    has_recurring_support: boolean;
}

export interface PaymentMethodResource {
    id: string;
    type: 'card' | 'pix' | 'boleto' | 'bank_transfer';
    provider: string;
    brand: string | null;
    last4: string | null;
    exp_month: number | null;
    exp_year: number | null;
    bank_name: string | null;
    is_default: boolean;
    is_verified: boolean;
    is_expired: boolean;
    display_label: string;
    expiration_display: string | null;
    created_at: string;
}

export interface PaymentResource {
    id: string;
    number: string;
    date: string;
    paid_at: string | null;
    amount: number;
    amount_formatted: string;
    currency: string;
    status: 'paid' | 'open' | 'failed' | 'refunded' | 'void';
    payment_type: 'card' | 'pix' | 'boleto';
    provider: string;
    description: string | null;
    payable_type: string | null;
    is_refundable: boolean;
    failure_message: string | null;
}

export interface PaymentSettingResource {
    id: string;
    gateway: string;
    display_name: string;
    is_enabled: boolean;
    is_sandbox: boolean;
    is_default: boolean;
    enabled_payment_types: string[];
    available_countries: string[];
    webhook_urls: Record<string, string>;
    supported_payment_types: string[];
    credential_fields: CredentialField[];
    docs_url: string;
    sandbox_url: string | null;
    production_credential_hints: Record<string, string | null>;
    sandbox_credential_hints: Record<string, string | null>;
    has_production_credentials: boolean;
    has_sandbox_credentials: boolean;
    last_tested_at: string | null;
    last_test_success: boolean | null;
    last_test_error: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface PlanDetailResource {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    price: number;
    formatted_price: string;
    currency: string;
    billing_period: BillingPeriod;
    stripe_product_id: string | null;
    stripe_price_id: string | null;
    features: PlanFeatures;
    limits: PlanLimits;
    permission_map: Record<string, string[]>;
    is_active: boolean;
    is_featured: boolean;
    badge: BadgePreset | null;
    icon: string;
    icon_color: string;
    sort_order: number;
    created_at: string;
    updated_at: string;
    tenants_count: number;
    addons: AddonSummary[] | undefined;
}

export interface PlanEditResource {
    id: string;
    name: Translations;
    name_display: string;
    slug: string;
    description: Translations;
    price: number;
    currency: string;
    billing_period: BillingPeriod;
    stripe_price_id: string | null;
    stripe_product_id: string | null;
    features: PlanFeatures;
    limits: PlanLimits;
    permission_map: Record<string, string[]>;
    is_active: boolean;
    is_featured: boolean;
    badge: BadgePreset | null;
    icon: string;
    icon_color: string;
    sort_order: number;
    addon_ids: string[];
}

export interface PlanResource {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    price: number;
    formatted_price: string;
    currency: string;
    billing_period: BillingPeriod;
    stripe_price_id: string | null;
    features: PlanFeatures;
    limits: PlanLimits;
    is_active: boolean;
    is_featured: boolean;
    badge: BadgePreset | null;
    icon: string | null;
    icon_color: string | null;
    sort_order: number;
    tenants_count: number;
    addons_count: number;
}

export interface PlanSummaryResource {
    id: string;
    name: string;
    slug: string;
    price: number;
    formatted_price: string;
    currency: string;
    billing_period: BillingPeriod;
    is_featured: boolean;
}

export interface TenantDetailResource {
    id: string;
    name: string;
    slug: string;
    settings: Record<string, unknown>;
    created_at: string;
    updated_at: string;
    domains: DomainResource[] | undefined;
    plan: TenantPlanSummary | undefined;
    addons: AddonSummary[] | undefined;
    users: TenantUser[];
    users_count: number;
    plan_features_override: Partial<PlanFeatures> | null;
    plan_limits_override: Partial<PlanLimits> | null;
    current_usage: PlanUsage | null;
    trial_ends_at: string | null;
    is_on_trial: boolean;
    federation_groups: TenantFederationGroup[] | undefined;
    federation_groups_count: number | undefined;
}

export interface TenantEditResource {
    id: string;
    name: string;
    slug: string;
    settings: Record<string, unknown>;
    domains: DomainResource[] | undefined;
    plan: TenantPlanSummary | undefined;
    plan_id: string | null;
    plan_features_override: Partial<PlanFeatures>;
    plan_limits_override: Partial<PlanLimits>;
}

export interface TenantResource {
    id: string;
    name: string;
    slug: string;
    created_at: string;
    updated_at: string;
    domains: DomainResource[] | undefined;
    plan: PlanSummaryResource | undefined;
    primary_domain: string | undefined;
    users_count: number;
    settings: Record<string, unknown> | undefined;
}

export interface TenantSummaryResource {
    id: string;
    name: string;
    slug: string;
}

export interface UserSummaryResource {
    id: string;
    name: string;
    email: string;
}

export interface ApiTokenResource {
    id: string;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    created_at: string;
}

export interface BillingPlanResource {
    slug: string;
    name: string;
    price: string;
    price_id: string;
    interval: string;
    features: string[];
    limits: { max_users: number | null; max_projects: number | null; storage_mb: number };
}

export interface FederationGroupForTenantResource {
    id: string;
    name: string;
    description: string | null;
    sync_strategy: FederationSyncStrategy;
    is_master: boolean;
    settings: Record<string, unknown>;
}

export interface FederationInfoResource {
    is_federated: boolean;
    is_master: boolean;
    group_name: string | null;
    group_id: string | null;
    sync_strategy: FederationSyncStrategy | null;
    federated_users_count: number;
    local_users_count: number;
    total_group_tenants: number;
}

export interface InvoiceDetailResource {
    id: string;
    number: string | null;
    date: string;
    date_formatted: string;
    due_date: string | null;
    total: string;
    status: string;
    paid: boolean;
    download_url: string;
    lines: { description: string; quantity: number; amount: string }[];
}

export interface InvoiceResource {
    id: string;
    date: string;
    total: string;
    download_url: string;
}

export interface MediaResource {
    id: string;
    uuid: string;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    human_readable_size: string;
    collection_name: string;
    disk: string;
    created_at: string;
    url: string;
    thumb_url: string | undefined;
    custom_properties: Record<string, unknown>;
}

export interface ProjectDetailResource {
    id: string;
    name: string;
    description: string | null;
    status: string;
    created_at: string;
    updated_at: string;
    user: UserSummaryResource | null;
    attachments: ProjectAttachment[];
    images: ProjectImage[];
}

export interface ProjectEditResource {
    id: string;
    name: string;
    description: string | null;
    status: string;
    user_id: string | null;
}

export interface ProjectResource {
    id: string;
    name: string;
    description: string | null;
    status: string;
    created_at: string;
    updated_at: string;
    user: UserSummaryResource | null;
    user_id: string | null;
    attachments_count: number | undefined;
    images_count: number | undefined;
}

export interface SubscriptionResource {
    name: string;
    status: string;
    trial_ends_at: string | null;
    ends_at: string | null;
    on_trial: boolean;
    on_grace_period: boolean;
    canceled: boolean;
}

export interface TeamMemberResource {
    id: string;
    name: string;
    email: string;
    role: TenantRole | null;
    permissions: string[];
    created_at: string;
    email_verified_at: string | null;
    is_owner: boolean;
    is_admin: boolean;
}

export interface TenantFederationMembershipResource {
    sync_enabled: boolean;
    joined_at: string | null;
    settings: Record<string, unknown>;
    default_role: string | null;
}

export interface UserFederationInfoResource {
    is_federated: boolean;
    federation_id: string | null;
    is_master_user: boolean;
    federated_user: UserFederationInfoFederatedUser | null;
    link: UserFederationInfoLink | null;
    group: UserFederationInfoGroup | null;
}

export interface UserInvitationResource {
    id: string;
    email: string;
    role: TenantRole;
    invited_at: string;
    expires_at: string;
    is_expired: boolean;
    expires_in_days: number | null;
    invited_by: InvitedByUser | undefined;
}

export interface UserResource {
    id: string;
    name: string;
    email: string;
    locale: string | null;
    department: string | null;
    employee_id: string | null;
    email_verified_at: string | null;
    two_factor_confirmed_at: string | null;
    created_at: string;
    updated_at: string;
    role: TenantRole | null;
    roles: string[] | undefined;
    permissions: string[] | undefined;
    is_owner: boolean;
    is_admin: boolean;
    has_2fa: boolean;
}

export interface UserSummaryResource {
    id: string;
    name: string;
    email: string;
}

export interface ActivityResource {
    id: string;
    description: string;
    event: string;
    log_name: string;
    subject_type: string | null;
    subject_id: string | null;
    subject_name: string | null;
    causer: ActivityCauser | undefined;
    created_at: string;
    created_at_human: string;
    created_at_formatted: string;
    properties: ActivityProperties;
}

export interface CategoryOptionResource {
    value: string;
    label: string;
}

export interface FeatureDefinitionResource {
    value: string;
    label: string;
    description: string;
    icon: string;
    color: string;
    category: string;
    permissions: string[];
    is_customizable: boolean;
}

export interface LimitDefinitionResource {
    value: string;
    label: string;
    description: string;
    icon: string;
    color: string;
    unit: string;
    unit_label: string;
    default_value: number;
    allows_unlimited: boolean;
    is_customizable: boolean;
}

export interface PermissionResource {
    id: string;
    name: string;
    description: string | null;
    category: string;
}

export interface RoleDetailResource {
    id: string;
    name: string;
    display_name: string;
    description: string | null;
    is_protected: boolean;
    permissions: PermissionResource[] | undefined;
    users: UserSummaryResource[] | undefined;
    created_at: string;
}

export interface RoleEditResource {
    id: string;
    name: string;
    display_name: Translations;
    display_name_display: string;
    description: Translations;
    is_protected: boolean;
    permission_ids: string[] | undefined;
}

export interface RoleResource {
    id: string;
    name: string;
    display_name: string;
    description: string | null;
    users_count: number;
    permissions_count: number;
    is_protected: boolean;
    created_at: string;
}

