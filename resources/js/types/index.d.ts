import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';
import type { Auth as AuthType } from './permissions';

// Re-export types from permissions.d.ts
export type { Permission, Role, Auth, PermissionCategory, PermissionAction } from './permissions';

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    is_super_admin: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

export interface TenantInfo {
    id: number;
    name: string;
    slug: string;
    role: string | null;
    is_current: boolean;
}

/**
 * Extended Auth with tenants list
 * Combines auto-generated Auth type with tenant info
 */
export interface ExtendedAuth extends AuthType {
    tenants: TenantInfo[];
}

export interface TenantSubscription {
    name: string;
    active: boolean;
    on_trial: boolean;
    ends_at: string | null;
    trial_ends_at: string | null;
}

export interface Tenant {
    id: number;
    name: string;
    slug: string;
    domain: string;
    settings: Record<string, unknown> | null;
    subscription: TenantSubscription | null;
}

export interface Impersonation {
    isImpersonating: boolean;
    impersonatingTenant: string | null;
    impersonatingUser: number | null;
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
}

export interface PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: ExtendedAuth;
    tenant: Tenant | null;
    flash: FlashMessages;
    sidebarOpen: boolean;
    impersonation: Impersonation;
    [key: string]: unknown;
}

// Deprecated: Use PageProps instead
export interface SharedData extends PageProps {}
