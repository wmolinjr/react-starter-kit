# Frontend Types Migration Plan

Este documento descreve o plano de implementação para completar a migração do frontend para usar tipos TypeScript auto-gerados pelo backend como fonte única de verdade.

## Contexto

O comando `sail artisan types:generate` gera automaticamente interfaces TypeScript a partir dos API Resources PHP. Isso garante:

- **Fonte única de verdade**: Tipos definidos no backend, consumidos no frontend
- **Sincronização automática**: Mudanças no backend refletem automaticamente no frontend
- **Type-safety**: Erros de tipo detectados em compile-time

## Status Atual

### Arquivos de Tipos Gerados

```
resources/js/types/
├── resources.d.ts    # API Resources auto-gerados
├── enums.d.ts        # Enums auto-gerados
├── plan.d.ts         # PlanFeatures, PlanLimits, PlanUsage
├── common.d.ts       # Tipos auxiliares (Translations, ActivityProperties, etc.)
├── pagination.d.ts   # Tipos de paginação
├── permissions.d.ts  # Tipos de permissões
├── addons.d.ts       # Tipos de addons
└── index.d.ts        # Re-exports e tipos globais (PageProps, etc.)
```

### Páginas Já Migradas

- `central/admin/plans/index.tsx` → `PlanResource`
- `central/admin/roles/index.tsx` → `RoleResource`
- `central/admin/tenants/index.tsx` → `TenantResource` + extensões
- `central/admin/federation/index.tsx` → `FederationGroupResource`
- `tenant/admin/projects/index.tsx` → `ProjectResource`
- `tenant/admin/projects/show.tsx` → `ProjectDetailResource`
- `tenant/admin/projects/edit.tsx` → `ProjectEditResource`
- `tenant/admin/team/index.tsx` → `TeamMemberResource` (extended)
- `tenant/admin/audit/index.tsx` → `ActivityResource`
- `tenant/admin/settings/roles/index.tsx` → `RoleResource`

---

## Task 1: Criar CentralUserResource

### Problema

A página `central/admin/users/index.tsx` usa um tipo `CentralUser` inline porque não existe um Resource para usuários centrais (admins).

### Arquivos a Criar/Modificar

#### 1.1 Criar o Resource PHP

**Arquivo**: `app/Http/Resources/Central/CentralUserResource.php`

```php
<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Traits\HasTypescriptType;
use Illuminate\Http\Request;

class CentralUserResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->formatIso($this->email_verified_at),
            'two_factor_confirmed_at' => $this->formatIso($this->two_factor_confirmed_at),
            'is_super_admin' => $this->is_super_admin,
            'created_at' => $this->formatIso($this->created_at),
            'updated_at' => $this->formatIso($this->updated_at),
            // Role info
            'role' => $this->roles->first()?->name,
            'role_display_name' => $this->roles->first()?->display_name,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->getAllPermissions()->pluck('name')),
        ];
    }

    /**
     * TypeScript type definition.
     */
    public static function typescriptType(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'email' => 'string',
            'email_verified_at' => 'string | null',
            'two_factor_confirmed_at' => 'string | null',
            'is_super_admin' => 'boolean',
            'created_at' => 'string',
            'updated_at' => 'string',
            'role' => 'string | null',
            'role_display_name' => 'string | null',
            'roles' => 'string[] | undefined',
            'permissions' => 'string[] | undefined',
        ];
    }
}
```

#### 1.2 Criar CentralUserDetailResource (opcional)

**Arquivo**: `app/Http/Resources/Central/CentralUserDetailResource.php`

