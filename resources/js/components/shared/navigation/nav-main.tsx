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
import { resolveUrl } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useState } from 'react';

/**
 * Extract pathname from URL (handles both relative and absolute URLs).
 * Wayfinder returns URLs like "//localhost/admin" but page.url is "/admin".
 */
function getPathname(url: string): string {
    // Handle protocol-relative URLs (//localhost/path) or full URLs
    if (url.startsWith('//') || url.startsWith('http')) {
        try {
            const fullUrl = url.startsWith('//') ? `https:${url}` : url;
            return new URL(fullUrl).pathname;
        } catch {
            return url;
        }
    }
    return url;
}

/**
 * Check if current URL matches the nav item href.
 * Following Inertia.js docs pattern: url.startsWith(href)
 * @see https://inertiajs.com/links#active-states
 */
function isActiveRoute(url: string, item: NavItem): boolean {
    const resolved = getPathname(resolveUrl(item.href));
    const currentPath = url.split('?')[0];

    // Prefix match (Inertia pattern: url.startsWith('/users'))
    return currentPath.startsWith(resolved);
}

/**
 * Check if any subitem is active (for collapsible menus).
 * This ensures parent menus stay open when navigating between subitems.
 */
function hasActiveSubitem(url: string, item: NavItem): boolean {
    if (!item.items || item.items.length === 0) {
        return false;
    }

    return item.items.some((subItem) => isActiveRoute(url, subItem));
}

/**
 * State for tracking which menu items are open.
 * Keys are menu titles, values are open state.
 */
type OpenMenuState = Record<string, boolean>;

export function NavMain({ items = [], label }: { items: NavItem[]; label?: string }) {
    const { t } = useLaravelReactI18n();
    const displayLabel = label ?? t('sidebar.platform');
    const page = usePage();

    // With Persistent Layouts, this component doesn't remount during navigation
    // so useState is sufficient to preserve menu state
    const [openMenus, setOpenMenus] = useState<OpenMenuState>({});

    const handleOpenChange = (title: string, isOpen: boolean) => {
        setOpenMenus((prev) => ({ ...prev, [title]: isOpen }));
    };

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{displayLabel}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    item.items && item.items.length > 0 ? (
                        <CollapsibleNavItem
                            key={item.title}
                            item={item}
                            currentUrl={page.url}
                            isOpen={openMenus[item.title]}
                            onOpenChange={(isOpen) => handleOpenChange(item.title, isOpen)}
                        />
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isActiveRoute(page.url, item)}
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
    currentUrl: string;
    isOpen: boolean | undefined;
    onOpenChange: (isOpen: boolean) => void;
}

/**
 * Collapsible nav item with controlled open state.
 * With Persistent Layouts, state is naturally preserved across navigations.
 * Falls back to keeping menu open when any subitem is active.
 */
function CollapsibleNavItem({ item, currentUrl, isOpen, onOpenChange }: CollapsibleNavItemProps) {
    const hasActiveChild = hasActiveSubitem(currentUrl, item);

    // Determine open state:
    // 1. If user has explicitly toggled (isOpen is defined), use that
    // 2. Otherwise, open if any subitem is active
    const effectiveOpen = isOpen ?? hasActiveChild;

    return (
        <Collapsible
            asChild
            open={effectiveOpen}
            onOpenChange={onOpenChange}
            className="group/collapsible"
        >
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton tooltip={{ children: item.title }} isActive={hasActiveChild}>
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {item.items?.map((subItem) => (
                            <SidebarMenuSubItem key={subItem.title}>
                                <SidebarMenuSubButton asChild isActive={isActiveRoute(currentUrl, subItem)}>
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
