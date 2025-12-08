import { ChevronRight } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useState } from 'react';

/**
 * Normalize href to pathname for comparison.
 * Handles Wayfinder URLs (//host/path) and regular paths.
 */
function toPathname(href: NavItem['href']): string {
    const url = typeof href === 'string' ? href : href.url;

    // Handle protocol-relative (//host/path) or absolute URLs
    if (url.startsWith('//') || url.startsWith('http')) {
        try {
            return new URL(url.startsWith('//') ? `https:${url}` : url).pathname;
        } catch {
            return url;
        }
    }
    return url;
}

/**
 * Check if current path matches the nav item exactly.
 * Uses exact matching to prevent multiple items being active.
 */
function isActive(currentPath: string, item: NavItem): boolean {
    return currentPath === toPathname(item.href);
}

/**
 * Check if current path is within the nav item's scope (prefix match).
 * Used to keep parent menus open for dynamic routes like /tenants/{id}.
 */
function isWithinScope(currentPath: string, item: NavItem): boolean {
    return currentPath.startsWith(toPathname(item.href));
}

/** Track which menu groups are open */
type OpenState = Record<string, boolean>;

export function NavMain({ items = [], label }: { items: NavItem[]; label?: string }) {
    const { t } = useLaravelReactI18n();
    const { url } = usePage();
    const breadcrumbs = useBreadcrumbs();

    // Use last breadcrumb for exact page matching, fallback to URL
    const lastBreadcrumb = breadcrumbs[breadcrumbs.length - 1];
    const currentPath = lastBreadcrumb?.href
        ? toPathname(lastBreadcrumb.href)
        : url.split('?')[0];

    // With Persistent Layouts, useState preserves state across navigations
    const [openMenus, setOpenMenus] = useState<OpenState>({});

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label ?? t('sidebar.platform')}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    item.items?.length ? (
                        <CollapsibleNavItem
                            key={item.title}
                            item={item}
                            currentPath={currentPath}
                            isOpen={openMenus[item.title]}
                            onToggle={(open) => setOpenMenus((prev) => ({ ...prev, [item.title]: open }))}
                        />
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isActive(currentPath, item)}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}

interface CollapsibleNavItemProps {
    item: NavItem;
    currentPath: string;
    isOpen: boolean | undefined;
    onToggle: (open: boolean) => void;
}

/**
 * Collapsible menu group with subitems.
 * Opens automatically when any subitem is active or we're in its scope.
 */
function CollapsibleNavItem({ item, currentPath, isOpen, onToggle }: CollapsibleNavItemProps) {
    // Check if any child is exactly active (for highlighting)
    const hasActiveChild = item.items?.some((sub) => isActive(currentPath, sub)) ?? false;

    // Check if we're within any child's scope (for keeping menu open on dynamic routes)
    const isInChildScope = item.items?.some((sub) => isWithinScope(currentPath, sub)) ?? false;

    // User toggle takes precedence, otherwise open if active or in scope
    const open = isOpen ?? (hasActiveChild || isInChildScope);

    return (
        <Collapsible asChild open={open} onOpenChange={onToggle} className="group/collapsible">
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton tooltip={{ children: item.title }} isActive={hasActiveChild || isInChildScope}>
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {item.items?.map((subItem) => (
                            <SidebarMenuSubItem key={subItem.title}>
                                <SidebarMenuSubButton asChild isActive={isActive(currentPath, subItem)}>
                                    <Link href={subItem.href} prefetch>
                                        <span>{subItem.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}