```php
<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Shared\RoleResource;
use App\Traits\HasTypescriptType;
use Illuminate\Http\Request;

class CentralUserDetailResource extends BaseResource
{
    use HasTypescriptType;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->formatIso($this->email_verified_at),
            'two_factor_confirmed_at' => $this->formatIso($this->two_factor_confirmed_at),
            'is_super_admin' => $this->is_super_admin,
            'created_at' => $this->formatIso($this->created_at),
            'updated_at' => $this->formatIso($this->updated_at),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'activity_count' => $this->whenCounted('activities'),
        ];
    }

    public static function typescriptType(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'email' => 'string',
            'email_verified_at' => 'string | null',
            'two_factor_confirmed_at' => 'string | null',
            'is_super_admin' => 'boolean',
            'created_at' => 'string',
            'updated_at' => 'string',
            'roles' => 'RoleResource[] | undefined',
            'activity_count' => 'number | undefined',
        ];
    }
}
```

#### 1.3 Atualizar Controller

**Arquivo**: `app/Http/Controllers/Central/Admin/UserController.php`

```php
use App\Http\Resources\Central\CentralUserResource;

public function index(Request $request)
{
    $users = User::query()
        ->with('roles')
        ->when($request->search, fn ($q, $search) =>
            $q->where('name', 'ilike', "%{$search}%")
              ->orWhere('email', 'ilike', "%{$search}%")
        )
        ->orderBy('name')
        ->paginate(15);

    return Inertia::render('central/admin/users/index', [
        'users' => CentralUserResource::collection($users),
        'filters' => $request->only('search'),
    ]);
}
```

#### 1.4 Regenerar Types

```bash
sail artisan types:generate
```

#### 1.5 Atualizar Frontend

**Arquivo**: `resources/js/pages/central/admin/users/index.tsx`

```tsx
import { type BreadcrumbItem, type CentralUserResource } from '@/types';

interface Props {
    users: {
        data: CentralUserResource[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
    };
    filters: { search?: string };
}
```

### Verificação

```bash
sail artisan types:generate
npm run types
```

---

## Task 2: Migrar Páginas Restantes

### 2.1 Páginas Central Admin

| Arquivo | Tipo Inline | Substituir Por |
|---------|-------------|----------------|
| `central/admin/users/show.tsx` | `interface User` | `CentralUserDetailResource` |
| `central/admin/roles/show.tsx` | `interface User, Role` | `UserSummaryResource`, `RoleDetailResource` |
| `central/admin/roles/edit.tsx` | `interface Role` | `RoleEditResource` |
| `central/admin/federation/show.tsx` | `interface Tenant` | `TenantSummaryResource` |
| `central/admin/federation/create.tsx` | `interface Tenant` | `TenantSummaryResource` |
| `central/admin/federation/edit.tsx` | `interface Tenant` | `TenantSummaryResource` |
| `central/admin/bundles/index.tsx` | `interface Plan` | `PlanSummaryResource` |

### 2.2 Páginas Tenant Admin

| Arquivo | Tipo Inline | Substituir Por |
|---------|-------------|----------------|
| `tenant/admin/settings/index.tsx` | `interface Tenant` | `TenantSummaryResource` |
| `tenant/admin/settings/roles/show.tsx` | `interface User, Role` | `UserSummaryResource`, `RoleDetailResource` |
| `tenant/admin/settings/roles/edit.tsx` | `interface Role` | `RoleEditResource` |
| `tenant/admin/team/activity.tsx` | `interface Tenant` | `TenantSummaryResource` |
| `tenant/admin/billing/index.tsx` | `interface Plan` | Criar `BillingPlanResource` |
| `tenant/admin/billing/invoices.tsx` | `interface Tenant` | `TenantSummaryResource` |

### 2.3 Componentes

| Arquivo | Tipo Inline | Substituir Por |
|---------|-------------|----------------|
| `central/admin/federation/components/federation-group-form.tsx` | `interface Tenant` | `TenantSummaryResource` |
| `central/admin/federation/components/change-master-dialog.tsx` | `interface Tenant` | `TenantSummaryResource` |

### Padrão de Migração

Para cada arquivo:

1. **Identificar o tipo inline**
2. **Verificar se existe Resource equivalente** em `resources.d.ts`
3. **Se existir**: Importar e usar diretamente
4. **Se não existir**:
   - Criar Resource no backend (se fizer sentido)
   - Ou criar tipo local que estende o Resource existente
