import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';

import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CreditCard,
    Folder,
    FolderOpen,
    LayoutGrid,
    Settings,
    Users
} from 'lucide-react';
import AppLogo from './app-logo';
import { usePermissions } from '@/hooks/use-permissions';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function TenantSidebar() {
    const { has } = usePermissions();

    // Build navigation items based on permissions
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
            icon: LayoutGrid,
        },
    ];

    // Projects section
    if (has('tenant.projects:view')) {
        mainNavItems.push({
            title: 'Projetos',
            href: '/projects',
            icon: FolderOpen,
        });
    }

    // Team section
    if (has('tenant.team:view')) {
        const teamItems: NavItem[] = [];

        if (has('tenant.team:view')) {
            teamItems.push({
                title: 'Membros',
                href: '/team',
            });
        }

        if (has('tenant.team:activity')) {
            teamItems.push({
                title: 'Atividades',
                href: '/team/activity',
            });
        }

        mainNavItems.push({
            title: 'Equipe',
            href: '/team',
            icon: Users,
            items: teamItems.length > 0 ? teamItems : undefined,
        });
    }

    // Billing section
    if (has('tenant.billing:view')) {
        const billingItems: NavItem[] = [];

        if (has('tenant.billing:view')) {
            billingItems.push({
                title: 'Assinatura',
                href: '/billing',
            });
        }

        if (has('tenant.billing:invoices')) {
            billingItems.push({
                title: 'Faturas',
                href: '/billing/invoices',
            });
        }

        mainNavItems.push({
            title: 'Cobrança',
            href: '/billing',
            icon: CreditCard,
            items: billingItems.length > 0 ? billingItems : undefined,
        });
    }

    // Settings section
    if (has('tenant.settings:view')) {
        const settingsItems: NavItem[] = [];

        if (has('tenant.settings:view')) {
            settingsItems.push({
                title: 'Geral',
                href: '/settings',
            });
        }

        if (has('tenant.apiTokens:view')) {
            settingsItems.push({
                title: 'API Tokens',
                href: '/settings/api-tokens',
            });
        }

        if (has('tenant.settings:danger')) {
            settingsItems.push({
                title: 'Zona de Perigo',
                href: '/settings/danger',
            });
        }

        mainNavItems.push({
            title: 'Configurações',
            href: '/settings',
            icon: Settings,
            items: settingsItems.length > 0 ? settingsItems : undefined,
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
