import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
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

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    tenant: Tenant | null;
    tenants: TenantListItem[];
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export type TenantRole = 'owner' | 'admin' | 'member';
export type TenantStatus = 'active' | 'inactive' | 'suspended';

export interface TenantListItem {
    id: number;
    name: string;
    slug: string;
    role: TenantRole;
}

export type DomainVerificationStatus = 'pending' | 'verified' | 'failed';

export interface Domain {
    id: number;
    tenant_id: number;
    domain: string;
    is_primary: boolean;
    verification_status: DomainVerificationStatus;
    verification_token: string | null;
    verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Tenant {
    id: number;
    name: string;
    slug: string;
    domain?: string | null;
    settings?: Record<string, unknown> | null;
    status: TenantStatus;
    created_at: string;
    domains?: Domain[];
    description?: string | null;
    logo?: string | null;
    favicon?: string | null;
    primary_color?: string | null;
    logo_url?: string | null;
    favicon_url?: string | null;
}

export interface TenantWithUsers extends Tenant {
    users: TenantUser[];
}

export interface TenantUser {
    id: number;
    name: string;
    email: string;
    role: TenantRole;
    joined_at: string;
}

export type InvitationStatus = 'pending' | 'accepted' | 'expired';

export interface TenantInvitation {
    id: number;
    tenant_id: number;
    email: string;
    role: TenantRole;
    token: string;
    invited_by: number;
    inviter?: {
        id: number;
        name: string;
        email: string;
    };
    accepted_at: string | null;
    expires_at: string;
    created_at: string;
    updated_at: string;
}

export interface TenantIndexItem {
    id: number;
    name: string;
    slug: string;
    domain?: string | null;
    status: TenantStatus;
    role: TenantRole;
    users_count: number;
    created_at: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

// Page Builder Types
export type PageStatus = 'draft' | 'published' | 'archived';

export type BlockType =
    | 'hero'
    | 'text'
    | 'image'
    | 'gallery'
    | 'cta'
    | 'features'
    | 'testimonials';

export interface PageBlock {
    id: number;
    block_type: BlockType;
    content: Record<string, any>;
    config?: Record<string, any>;
    order: number;
}

export interface PageListItem {
    id: number;
    title: string;
    slug: string;
    status: PageStatus;
    published_at?: string | null;
    blocks_count: number;
    created_by?: {
        id: number;
        name: string;
    } | null;
    created_at: string;
    updated_at: string;
}

export interface Page {
    id: number;
    title: string;
    slug: string;
    content?: Record<string, any> | null;
    status: PageStatus;
    meta_title?: string | null;
    meta_description?: string | null;
    meta_keywords?: string | null;
    og_image?: string | null;
    published_at?: string | null;
    blocks: PageBlock[];
    created_by?: {
        id: number;
        name: string;
        email: string;
    } | null;
    created_at: string;
    updated_at: string;
}

export interface PageTemplate {
    id: number;
    name: string;
    description?: string | null;
    thumbnail?: string | null;
    category?: string | null;
    blocks: Array<{
        block_type: BlockType;
        content: Record<string, any>;
        config?: Record<string, any>;
    }>;
}
