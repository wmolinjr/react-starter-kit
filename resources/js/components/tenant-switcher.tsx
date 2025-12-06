import { Building2, Check, ChevronsUpDown } from 'lucide-react';
import { useTenant } from '@/hooks/use-tenant';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export function TenantSwitcher() {
    const { tenant, tenants, hasTenant } = useTenant();

    if (!hasTenant) {
        return null;
    }

    const handleTenantSwitch = (slug: string) => {
        // Redirect to the tenant's domain
        const protocol = window.location.protocol;
        const port = window.location.port ? `:${window.location.port}` : '';
        const baseDomain = window.location.hostname.split('.').slice(-2).join('.');

        // Build the new URL: protocol://slug.basedomain:port/dashboard
        const newUrl = `${protocol}//${slug}.${baseDomain}${port}/dashboard`;

        // Use full page reload to reinitialize tenant context
        // eslint-disable-next-line react-hooks/immutability
        window.location.href = newUrl;
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    className="w-full justify-between"
                >
                    <div className="flex items-center gap-2">
                        <Building2 className="h-4 w-4 shrink-0 opacity-50" />
                        <span className="truncate">{tenant?.name || 'Select Tenant'}</span>
                    </div>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-[240px]" align="start">
                <DropdownMenuLabel>Switch Tenant</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {tenants.map((t) => (
                    <DropdownMenuItem
                        key={t.id}
                        onSelect={() => {
                            if (!t.is_current) {
                                handleTenantSwitch(t.slug);
                            }
                        }}
                        className={cn(
                            'flex items-center justify-between cursor-pointer',
                            t.is_current && 'bg-accent'
                        )}
                    >
                        <div className="flex flex-col">
                            <span className="font-medium">{t.name}</span>
                            <span className="text-xs text-muted-foreground">{t.role}</span>
                        </div>
                        {t.is_current && <Check className="h-4 w-4" />}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
