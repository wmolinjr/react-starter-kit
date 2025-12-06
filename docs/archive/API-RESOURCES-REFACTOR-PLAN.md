# API Resources Refactoring Plan

## Status: COMPLETED

**Completed**: 2025-12-05
**All 369 tests passing**

## Summary of Changes

### Created Resources (19 files)

**Central Resources (10 files)**:
- `BaseResource.php` - Base class with helper methods
- `TenantResource.php`, `TenantDetailResource.php`, `TenantEditResource.php`, `TenantSummaryResource.php`
- `TenantCollection.php`
- `PlanResource.php`, `PlanDetailResource.php`, `PlanEditResource.php`, `PlanSummaryResource.php`
- `DomainResource.php`

**Tenant Resources (9 files)**:
- `UserResource.php`, `UserSummaryResource.php`, `TeamMemberResource.php`
- `ProjectResource.php`, `ProjectDetailResource.php`, `ProjectEditResource.php`
- `ActivityResource.php`, `MediaResource.php`, `TenantInvitationResource.php`

**Shared Resources (4 files)**:
- `RoleResource.php`, `RoleDetailResource.php`, `RoleEditResource.php`
- `PermissionResource.php`

### Updated Controllers
- `TenantManagementController` - Uses TenantResource, TenantDetailResource
- `PlanCatalogController` - Uses PlanResource, PlanDetailResource
- `ProjectController` - Uses ProjectResource, ProjectDetailResource, ProjectEditResource
- `TeamController` - Uses TeamMemberResource, TenantInvitationResource
- `AuditLogController` - Uses ActivityResource, UserSummaryResource
- `RoleManagementController` - Uses RoleResource, RoleDetailResource, RoleEditResource
- `TenantRoleController` - Uses RoleResource, RoleDetailResource, RoleEditResource

### Refactored Services
- `TeamService` - Returns User models instead of arrays
- `AuditLogService` - Returns Activity models, removed formatActivity()
- `RoleService` (Central) - Returns Role models, removed format methods
- `RoleService` (Tenant) - Returns Role models, removed format methods

### Configuration
- `AppServiceProvider` - Added `JsonResource::withoutWrapping()` for Inertia compatibility

### Documentation
- Created `docs/API-RESOURCES.md` - Complete documentation

---

## Original Plan

## Executive Summary

Este projeto atualmente usa uma variedade de padrões de transformação de dados em controllers e services:

1. **Instâncias diretas de model** passadas ao Inertia (ex: `ProjectController::edit`)
2. **Mapeamento de arrays em services** (ex: `TeamService::getTeamMembers()`, `PlanService::formatPlanForList()`)
3. **Transformações inline em controllers** (ex: `TenantManagementController::index`)
4. **Model `toArray()` com manipulação manual** (ex: `TenantManagementController::show`)

Esta inconsistência leva a:
- Lógica de transformação duplicada entre services
- Tipos TypeScript definidos independentemente no frontend (sem fonte única de verdade)
- Dificuldade em manter contratos de API consistentes
- Nenhum lugar centralizado para tratar traduções (chamadas `trans()` espalhadas)

**Solução Proposta**: Implementar Laravel API Resources com organização de namespace estruturada que espelha o padrão existente `Models/Central`, `Models/Tenant`, `Models/Shared` (anteriormente Universal).

---

## Estado Atual

### Padrões de Resposta Encontrados

| Controller | Método | Padrão Atual |
|------------|--------|--------------|
| `TenantManagementController` | `index` | `$query->paginate()->through(fn => array)` |
| `TenantManagementController` | `show` | `$model->toArray()` + manipulação manual |
| `ProjectController` | `index` | Coleção direta de models `$projects` |
| `ProjectController` | `show` | Array inline com mapeamento de media aninhado |
| `ProjectController` | `edit` | Instância direta do model `'project' => $project` |
| `TeamController` | `index` | Service retorna `Collection<array>` |
| `PlanCatalogController` | `index` | `$planService->getAllPlans()` retorna arrays formatados |
| `TenantRoleController` | `index` | `$roleService->getRolesWithStats()` retorna arrays |
| `AuditLogController` | `index` | `$auditLogService->getActivities()` retorna paginator com `->through()` |
| `BillingController` | `index` | Service retorna arrays aninhados |
| `AddonManagementController` | `index` | Paginator direto `$addons` |

