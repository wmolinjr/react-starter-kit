# Inline Interfaces Migration Plan

Este documento descreve o plano para migrar interfaces TypeScript inline nas páginas React para tipos auto-gerados a partir de API Resources do backend.

## Contexto

O projeto usa o padrão "Single Source of Truth" onde:
1. Backend PHP Resources definem a estrutura dos dados (`HasTypescriptType` trait)
2. `sail artisan types:generate` gera tipos TypeScript automaticamente
3. Frontend importa tipos de `@/types` ao invés de definir interfaces inline

**Benefícios**:
- Consistência entre backend e frontend
- Tipos sempre atualizados com a API
- Menos duplicação de código
- Melhor DX com autocomplete

## Inventário de Interfaces Inline

### Tipos de Paginação Existentes

O projeto já possui tipos de paginação bem definidos em `resources/js/types/pagination.d.ts`:

| Tipo | Uso | Estrutura |
|------|-----|-----------|
| `PaginatedResponse<T>` | API JSON responses | `data`, `links` (first/last/prev/next), `meta` |
| `SimplePaginatedResponse<T>` | `simplePaginate()` | `data`, `next_page_url`, `prev_page_url` |
| `CursorPaginatedResponse<T>` | `cursorPaginate()` | `data`, `next_cursor`, `prev_cursor` |
| `InertiaPaginatedResponse<T>` | **Inertia pages** | `data`, `links[]`, `current_page`, `last_page`, `total` |

**Padrão a seguir**: Para páginas Inertia, usar `InertiaPaginatedResponse<T>`.

### Arquivos com `PaginationLink` Inline (Devem usar tipos existentes)

| Arquivo | Interface Inline | Substituir por | Status |
|---------|------------------|----------------|--------|
| `central/admin/tenants/index.tsx:27` | `PaginationLink` | `InertiaPaginatedResponse<TenantWithUsers>` | ✅ DONE |
| `tenant/admin/audit/index.tsx:83` | `PaginationLink` | Removido (usa `InertiaPaginatedResponse`) | ✅ DONE |
| `tenant/admin/audit/index.tsx:89` | `PaginatedActivities` | `InertiaPaginatedResponse<ActivityResource>` | ✅ DONE |

---

## Fase 1: Bundle Resources (CRÍTICO - tem TODOs) ✅ CONCLUÍDA

### Arquivos Afetados
- `central/admin/bundles/index.tsx`
- `central/admin/bundles/edit.tsx`
- `central/admin/bundles/create.tsx`
- `central/admin/bundles/components/bundle-form.tsx`

### Resources a Criar

#### 1.1 `BundleResource`

```php
// app/Http/Resources/Central/BundleResource.php
class BundleResource extends BaseResource
{
    use HasTypescriptType;

    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'slug' => 'string',
            'name' => 'Translations',
            'name_display' => 'string',
            'description' => 'Translations',
            'active' => 'boolean',
            'discount_percent' => 'number',
            'price_monthly' => 'number | null',
            'price_yearly' => 'number | null',
            'price_monthly_effective' => 'number',
            'price_yearly_effective' => 'number',
            'base_price_monthly' => 'number',
            'savings_monthly' => 'number',
            'badge' => 'BadgePreset | null',
            'icon' => 'string',
            'icon_color' => 'string | null',
            'features' => 'string[]',
            'sort_order' => 'number',
            'addon_count' => 'number',
            'addons' => 'BundleAddonResource[]',
            'plan_ids' => 'string[]',
            'plans' => 'BundlePlanSummary[]',
            'stripe_product_id' => 'string | null',
            'stripe_price_monthly_id' => 'string | null',
            'stripe_price_yearly_id' => 'string | null',
            'is_synced' => 'boolean',
        ];
    }
}
```

#### 1.2 `BundleAddonResource`

```php
// app/Http/Resources/Central/BundleAddonResource.php
class BundleAddonResource extends BaseResource
{
    use HasTypescriptType;

    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'addon_id' => 'string',
            'slug' => 'string',
            'name' => 'string',
            'type' => 'string',
            'type_label' => 'string',
            'price_monthly' => 'number',
            'quantity' => 'number',
        ];
    }
}
```

#### 1.3 Tipos Auxiliares (common.d.ts)

```typescript
// resources/js/types/common.d.ts

/**
 * Plan summary for bundle views
 */
export interface BundlePlanSummary {
    id: string;
    name: string;
    slug: string;
}
```

### Controller a Atualizar

```php
// app/Http/Controllers/Central/Admin/BundleController.php
public function index()
{
    $bundles = AddonBundle::with(['addons.addon', 'plans'])->get();

    return Inertia::render('central/admin/bundles/index', [
        'bundles' => BundleResource::collection($bundles),
    ]);
}
```