5. **Rodar verificação**: `npm run types`

---

## Task 3: Padronizar Paginação

### Problema

Várias páginas definem estruturas de paginação inline repetidamente:

```tsx
interface Props {
    items: {
        data: Item[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
    };
}
```

### Solução

Usar os tipos de `pagination.d.ts`:

```tsx
import type { PaginatedResponse } from '@/types';

interface Props {
    items: PaginatedResponse<ItemResource>;
}
```

### Problema: Incompatibilidade de Estrutura

O Laravel retorna paginação em dois formatos:

**Formato 1 - `paginate()` com `->toArray()`:**
```json
{
  "data": [...],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 5, ... }
}
```

**Formato 2 - `paginate()` direto (Inertia default):**
```json
{
  "data": [...],
  "links": [{ "url": "...", "label": "1", "active": true }, ...],
  "current_page": 1,
  "last_page": 5,
  ...
}
```

### Solução Proposta

Criar tipo adicional para o formato Inertia:

**Arquivo**: `resources/js/types/pagination.d.ts` (adicionar)

```typescript
/**
 * Pagination link for Inertia default format
 */
export interface InertiaPaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * Inertia paginated response (Laravel's default paginate() format)
 * Used by most Inertia pages
 */
export interface InertiaPaginatedResponse<T> {
    data: T[];
    links: InertiaPaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}
```

### Uso

```tsx
import type { InertiaPaginatedResponse, PlanResource } from '@/types';

interface Props {
    plans: InertiaPaginatedResponse<PlanResource>;
}
```

---

## Task 4: Criar Resources Faltantes

### 4.1 BillingPlanResource

Para a página de billing do tenant que precisa de informações específicas de plano + subscription.

**Arquivo**: `app/Http/Resources/Tenant/BillingPlanResource.php`

```php
<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Traits\HasTypescriptType;

class BillingPlanResource extends BaseResource
{
    use HasTypescriptType;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->trans('name'),
            'slug' => $this->slug,
            'description' => $this->trans('description'),
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'currency' => $this->currency,
            'billing_period' => $this->billing_period->value,
            'features' => $this->features,
            'limits' => $this->limits,
            'is_current' => $this->when(
                isset($this->is_current),
                fn () => $this->is_current
            ),
            'is_upgrade' => $this->when(
                isset($this->is_upgrade),
                fn () => $this->is_upgrade
            ),
        ];
    }

    public static function typescriptType(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'slug' => 'string',
            'description' => 'string | null',
            'price' => 'number',
            'formatted_price' => 'string',
            'currency' => 'string',
            'billing_period' => 'BillingPeriod',
            'features' => 'PlanFeatures',
            'limits' => 'PlanLimits',
            'is_current' => 'boolean | undefined',
            'is_upgrade' => 'boolean | undefined',
        ];
    }
}
```

### 4.2 SubscriptionResource

**Arquivo**: `app/Http/Resources/Tenant/SubscriptionResource.php`

```php
<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Traits\HasTypescriptType;

class SubscriptionResource extends BaseResource
{
    use HasTypescriptType;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'stripe_status' => $this->stripe_status,
            'stripe_price' => $this->stripe_price,
            'quantity' => $this->quantity,
            'trial_ends_at' => $this->formatIso($this->trial_ends_at),
            'ends_at' => $this->formatIso($this->ends_at),
            'created_at' => $this->formatIso($this->created_at),
            'is_active' => $this->active(),
            'is_on_trial' => $this->onTrial(),
            'is_canceled' => $this->canceled(),
            'is_on_grace_period' => $this->onGracePeriod(),
        ];
    }

    public static function typescriptType(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'stripe_status' => 'string',
            'stripe_price' => 'string | null',
            'quantity' => 'number',
            'trial_ends_at' => 'string | null',
            'ends_at' => 'string | null',
            'created_at' => 'string',
            'is_active' => 'boolean',
            'is_on_trial' => 'boolean',
            'is_canceled' => 'boolean',
            'is_on_grace_period' => 'boolean',
        ];
    }
}
```