### Métodos de Formatação em Services (a serem substituídos)

| Service | Método | Retorna |
|---------|--------|---------|
| `TeamService` | `getTeamMembers()` | `Collection<array>` |
| `TeamService` | `getPendingInvitations()` | `Collection<array>` |
| `TeamService` | `getTeamStats()` | `array` |
| `PlanService` | `getAllPlans()` | `Collection<array>` |
| `PlanService` | `formatPlanForList()` | `array` |
| `PlanService` | `formatPlanForEdit()` | `array` |
| `RoleService` (Central) | `getAllRoles()` | `Collection<array>` |
| `RoleService` (Central) | `formatRoleForList()` | `array` |
| `RoleService` (Central) | `getRoleDetail()` | `array` |
| `RoleService` (Central) | `getRoleForEdit()` | `array` |
| `RoleService` (Tenant) | `getRolesWithStats()` | `Collection<array>` |
| `RoleService` (Tenant) | `getRoleDetail()` | `array` |
| `RoleService` (Tenant) | `getRoleForEdit()` | `array` |
| `BillingService` | `getBillingOverview()` | `array` |
| `BillingService` | `getPlansForDisplay()` | `array` |
| `BillingService` | `formatSubscription()` | `array|null` |
| `AuditLogService` | `formatActivity()` | `array` |

---

## Arquitetura Proposta

### Estrutura de Diretórios

```
app/Http/Resources/
├── Central/                          # Resources do banco central
│   ├── TenantResource.php            # Listagem/show de tenant
│   ├── TenantDetailResource.php      # Tenant com users, addons
│   ├── TenantEditResource.php        # Tenant para formulário de edição
│   ├── TenantSummaryResource.php     # Info mínima (para listas)
│   ├── TenantCollection.php          # Coleção paginada
│   │
│   ├── PlanResource.php              # Listagem de plano
│   ├── PlanDetailResource.php        # Plano com todos os detalhes
│   ├── PlanEditResource.php          # Plano para formulário de edição
│   ├── PlanSummaryResource.php       # Plano para dropdowns/selects
│   ├── PlanCollection.php            # Coleção de planos
│   │
│   ├── AddonResource.php             # Item do catálogo de addon
│   ├── AddonDetailResource.php       # Addon com detalhes completos
│   ├── AddonEditResource.php         # Addon para formulário de edição
│   ├── AddonCollection.php           # Coleção de addons
│   │
│   ├── AddonSubscriptionResource.php # Assinatura ativa de addon
│   ├── AddonSubscriptionCollection.php
│   │
│   ├── DomainResource.php            # Info de domínio
│   │
│   ├── AdminUserResource.php         # Usuário admin central
│   ├── AdminUserCollection.php
│   │
│   └── TenantInvitationResource.php  # Convite pendente
│
├── Tenant/                           # Resources do banco do tenant
│   ├── UserResource.php              # Usuário do tenant
│   ├── UserSummaryResource.php       # Usuário para listas/dropdowns
│   ├── UserCollection.php            # Coleção de usuários
│   │
│   ├── TeamMemberResource.php        # Membro com info de role
│   ├── TeamMemberCollection.php
│   │
│   ├── ProjectResource.php           # Listagem de projeto
│   ├── ProjectDetailResource.php     # Projeto com media
│   ├── ProjectEditResource.php       # Projeto para formulário de edição
│   ├── ProjectCollection.php
│   │
│   ├── ActivityResource.php          # Entrada de audit log
│   ├── ActivityDetailResource.php    # Atividade com properties
│   ├── ActivityCollection.php
│   │
│   ├── MediaResource.php             # Info de arquivo de media
│   ├── MediaCollection.php
│   │
│   └── Billing/                      # Resources específicos de billing
│       ├── SubscriptionResource.php
│       ├── InvoiceResource.php
│       ├── InvoiceCollection.php
│       └── InvoiceDetailResource.php
│
├── Shared/                           # Funciona em ambos os contextos (anteriormente Universal)
│   ├── RoleResource.php              # Listagem de role
│   ├── RoleDetailResource.php        # Role com permissions/users
│   ├── RoleEditResource.php          # Role para formulário de edição
│   ├── RoleCollection.php
│   │
│   ├── PermissionResource.php        # Item de permission
│   ├── PermissionGroupResource.php   # Permissions agrupadas por categoria
│   └── PermissionCollection.php
│
└── Common/                           # Resources compartilhados/utilitários
    ├── PaginationResource.php        # Meta de paginação padronizada
    ├── FilterOptionsResource.php     # Opções de filtro para dropdown
    └── StatsResource.php             # Objeto genérico de estatísticas
```