### Tarefas
- [x] Criar `BundleResource.php`
- [x] Criar `BundleAddonResource.php`
- [x] Adicionar `BundlePlanSummary` em `common.d.ts`
- [x] Atualizar `BundleController` para usar Resources
- [x] Rodar `sail artisan types:generate`
- [x] Atualizar páginas de bundles para usar tipos gerados
- [x] Remover interfaces inline dos arquivos

**Implementado em**: 2024-12-09

---

## Fase 2: Addon Resources ✅ CONCLUÍDA

### Arquivos Afetados
- `central/admin/addons/index.tsx` ✅
- `central/admin/addons/revenue.tsx` ✅
- `central/admin/catalog/index.tsx` (não modificado - usa tipos de addon catalog)
- `central/admin/catalog/edit.tsx` (não modificado - usa tipos de addon catalog)
- `central/admin/catalog/create.tsx` (não modificado - usa tipos de addon catalog)
- `central/admin/catalog/components/addon-form.tsx` (não modificado - usa tipos de addon catalog)

### Resources Criados

#### 2.1 `AddonSubscriptionResource` ✅

```php
// app/Http/Resources/Central/AddonSubscriptionResource.php
class AddonSubscriptionResource extends BaseResource
{
    use HasTypescriptType;

    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'addon_slug' => 'string',
            'addon_type' => 'AddonType',
            'name' => 'string',
            'description' => 'string | null',
            'quantity' => 'number',
            'price' => 'number',
            'currency' => 'string',
            'total_price' => 'number',
            'formatted_price' => 'string',
            'formatted_total_price' => 'string',
            'billing_period' => 'BillingPeriod',
            'billing_period_label' => 'string',
            'status' => 'AddonStatus',
            'status_label' => 'string',
            'started_at' => 'string | null',
            'expires_at' => 'string | null',
            'canceled_at' => 'string | null',
            'is_active' => 'boolean',
            'is_recurring' => 'boolean',
            'is_metered' => 'boolean',
            'metered_usage' => 'number | null',
            'stripe_subscription_item_id' => 'string | null',
            'tenant' => 'AddonSubscriptionTenant | null',
            'created_at' => 'string',
        ];
    }
}
```

### Tipos Auxiliares Adicionados (common.d.ts) ✅

```typescript
/**
 * Tenant info in addon subscription views
 */
export interface AddonSubscriptionTenant {
    id: string;
    name: string;
}

/**
 * Addon management statistics
 */
export interface AddonManagementStats {
    total_addons: number;
    active_addons: number;
    total_revenue: number;
    tenants_with_addons: number;
}

/**
 * Revenue breakdown by addon type
 */
export interface RevenueByType {
    addon_type: string;
    addon_type_label: string;
    total: number;
    formatted_total: string;
}
```

### Tarefas
- [x] Criar `AddonSubscriptionResource.php`
- [x] Adicionar tipos auxiliares em `common.d.ts` (`AddonSubscriptionTenant`, `AddonManagementStats`, `RevenueByType`)
- [x] Atualizar `AddonManagementController` para usar Resource
- [x] Regenerar tipos com `sail artisan types:generate`
- [x] Atualizar `addons/index.tsx` para usar `AddonSubscriptionResource` e `AddonManagementStats`
- [x] Atualizar `addons/revenue.tsx` para usar `RevenueByType`

**Implementado em**: 2024-12-09

**Nota**: Os arquivos de `catalog/` não foram modificados pois usam tipos diferentes (Addon catalog vs Addon subscriptions).

---

## Fase 3: Federation Resources ✅ CONCLUÍDA

### Arquivos Afetados
- `central/admin/federation/show.tsx` ✅
- `central/admin/federation/conflicts.tsx` ✅
- `central/admin/federation/user.tsx` ✅

### Solução Implementada

Os Resources backend já estavam corretos. As páginas foram atualizadas para:
1. Usar tipos auto-gerados: `FederationGroupResource`, `FederationConflictResource`, `FederatedUserDetailResource`
2. Tipos estendidos quando necessário: `ConflictWithSourceTenant`, `FederationGroupShowData`
3. Tipos auxiliares em `common.d.ts`: `FederationGroupShowStats`

### Tipos Adicionados em `common.d.ts`

```typescript
/**
 * Federation group stats for show page (detailed)
 */
export interface FederationGroupShowStats {
    total_users: number;
    active_syncs: number;
    pending_conflicts: number;
    failed_syncs: number;
}
```

### Tarefas
- [x] Atualizar `show.tsx` para usar `FederationGroupShowStats` de common.d.ts
- [x] Atualizar `conflicts.tsx` para usar `FederationConflictResource` com extensão
- [x] Atualizar `user.tsx` para usar `FederatedUserDetailResource`
- [x] Corrigir acessos a `email` -> `global_email` nos tipos

