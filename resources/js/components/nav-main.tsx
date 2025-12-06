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

export function NavMain({ items = [], label }: { items: NavItem[]; label?: string }) {
    const { t } = useLaravelReactI18n();
    const displayLabel = label ?? t('sidebar.platform');
    const page = usePage();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{displayLabel}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    item.items && item.items.length > 0 ? (
                        <Collapsible
                            key={item.title}
                            asChild
                            defaultOpen={isActiveRoute(page.url, item)}
                            className="group/collapsible"
                        >
                            <SidebarMenuItem>
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton
                                        tooltip={{ children: item.title }}
                                        isActive={isActiveRoute(page.url, item)}
                                    >
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenuSub>
                                        {item.items.map((subItem) => (
                                            <SidebarMenuSubItem key={subItem.title}>
                                                <SidebarMenuSubButton
                                                    asChild
                                                    isActive={page.url === resolveUrl(subItem.href)}
                                                >
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