---

## Lista de Resources por Namespace

### Central Resources (14 arquivos)

| Resource | Model | Propósito |
|----------|-------|-----------|
| `TenantResource` | `Tenant` | View de lista com domains, plan, user count |
| `TenantDetailResource` | `Tenant` | View de show com users, addons, detalhes completos |
| `TenantEditResource` | `Tenant` | Formulário de edição com valores atuais |
| `TenantSummaryResource` | `Tenant` | Info mínima para dropdowns/referências |
| `TenantCollection` | - | Coleção paginada de tenants |
| `PlanResource` | `Plan` | View de lista com contagem de tenants |
| `PlanDetailResource` | `Plan` | Plano completo com features/limits |
| `PlanEditResource` | `Plan` | Formulário de edição com traduções |
| `PlanSummaryResource` | `Plan` | Para dropdowns de seleção de plano |
| `AddonResource` | `Addon` | Item do catálogo |
| `AddonSubscriptionResource` | `AddonSubscription` | Assinatura ativa |
| `DomainResource` | `Domain` | Info de domínio |
| `AdminUserResource` | `Central\User` | Usuário admin central |
| `TenantInvitationResource` | `TenantInvitation` | Convite pendente |

### Tenant Resources (13 arquivos)

| Resource | Model | Propósito |
|----------|-------|-----------|
| `UserResource` | `Tenant\User` | Info completa do usuário |
| `UserSummaryResource` | `Tenant\User` | Usuário mínimo para listas |
| `TeamMemberResource` | `Tenant\User` | Usuário com role/permissions |
| `ProjectResource` | `Project` | View de lista |
| `ProjectDetailResource` | `Project` | View de show com media |
| `ProjectEditResource` | `Project` | Formulário de edição |
| `ActivityResource` | `Activity` | Entrada de audit log |
| `ActivityDetailResource` | `Activity` | Atividade completa com properties |
| `MediaResource` | `Media` | Info de arquivo de media |
| `SubscriptionResource` | - | Info de assinatura do tenant |
| `InvoiceResource` | - | Listagem de invoice |
| `InvoiceDetailResource` | - | Invoice completo com line items |

### Shared Resources (6 arquivos)

| Resource | Model | Propósito |
|----------|-------|-----------|
| `RoleResource` | `Role` | View de lista com contagens |
| `RoleDetailResource` | `Role` | Role completo com permissions/users |
| `RoleEditResource` | `Role` | Formulário de edição com permission IDs |
| `PermissionResource` | `Permission` | Permission individual |
| `PermissionGroupResource` | - | Permissions agrupadas por categoria |

---

## Fases de Implementação

### Fase 1: Fundação

**Objetivo**: Configurar classes base e infraestrutura

1. **Criar classe base Resource** com funcionalidade comum:
   - Helper de tradução (`$this->trans('field')`)
   - Helpers de formatação de data
   - Inclusão condicional de campos

2. **Criar Central Resources** para models mais usados:
   - `TenantResource`, `TenantCollection`
   - `PlanResource`, `PlanSummaryResource`
   - `DomainResource`

3. **Atualizar 2-3 controllers** para usar os novos resources como prova de conceito

### Fase 2: Tenant Resources

**Objetivo**: Implementar resources de contexto tenant

1. **Criar Tenant Resources**:
   - `UserResource`, `TeamMemberResource`
   - `ProjectResource`, `ProjectDetailResource`
   - `ActivityResource`, `ActivityDetailResource`

2. **Refatorar Tenant Controllers**:
   - `TeamController`
   - `ProjectController`
   - `AuditLogController`

3. **Atualizar TeamService** para retornar models, não arrays

### Fase 3: Shared & Billing Resources

**Objetivo**: Completar cobertura de resources

1. **Criar Shared Resources**:
   - `RoleResource`, `RoleDetailResource`, `RoleEditResource`
   - `PermissionResource`, `PermissionGroupResource`

2. **Criar Billing Resources**:
   - `SubscriptionResource`, `InvoiceResource`

3. **Refatorar controllers restantes**:
   - `TenantRoleController`
   - `RoleManagementController`
   - `BillingController`