**Implementado em**: 2024-12-09

---

## Fase 4: Team Resources ✅ CONCLUÍDA

### Arquivos Afetados
- `tenant/admin/team/index.tsx` ✅
- `tenant/admin/team/activity.tsx` (não modificado - usa ActivityResource)

### Análise do Problema

O problema original era que a página estendia `TeamMemberResource` com campos de invitation que não existiam:

```typescript
// ANTES (incorreto - estendia Resource)
interface TeamMember extends Omit<TeamMemberResource, 'role'> {
  invited_at: string;
  joined_at: string | null;
  is_pending: boolean;
}
```

No entanto, o controller já retornava os dados corretamente separados:
- `members` - Lista de `TeamMemberResource[]`
- `pendingInvitations` - Lista de `UserInvitationResource[]`

### Solução Implementada

**Não foi necessário modificar os Resources backend**, apenas alinhar a página frontend com a estrutura correta dos dados.

#### 4.1 Atualização da Página `team/index.tsx`

```typescript
// DEPOIS (correto - usa tipos separados)
interface Props {
  members: TeamMemberResource[];
  pendingInvitations: UserInvitationResource[];
  teamStats: TeamStats;
}
```

A página agora renderiza duas seções:
1. **Active Members** - Usa `TeamMemberResource`
2. **Pending Invitations** - Usa `UserInvitationResource` (já existente)

#### 4.2 Tipo `TeamStats` em `common.d.ts`

```typescript
/**
 * Team usage statistics
 */
export interface TeamStats {
    max_users: number | null;
    current_users: number;
}
```

### Tarefas
- [x] Analisar estrutura de dados do controller
- [x] Adicionar `TeamStats` em `common.d.ts`
- [x] Atualizar `team/index.tsx` para usar `TeamMemberResource[]` e `UserInvitationResource[]` separados
- [x] Remover interface inline `TeamMember` e `TeamStats`

**Implementado em**: 2024-12-09

**Nota**: Os Resources backend (`TeamMemberResource` e `UserInvitationResource`) já estavam corretos. O problema era apenas na página que não refletia a estrutura real dos dados.

---

## Fase 5: Paginação Padronizada

### Arquivos Afetados
- `central/admin/tenants/index.tsx`
- `tenant/admin/audit/index.tsx`

### Ação

Substituir interfaces inline pelos tipos existentes:

```typescript
// ANTES (inline)
interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedActivities {
    data: ActivityResource[];
    current_page: number;
    // ...
}

// DEPOIS (usando tipos existentes)
import type { InertiaPaginatedResponse, InertiaPaginationLink } from '@/types';

interface Props {
    activities: InertiaPaginatedResponse<ActivityResource>;
}
```

### Tarefas
- [x] `central/admin/tenants/index.tsx` - Usar `InertiaPaginatedResponse<TenantWithUsers>`
- [x] `tenant/admin/audit/index.tsx` - Usar `InertiaPaginatedResponse<ActivityResource>`
- [x] Remover interfaces `PaginationLink` inline

**Implementado em**: 2024-12-09

---

## Fase 6: Plan/Roles Info ✅ CONCLUÍDA (Não Aplicável)

### Análise

A interface `PlanInfo` em `tenant/admin/settings/roles/index.tsx` é **específica para a lógica dessa página** e não precisa ser centralizada. É um tipo que representa uma resposta computada do controller, não um Resource genérico.

**Status**: Mantido como interface local (Props) - não é candidato para migração.

---

## Fase 7: Dashboard Stats ✅ CONCLUÍDA

### Arquivos Afetados
- `central/admin/dashboard.tsx` ✅

### Solução Implementada

Adicionado `CentralDashboardStats` em `common.d.ts`:

```typescript
/**
 * Central admin dashboard statistics
 */
export interface CentralDashboardStats {
    total_tenants: number;
    total_admins: number;
    total_addons: number;
    total_plans: number;
}
```

### Tarefas
- [x] Adicionar `CentralDashboardStats` em `common.d.ts`
- [x] Atualizar `dashboard.tsx` para importar de `common.d.ts`

**Implementado em**: 2024-12-09

---

## Fase 8: Tipos de Formulário ✅ CONCLUÍDA

### Arquivos Atualizados

