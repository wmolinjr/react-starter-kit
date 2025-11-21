# 12 - Integração Inertia.js/React

## Shared Props com Tenant Context

### HandleInertiaRequests.php (Completo)

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            // User autenticado
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'is_super_admin' => $request->user()->is_super_admin,
                ] : null,

                // Permissões
                'permissions' => $request->user() ? [
                    'canManageTeam' => Gate::allows('manage-team'),
                    'canManageBilling' => Gate::allows('manage-billing'),
                    'canManageSettings' => Gate::allows('manage-settings'),
                    'canCreateResources' => Gate::allows('create-resources'),
                    'role' => $request->user()->currentTenantRole(),
                    'isOwner' => $request->user()->isOwner(),
                    'isAdmin' => $request->user()->hasRole('admin'),
                    'isAdminOrOwner' => $request->user()->isAdminOrOwner(),
                ] : null,

                // Tenants do user (para switcher)
                'tenants' => $request->user() ? $request->user()->tenants->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'domain' => $t->primaryDomain()?->domain,
                ]) : [],
            ],

            // Tenant atual
            'tenant' => tenancy()->initialized ? [
                'id' => tenant('id'),
                'name' => tenant('name'),
                'slug' => current_tenant()->slug,
                'domain' => request()->getHost(),
                'settings' => current_tenant()->settings,
                'subscription' => current_tenant()->subscribed('default') ? [
                    'active' => true,
                    'on_trial' => current_tenant()->subscription('default')->onTrial(),
                ] : null,
            ] : null,

            // Flash messages
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],

            // Impersonation
            'impersonating' => session()->has('impersonating_user'),
        ]);
    }
}
```

## TypeScript Types

```typescript
// resources/js/types/index.d.ts

export interface User {
  id: number;
  name: string;
  email: string;
  is_super_admin: boolean;
}

export interface Tenant {
  id: number;
  name: string;
  slug: string;
  domain: string;
  settings: {
    branding?: {
      logo_url?: string;
      primary_color?: string;
    };
    features?: Record<string, boolean>;
    limits?: Record<string, number | null>;
  };
  subscription?: {
    active: boolean;
    on_trial: boolean;
  };
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

export interface PageProps {
  auth: {
    user: User | null;
    permissions: Permissions | null;
    tenants: Tenant[];
  };
  tenant: Tenant | null;
  flash: {
    success?: string;
    error?: string;
  };
  impersonating: boolean;
}
```

## React Hooks

### useTenant

```typescript
// resources/js/hooks/use-tenant.ts

import { usePage } from '@inertiajs/react';
import { PageProps } from '@/types';

export function useTenant() {
  const { tenant } = usePage<PageProps>().props;

  return {
    tenant,
    isInitialized: tenant !== null,
    getSetting: (key: string, defaultValue: any = null) => {
      if (!tenant) return defaultValue;
      const keys = key.split('.');
      let value: any = tenant.settings;
      for (const k of keys) {
        value = value?.[k];
      }
      return value ?? defaultValue;
    },
    hasFeature: (feature: string) => {
      return tenant?.settings?.features?.[feature] ?? false;
    },
  };
}
```

## Tenant Switcher Component

```tsx
// resources/js/components/tenant-switcher.tsx

import { usePage, router } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { PageProps } from '@/types';
import { Button } from '@/components/ui/button';
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
} from '@/components/ui/command';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { useState } from 'react';

export function TenantSwitcher() {
  const { auth, tenant } = usePage<PageProps>().props;
  const [open, setOpen] = useState(false);

  const handleSwitch = (tenantSlug: string) => {
    const selectedTenant = auth.tenants.find((t) => t.slug === tenantSlug);
    if (selectedTenant) {
      window.location.href = `https://${selectedTenant.domain}/dashboard`;
    }
  };

  if (!tenant || auth.tenants.length <= 1) {
    return null;
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          role="combobox"
          aria-expanded={open}
          className="w-[200px] justify-between"
        >
          {tenant.name}
          <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-[200px] p-0">
        <Command>
          <CommandInput placeholder="Buscar organização..." />
          <CommandEmpty>Nenhuma organização encontrada.</CommandEmpty>
          <CommandGroup>
            {auth.tenants.map((t) => (
              <CommandItem
                key={t.id}
                value={t.slug}
                onSelect={() => {
                  handleSwitch(t.slug);
                  setOpen(false);
                }}
              >
                <Check
                  className={
                    'mr-2 h-4 w-4 ' +
                    (tenant.id === t.id ? 'opacity-100' : 'opacity-0')
                  }
                />
                {t.name}
              </CommandItem>
            ))}
          </CommandGroup>
        </Command>
      </PopoverContent>
    </Popover>
  );
}
```

---

## Checklist

- [ ] `HandleInertiaRequests` configurado com tenant props
- [ ] Types TypeScript criados
- [ ] Hook `useTenant` criado
- [ ] Hook `usePermissions` criado
- [ ] Componente `TenantSwitcher` criado
- [ ] Componente `Can` criado
- [ ] SSR configurado com tenant context

---

## Próximo Passo

➡️ **[13-TESTING.md](13-TESTING.md)** - Estratégias de Teste

---

**Versão:** 1.0
**Última atualização:** 2025-11-19