### Fase 4: Refatoração de Services

**Objetivo**: Limpar services, remover métodos de formatação

1. **Refatorar Services** para retornar models/collections ao invés de arrays:
   - Remover `formatPlanForList()`, `formatPlanForEdit()` do `PlanService`
   - Remover `formatRoleForList()`, `getRoleDetail()` do `RoleService`
   - Simplificar `TeamService`, `AuditLogService`

2. **Atualizar HandleInertiaRequests** para usar resources nos shared props

### Fase 5: Geração TypeScript & Testes

**Objetivo**: Gerar tipos TypeScript e garantir cobertura completa

1. **Gerar tipos TypeScript** dos Resources (usando laravel-typescript-transformer ou similar)

2. **Atualizar tipos no frontend** para usar tipos gerados

3. **Escrever testes** para todos os resources

4. **Atualizar documentação**

---

## Exemplos de Código

### Padrão de Base Resource

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    /**
     * Get translated value with fallback.
     */
    protected function trans(string $key): ?string
    {
        if (method_exists($this->resource, 'trans')) {
            return $this->resource->trans($key);
        }

        return $this->resource->{$key} ?? null;
    }

    /**
     * Format date for frontend.
     */
    protected function formatDate($date, string $format = 'Y-m-d H:i'): ?string
    {
        return $date?->format($format);
    }

    /**
     * Format date as ISO string.
     */
    protected function formatIso($date): ?string
    {
        return $date?->toISOString();
    }
}
```

### Exemplo Central/TenantResource

```php
<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TenantResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'created_at' => $this->formatIso($this->created_at),

            // Relationships
            'domains' => DomainResource::collection($this->whenLoaded('domains')),
            'plan' => new PlanSummaryResource($this->whenLoaded('plan')),

            // Computed
            'users_count' => $this->when(
                $this->users_count !== null,
                fn () => $this->users_count,
                fn () => $this->getUserCount()
            ),

            // Conditional fields
            'primary_domain' => $this->when(
                $this->relationLoaded('domains'),
                fn () => $this->domains->firstWhere('is_primary', true)?->domain
            ),
        ];
    }
}
```

### Exemplo Tenant/TeamMemberResource

```php
<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TeamMemberResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->roles->first()?->name,
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'created_at' => $this->formatIso($this->created_at),
            'email_verified_at' => $this->formatIso($this->email_verified_at),

            // Computed flags
            'is_owner' => $this->isOwner(),
            'is_admin' => $this->isAdmin(),
        ];
    }
}
```

### Exemplo Shared/RoleResource

```php
<?php

namespace App\Http\Resources\Shared;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->trans('display_name') ?: $this->name,
            'description' => $this->trans('description'),
            'users_count' => $this->when(
                isset($this->users_count),
                fn () => $this->users_count,
                fn () => $this->getUserCount()
            ),
            'permissions_count' => $this->when(
                isset($this->permissions_count),
                fn () => $this->permissions_count,
                fn () => $this->permissions()->count()
            ),
            'is_protected' => $this->isProtected(),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }

    protected function getUserCount(): int
    {
        if (method_exists($this->resource, 'users')) {
            return $this->resource->users()->count();
        }

        return DB::table('model_has_roles')
            ->where('role_id', $this->id)
            ->where('model_type', 'user')
            ->count();
    }
}
```

### Exemplo de Uso no Controller

```php
<?php

