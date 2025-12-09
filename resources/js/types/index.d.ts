import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';
import type { Auth } from './permissions';
import type { PlanFeatures, PlanLimits, PlanUsage } from './plan';

// Re-export all auto-generated types
export * from './permissions';
export * from './plan';
export * from './enums';
export * from './resources';
export * from './pagination';
export * from './common';

export interface User {
    id: string; // UUID
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

export interface TenantInfo {
    id: string; // UUID
    name: string;
    slug: string;
    role: string | null;
    is_current: boolean;
}

/**
 * Extended Auth with User type and tenant info.
 *
 * OPTION C ARCHITECTURE:
 * - Users exist ONLY in tenant databases (complete isolation)
 * - A user belongs to exactly one tenant (the database they're in)
 * - No tenants list - just current tenant info
 *
 * DUAL GUARD SYSTEM:
 * - 'central' guard: Central admins (Central\User) - isSuperAdmin available
 * - 'tenant' guard: Tenant users (Tenant\User)
 */
export interface ExtendedAuth extends Auth<User> {
    tenant: TenantInfo | null;
    /** Only present for central users (guard === 'central') */
    isSuperAdmin?: boolean;
    /** Which authentication guard is active */
    guard: 'central' | 'tenant' | null;
}

export interface TenantSubscription {
    name: string;
    active: boolean;
    on_trial: boolean;
    ends_at: string | null;
    trial_ends_at: string | null;
}

export interface Plan {
    id: string; // UUID
    name: string;
    slug: string;
    description: string;
    price: number;
    formatted_price: string;
    features: PlanFeatures;
    limits: PlanLimits;
    usage: PlanUsage;
    is_on_trial: boolean;
    trial_ends_at: string | null;
}

export interface Tenant {
    id: string; // UUID
    name: string;
    slug: string;
    domain: string;
    settings: Record<string, unknown> | null;
    subscription: TenantSubscription | null;
    plan: Plan | null;
}

export interface Impersonation {
    isImpersonating: boolean;
    isAdminMode: boolean;
    impersonatingTenant?: string | null;
    impersonatingUser?: string | null; // UUID
}

export interface FlashMessages {
    success?: string | null;
    error?: string | null;
    warning?: string | null;
    info?: string | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    items?: NavItem[];
}

export interface LocaleConfig {
    locale: string;
    fallbackLocale: string;
    availableLocales: string[];
    localeLabels: Record<string, string>;
}

export interface CurrencyConfig {
    code: string;
    symbol: string;
    locale: string;
}

export interface EnumOption {
    value: string;
    label: string;
    description: string;
}

/**
 * Grouped permissions by category.
 * Used in role forms for permission assignment.
 *
 * Uses PermissionResource from auto-generated types.
 * Note: The `category` field in PermissionResource is not used here
 * since permissions are already grouped by category key.
 */
export interface CategoryPermissions {
    label: string;
    permissions: PermissionResource[];
}

export interface PageProps extends LocaleConfig {
    name: string;
    quote: { message: string; author: string };
    auth: ExtendedAuth;
    tenant: Tenant | null;
    flash: FlashMessages;
    sidebarOpen: boolean;
    impersonation: Impersonation;
    currency: CurrencyConfig;
    [key: string]: unknown;
}

// Deprecated: Use PageProps instead
// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface SharedData extends PageProps {}
