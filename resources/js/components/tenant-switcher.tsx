import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import tenants from '@/routes/tenants';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus, Settings } from 'lucide-react';

export function TenantSwitcher() {
    const { tenant, tenants: tenantsList } = usePage<SharedData>().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    const handleSwitch = (slug: string) => {
        // Navigate to tenant subdomain
        // EnsureTenantAccess middleware will automatically update current_tenant_id
        const protocol = window.location.protocol;
        const domain = window.location.hostname.split('.').slice(-1)[0]; // Get base domain (localhost or production domain)
        const tenantUrl = `${protocol}//${slug}.${domain}`;

        window.location.href = tenantUrl;
    };

    if (!tenantsList || tenantsList.length === 0) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="group data-[state=open]:bg-sidebar-accent"
                        >
                            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                <Building2 className="size-4" />
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-semibold">
                                    {tenant ? tenant.name : 'No Workspace'}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {tenant ? tenant.slug : 'Select a workspace'}
                                </span>
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                        align="start"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'right'
                                  : 'bottom'
                        }
                        sideOffset={4}
                    >
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            Workspaces
                        </DropdownMenuLabel>
                        {tenantsList.map((t) => (
                            <DropdownMenuItem
                                key={t.id}
                                onClick={() => handleSwitch(t.slug)}
                                className="gap-2 p-2"
                            >
                                <div className="flex size-6 items-center justify-center rounded-sm border">
                                    <Building2 className="size-4 shrink-0" />
                                </div>
                                <div className="flex-1">
                                    <div className="font-medium">{t.name}</div>
                                    <div className="text-xs text-muted-foreground">{t.slug}</div>
                                </div>
                                {tenant?.id === t.id && <Check className="size-4 ml-auto" />}
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <a
                                href={tenants.index().url}
                                className="gap-2 p-2"
                            >
                                <div className="flex size-6 items-center justify-center rounded-sm border border-dashed">
                                    <Settings className="size-4" />
                                </div>
                                Manage Workspaces
                            </a>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <a
                                href={tenants.create().url}
                                className="gap-2 p-2"
                            >
                                <div className="flex size-6 items-center justify-center rounded-sm border border-dashed">
                                    <Plus className="size-4" />
                                </div>
                                Create Workspace
                            </a>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
