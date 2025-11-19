import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

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
    id: string;
    name: string;
    slug: string;
    role: string | null;
    is_current: boolean;
}

export interface Permissions {
    canManageTeam: boolean;
    canManageBilling: boolean;
    canManageSettings: boolean;
    canCreateResources: boolean;
    role: string | null;
    isOwner: boolean;
    isAdmin: boolean;
    isAdminOrOwner: boolean;
}

export interface Auth {
    user: User | null;
    tenants: TenantInfo[];
    permissions: Permissions | null;
}

export interface TenantSubscription {
    name: string;
    active: boolean;
    on_trial: boolean;
    ends_at: string | null;
    trial_ends_at: string | null;
}

export interface Tenant {
    id: string;
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
    auth: Auth;
    tenant: Tenant | null;
    flash: FlashMessages;
    sidebarOpen: boolean;
    impersonation: Impersonation;
    [key: string]: unknown;
}

// Deprecated: Use PageProps instead
export interface SharedData extends PageProps {}