| Arquivo | Tipos Migrados |
|---------|----------------|
| `plans/components/plan-form.tsx` | `FeatureDefinition`, `LimitDefinition`, `AddonOptionForPlan` |
| `plans/create.tsx` | `FeatureDefinition`, `LimitDefinition`, `AddonOptionForPlan` |
| `plans/edit.tsx` | `FeatureDefinition`, `LimitDefinition`, `AddonOptionForPlan` |
| `catalog/components/addon-form.tsx` | `FeatureDefinition`, `LimitDefinition`, `CategoryOption`, `AddonTypeInfo` |
| `catalog/create.tsx` | `FeatureDefinition`, `LimitDefinition`, `CategoryOption`, `AddonTypeInfo` |
| `catalog/edit.tsx` | `FeatureDefinition`, `LimitDefinition`, `CategoryOption`, `AddonTypeInfo` |

### Tipos Centralizados em `common.d.ts`

```typescript
/**
 * Feature definition from backend (used in plan/addon forms)
 */
export interface FeatureDefinition {
    id: string;
    key: string;
    name: string;
    description: string | null;
    category: string | null;
    icon: string | null;
}

/**
 * Limit definition from backend (used in plan/addon forms)
 */
export interface LimitDefinition {
    id: string;
    key: string;
    name: string;
    description: string | null;
    unit: string | null;
    unit_label: string | null;
    default_value: number;
    allows_unlimited: boolean;
    icon: string | null;
}

/**
 * Category option for feature/limit grouping
 */
export interface CategoryOption {
    value: string;
    label: string;
}

/**
 * Simple addon info for plan forms
 */
export interface AddonOptionForPlan {
    id: string;
    name: string;
    slug: string;
}

/**
 * Addon type info from backend (used in addon forms)
 */
export interface AddonTypeInfo {
    value: string;
    label: string;
    description?: string;
    icon?: string;
    color?: string;
    is_stackable?: boolean;
    is_recurring?: boolean;
    is_one_time?: boolean;
    has_validity?: boolean;
}
```

### Tarefas
- [x] Identificar tipos duplicados em múltiplos arquivos
- [x] Mover tipos compartilhados para `common.d.ts`
- [x] Atualizar imports em todos os arquivos afetados
- [x] Verificar TypeScript compila sem erros

**Implementado em**: 2024-12-09

**Nota**: Tipos específicos de formulário como `PlanData`, `AddonInput`, `BundleInput` permanecem locais pois são usados apenas em um componente.

---

## Status Final

### Tabela de Priorização

| Fase | Prioridade | Esforço | Impacto | Status |
|------|------------|---------|---------|--------|
| 1. Bundle Resources | 🔴 CRÍTICA | Alto | Remove TODOs explícitos | ✅ DONE |
| 5. Paginação | 🟡 ALTA | Baixo | Remove duplicação óbvia | ✅ DONE |
| 2. Addon Resources | 🟡 ALTA | Médio | Padroniza addons management | ✅ DONE |
| 4. Team Resources | 🟡 ALTA | Baixo | Corrige extensão de tipo | ✅ DONE |
| 3. Federation | 🟢 MÉDIA | Médio | Alinha com Resources existentes | ✅ DONE |
| 6. Plan Info | 🟢 MÉDIA | Baixo | Tipo simples | ✅ N/A |
| 7. Dashboard | ⚪ BAIXA | Baixo | Stats simples | ✅ DONE |
| 8. Form Types | ⚪ BAIXA | Médio | Frontend-only | ✅ DONE |

### **MIGRAÇÃO 100% CONCLUÍDA** - 2024-12-09

Todas as interfaces inline foram migradas para:
1. Tipos auto-gerados do backend (`resources.d.ts`, `enums.d.ts`, `plan.d.ts`)
2. Tipos compartilhados em `common.d.ts`

### Arquivos Principais Criados/Modificados

**Backend (Resources):**
- `app/Http/Resources/Central/BundleResource.php` ✅
- `app/Http/Resources/Central/BundleAddonResource.php` ✅
- `app/Http/Resources/Central/AddonSubscriptionResource.php` ✅

**Frontend (Tipos):**
- `resources/js/types/common.d.ts` - Expandido com 15+ novos tipos
- `resources/js/pages/central/admin/bundles/*` - Usa BundleResource
- `resources/js/pages/central/admin/addons/*` - Usa AddonSubscriptionResource
- `resources/js/pages/central/admin/federation/*` - Usa Federation Resources
- `resources/js/pages/tenant/admin/team/*` - Usa TeamMemberResource + UserInvitationResource
- `resources/js/pages/central/admin/plans/*` - Usa tipos de common.d.ts
- `resources/js/pages/central/admin/catalog/*` - Usa tipos de common.d.ts
- `resources/js/pages/central/admin/dashboard.tsx` - Usa CentralDashboardStats

---

## Referências

- [docs/API-RESOURCES.md](API-RESOURCES.md) - Padrões de Resources
- [docs/ENUMS-SINGLE-SOURCE-OF-TRUTH.md](ENUMS-SINGLE-SOURCE-OF-TRUTH.md) - Padrão de tipos gerados