### 4.3 InvoiceResource

**Arquivo**: `app/Http/Resources/Tenant/InvoiceResource.php`

```php
<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Traits\HasTypescriptType;
use Laravel\Cashier\Invoice;

class InvoiceResource extends BaseResource
{
    use HasTypescriptType;

    public function toArray($request): array
    {
        /** @var Invoice $this->resource */
        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date()->toIso8601String(),
            'total' => $this->total(),
            'status' => $this->status,
            'pdf_url' => $this->when(
                method_exists($this->resource, 'downloadUrl'),
                fn () => $this->downloadUrl()
            ),
        ];
    }

    public static function typescriptType(): array
    {
        return [
            'id' => 'string',
            'number' => 'string | null',
            'date' => 'string',
            'total' => 'string',
            'status' => 'string',
            'pdf_url' => 'string | undefined',
        ];
    }
}
```

---

## Checklist de Implementação

### Fase 1: CentralUserResource
- [ ] Criar `CentralUserResource.php`
- [ ] Criar `CentralUserDetailResource.php`
- [ ] Atualizar `UserController.php` (central)
- [ ] Rodar `sail artisan types:generate`
- [ ] Atualizar `central/admin/users/index.tsx`
- [ ] Atualizar `central/admin/users/show.tsx`
- [ ] Rodar `npm run types` para verificar

### Fase 2: Páginas Central Admin
- [ ] Migrar `central/admin/roles/show.tsx`
- [ ] Migrar `central/admin/roles/edit.tsx`
- [ ] Migrar `central/admin/federation/show.tsx`
- [ ] Migrar `central/admin/federation/create.tsx`
- [ ] Migrar `central/admin/federation/edit.tsx`
- [ ] Migrar `central/admin/bundles/index.tsx`
- [ ] Migrar componentes de federation

### Fase 3: Páginas Tenant Admin
- [ ] Migrar `tenant/admin/settings/index.tsx`
- [ ] Migrar `tenant/admin/settings/roles/show.tsx`
- [ ] Migrar `tenant/admin/settings/roles/edit.tsx`
- [ ] Migrar `tenant/admin/team/activity.tsx`
- [ ] Migrar `tenant/admin/billing/invoices.tsx`

### Fase 4: Billing Resources
- [ ] Criar `BillingPlanResource.php`
- [ ] Criar `SubscriptionResource.php`
- [ ] Criar `InvoiceResource.php`
- [ ] Rodar `sail artisan types:generate`
- [ ] Migrar `tenant/admin/billing/index.tsx`

### Fase 5: Padronização de Paginação
- [ ] Adicionar `InertiaPaginatedResponse` em `pagination.d.ts`
- [ ] Atualizar páginas para usar tipo padronizado
- [ ] Documentar padrão no CLAUDE.md

### Verificação Final
- [ ] `sail artisan types:generate` sem erros
- [ ] `npm run types` sem erros
- [ ] `npm run build` sem erros
- [ ] Testar páginas principais no browser

---

## Comandos Úteis

```bash
# Regenerar tipos TypeScript do backend
sail artisan types:generate

# Verificar tipos TypeScript
npm run types

# Build completo
npm run build

# Verificar quais arquivos ainda têm interfaces inline
grep -r "^interface " resources/js/pages --include="*.tsx" | grep -v "Props\|PageProps"
```

---

## Referências

- **Resources com HasTypescriptType**: `app/Http/Resources/`
- **Tipos gerados**: `resources/js/types/resources.d.ts`
- **Documentação de API Resources**: `docs/API-RESOURCES.md`
- **Enums e metadados**: `docs/ENUMS-SINGLE-SOURCE-OF-TRUTH.md`