namespace App\Http\Controllers\Central\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\TenantResource;
use App\Http\Resources\Central\TenantDetailResource;
use App\Http\Resources\Central\PlanSummaryResource;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantManagementController extends Controller
{
    public function index(Request $request)
    {
        $tenants = Tenant::query()
            ->with(['domains', 'plan'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('central/admin/tenants/index', [
            'tenants' => TenantResource::collection($tenants),
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(Tenant $tenant)
    {
        $tenant->load(['domains', 'plan', 'addons']);

        return Inertia::render('central/admin/tenants/show', [
            'tenant' => new TenantDetailResource($tenant),
        ]);
    }

    public function edit(Tenant $tenant)
    {
        $tenant->load(['domains', 'plan']);

        return Inertia::render('central/admin/tenants/edit', [
            'tenant' => new TenantEditResource($tenant),
            'plans' => PlanSummaryResource::collection(
                Plan::active()->orderBy('sort_order')->get()
            ),
        ]);
    }
}
```

### Exemplo de Refatoração de Service

**Antes** (estado atual):
```php
// app/Services/Tenant/TeamService.php
public function getTeamMembers(): Collection
{
    return User::with('roles')
        ->orderBy('name')
        ->get()
        ->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'created_at' => $user->created_at,
            'email_verified_at' => $user->email_verified_at,
        ]);
}
```

**Depois** (com Resources):
```php
// app/Services/Tenant/TeamService.php
public function getTeamMembers(): Collection
{
    return User::with('roles')
        ->orderBy('name')
        ->get();
}

// Controller
public function index()
{
    return Inertia::render('tenant/admin/team/index', [
        'members' => TeamMemberResource::collection(
            $this->teamService->getTeamMembers()
        ),
        'pendingInvitations' => TenantInvitationResource::collection(
            $this->teamService->getPendingInvitations(tenant())
        ),
        'teamStats' => $this->teamService->getTeamStats(tenant()),
    ]);
}
```

---

## Estratégia de Geração TypeScript

### Opção 1: Tipos Manuais com Convenções

Criar tipos TypeScript que espelham Resources:

```typescript
// resources/js/types/resources/central.d.ts
export interface TenantResource {
    id: string;
    name: string;
    slug: string;
    created_at: string;
    domains: DomainResource[];
    plan: PlanSummaryResource | null;
    users_count: number;
    primary_domain?: string;
}

export interface TenantDetailResource extends TenantResource {
    settings: Record<string, unknown> | null;
    updated_at: string;
    addons: AddonSummary[];
    users?: UserSummary[];
}
```

### Opção 2: Geração Automatizada (Recomendado)

Usar `spatie/laravel-typescript-transformer` para gerar tipos dos Resources:

```php
// config/typescript-transformer.php
return [
    'collectors' => [
        Spatie\TypeScriptTransformer\Collectors\DefaultCollector::class,
    ],
    'transformers' => [
        App\TypeScript\ResourceTransformer::class,
    ],
    'output_file' => resource_path('js/types/generated.d.ts'),
];
```

### Comandos

```bash
sail artisan wayfinder:generate --with-form
sail artisan typescript:transform
```

---

## Abordagem de Testes

### Testes Unitários de Resource

```php
use App\Http\Resources\Central\TenantResource;
use App\Models\Central\Tenant;

test('TenantResource formats tenant correctly', function () {
    $tenant = Tenant::factory()
        ->has(Domain::factory()->primary())
        ->create(['name' => 'Acme Corp']);

    $tenant->load('domains');

    $resource = new TenantResource($tenant);
    $array = $resource->resolve(request());

    expect($array)->toHaveKeys(['id', 'name', 'slug', 'domains', 'users_count']);
    expect($array['name'])->toBe('Acme Corp');
    expect($array['domains'])->toHaveCount(1);
});

test('TenantResource handles missing relationships gracefully', function () {
    $tenant = Tenant::factory()->create();

    $resource = new TenantResource($tenant);
    $array = $resource->resolve(request());

    expect($array)->not->toHaveKey('domains');
    expect($array)->not->toHaveKey('plan');
});
```

### Testes de Integração

```php
test('tenant index returns TenantResource collection', function () {
    $this->actingAsAdmin();

    Tenant::factory()->count(3)->create();

    $response = $this->get(route('central.admin.tenants.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('central/admin/tenants/index')
        ->has('tenants.data', 3)
        ->has('tenants.data.0', fn ($tenant) => $tenant
            ->has('id')
            ->has('name')
            ->has('slug')
            ->has('users_count')
        )
    );
});
```

---

## Estratégia de Migração

### Passo 1: Criar Resources Sem Quebrar Nada

1. Criar novas classes Resource
2. Manter métodos existentes dos services
3. Adicionar Resources junto com respostas existentes

### Passo 2: Testes em Paralelo

1. Testar se novos Resources retornam a mesma estrutura de dados
2. Comparar comportamento do frontend com ambas as abordagens

### Passo 3: Migração Gradual

1. Migrar um controller por vez
2. Atualizar tipos TypeScript correspondentes
3. Rodar testes E2E após cada migração

### Passo 4: Limpeza de Services

1. Remover métodos de formatação dos services
2. Services retornam apenas models/collections
3. Resources tratam toda a transformação

---

## Atualizações de Documentação Necessárias

1. **CLAUDE.md**: Adicionar seção de API Resources
2. **docs/SERVICES.md**: Atualizar convenções de tipo de retorno
3. **docs/CONTROLLERS.md**: Adicionar exemplos de uso de Resource
4. **Novo doc: docs/API-RESOURCES.md**: Documentação completa de Resources

---

## Checklist de Implementação

### Fase 1: Fundação
- [ ] Criar `app/Http/Resources/BaseResource.php`
- [ ] Criar `Central/TenantResource.php`
- [ ] Criar `Central/TenantDetailResource.php`
- [ ] Criar `Central/TenantEditResource.php`
- [ ] Criar `Central/TenantCollection.php`
- [ ] Criar `Central/PlanResource.php`
- [ ] Criar `Central/PlanSummaryResource.php`
- [ ] Criar `Central/DomainResource.php`
- [ ] Atualizar `TenantManagementController` para usar Resources
- [ ] Escrever testes para novos Resources

### Fase 2: Tenant Resources
- [ ] Criar `Tenant/UserResource.php`
- [ ] Criar `Tenant/UserSummaryResource.php`
- [ ] Criar `Tenant/TeamMemberResource.php`
- [ ] Criar `Tenant/ProjectResource.php`
- [ ] Criar `Tenant/ProjectDetailResource.php`
- [ ] Criar `Tenant/ActivityResource.php`
- [ ] Criar `Tenant/MediaResource.php`
- [ ] Atualizar `TeamController` para usar Resources
- [ ] Atualizar `ProjectController` para usar Resources
- [ ] Atualizar `AuditLogController` para usar Resources
- [ ] Escrever testes

### Fase 3: Shared & Billing
- [ ] Criar `Shared/RoleResource.php`
- [ ] Criar `Shared/RoleDetailResource.php`
- [ ] Criar `Shared/RoleEditResource.php`
- [ ] Criar `Shared/PermissionResource.php`
- [ ] Criar `Shared/PermissionGroupResource.php`
- [ ] Criar `Tenant/Billing/SubscriptionResource.php`
- [ ] Criar `Tenant/Billing/InvoiceResource.php`
- [ ] Atualizar `TenantRoleController`
- [ ] Atualizar `RoleManagementController`
- [ ] Atualizar `BillingController`
- [ ] Escrever testes

### Fase 4: Refatoração de Services
- [ ] Refatorar `TeamService` - retornar models
- [ ] Refatorar `PlanService` - remover métodos de format
- [ ] Refatorar `RoleService` (Central) - remover métodos de format
- [ ] Refatorar `RoleService` (Tenant) - remover métodos de format
- [ ] Refatorar `BillingService` - remover métodos de format
- [ ] Refatorar `AuditLogService` - remover métodos de format
- [ ] Atualizar `HandleInertiaRequests` shared props

### Fase 5: TypeScript & Documentação
- [ ] Configurar typescript-transformer
- [ ] Gerar tipos TypeScript
- [ ] Atualizar imports de tipos no frontend
- [ ] Atualizar `resources/js/types/index.d.ts`
- [ ] Escrever `docs/API-RESOURCES.md`
- [ ] Atualizar `CLAUDE.md`
- [ ] Atualizar `docs/SERVICES.md`
- [ ] Atualizar `docs/CONTROLLERS.md`
- [ ] Rodar suite completa de testes E2E

---

## Arquivos Críticos para Implementação

| Arquivo | Motivo |
|---------|--------|
| `app/Http/Controllers/Central/Admin/TenantManagementController.php` | Exemplo principal de transformações inline que precisam ser refatoradas |
| `app/Services/Tenant/TeamService.php` | Service com métodos de formatação para refatorar |
| `app/Services/Central/PlanService.php` | Service com múltiplos métodos de format para substituir |
| `app/Http/Middleware/HandleInertiaRequests.php` | Compartilha props globais que devem usar Resources |
| `resources/js/types/index.d.ts` | Tipos TypeScript que precisam ser atualizados |

---

## Benefícios Esperados

1. **Consistência**: Todas as respostas seguem o mesmo padrão
2. **Type Safety**: Tipos TypeScript gerados automaticamente
3. **Manutenibilidade**: Transformação centralizada em Resources
4. **DRY**: Sem duplicação de lógica de formatação
5. **Testabilidade**: Resources podem ser testados isoladamente
6. **Documentação**: Resources servem como documentação viva da API
