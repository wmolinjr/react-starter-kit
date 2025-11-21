# Permissions & Roles System

## Visão Geral

Este projeto utiliza um sistema robusto de permissões baseado em [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) integrado com multi-tenancy via [Stancl Tenancy](https://tenancyforlaravel.com/).

**Características**:
- ✅ **Idempotente**: Comando `permissions:sync` pode rodar múltiplas vezes sem duplicar
- ✅ **Tenant-isolated**: Permissions e roles são isoladas por tenant
- ✅ **Metadata rica**: Cada permission tem `description` e `category`
- ✅ **Display names**: Roles têm `display_name` para UI
- ✅ **Nomenclatura clara**: `tenant.resource:action` (ex: `tenant.projects:view`)
- ✅ **TypeScript-safe**: Categories e actions usam camelCase para gerar tipos válidos

---

## 📋 Índice

1. [Estrutura Completa](#estrutura-completa)
2. [Plans System Integration](#plans-system-integration) ⭐ NEW
3. [Convenções de Nomenclatura](#convenções-de-nomenclatura)
4. [Comando de Sincronização](#comando-de-sincronização)
5. [Backend: Como Usar Permissions](#backend-como-usar-permissions)
6. [Frontend: Como Usar Permissions](#frontend-como-usar-permissions)
7. [Como Adicionar Novas Permissions](#como-adicionar-novas-permissions)
8. [Roles MVP](#roles-mvp)
9. [Arquitetura](#arquitetura)
10. [Testing](#testing)
11. [Troubleshooting](#troubleshooting)

---

## Estrutura Completa

### Permissions (37 total: 22 base + 15 enterprise)

**⚠️ IMPORTANTE**: Este sistema está integrado com o **Plans System**. Permissions enterprise (15) são habilitadas apenas para tenants com planos que incluem as features correspondentes. Ver [Plans System Integration](#plans-system-integration).

#### 📦 Projects (8 permissions)

| Permission | Descrição | Owner | Admin | Member |
|------------|-----------|-------|-------|--------|
| `tenant.projects:view` | View all projects | ✅ | ✅ | ✅ |
| `tenant.projects:create` | Create new projects | ✅ | ✅ | ✅ |
| `tenant.projects:edit` | Edit any project | ✅ | ✅ | ❌ |
| `tenant.projects:editOwn` | Edit own projects only | ✅ | ❌ | ✅ |
| `tenant.projects:delete` | Delete projects | ✅ | ❌ | ❌ |
| `tenant.projects:upload` | Upload files | ✅ | ✅ | ❌ |
| `tenant.projects:download` | Download files | ✅ | ✅ | ✅ |
| `tenant.projects:archive` | Archive projects | ✅ | ✅ | ❌ |

#### 👥 Team (5 permissions)

| Permission | Descrição | Owner | Admin | Member |
|------------|-----------|-------|-------|--------|
| `tenant.team:view` | View team members | ✅ | ✅ | ✅ |
| `tenant.team:invite` | Invite members | ✅ | ✅ | ❌ |
| `tenant.team:remove` | Remove members | ✅ | ✅ | ❌ |
| `tenant.team:manageRoles` | Manage roles | ✅ | ✅ | ❌ |
| `tenant.team:activity` | View activity logs | ✅ | ✅ | ❌ |

#### ⚙️ Settings (3 permissions)

| Permission | Descrição | Owner | Admin | Member |
|------------|-----------|-------|-------|--------|
| `tenant.settings:view` | View settings | ✅ | ✅ | ✅ |
| `tenant.settings:edit` | Edit settings | ✅ | ✅ | ❌ |
| `tenant.settings:danger` | Danger zone access | ✅ | ❌ | ❌ |

#### 💳 Billing (3 permissions)

| Permission | Descrição | Owner | Admin | Member |
|------------|-----------|-------|-------|--------|
| `tenant.billing:view` | View billing | ✅ | ❌ | ❌ |
| `tenant.billing:manage` | Manage subscriptions | ✅ | ❌ | ❌ |
| `tenant.billing:invoices` | Download invoices | ✅ | ❌ | ❌ |

#### 🔑 API Tokens (3 permissions)

| Permission | Descrição | Owner | Admin | Member |
|------------|-----------|-------|-------|--------|
| `tenant.apiTokens:view` | View API tokens | ✅ | ❌ | ❌ |
| `tenant.apiTokens:create` | Create API tokens | ✅ | ❌ | ❌ |
| `tenant.apiTokens:delete` | Delete API tokens | ✅ | ❌ | ❌ |

---

### Enterprise Permissions (15 total)

**⚠️ PLAN-GATED**: These permissions are only available to tenants with plans that include the corresponding features.

#### 🎭 Custom Roles (4 permissions)

**Required Plan Feature**: `customRoles` (Professional+)

| Permission | Descrição | Available When |
|------------|-----------|----------------|
| `tenant.roles:view` | View custom roles | Plan has `customRoles` feature |
| `tenant.roles:create` | Create custom roles | Plan has `customRoles` feature |
| `tenant.roles:edit` | Edit custom roles | Plan has `customRoles` feature |
| `tenant.roles:delete` | Delete custom roles | Plan has `customRoles` feature |

#### 📊 Advanced Reports (4 permissions)

**Required Plan Feature**: `advancedReports` (Enterprise only)

| Permission | Descrição | Available When |
|------------|-----------|----------------|
| `tenant.reports:view` | View advanced reports | Plan has `advancedReports` feature |
| `tenant.reports:create` | Create custom reports | Plan has `advancedReports` feature |
| `tenant.reports:edit` | Edit custom reports | Plan has `advancedReports` feature |
| `tenant.reports:export` | Export reports | Plan has `advancedReports` feature |

#### 🔐 Single Sign-On (3 permissions)

**Required Plan Feature**: `sso` (Enterprise only)

| Permission | Descrição | Available When |
|------------|-----------|----------------|
| `tenant.sso:view` | View SSO settings | Plan has `sso` feature |
| `tenant.sso:configure` | Configure SSO | Plan has `sso` feature |
| `tenant.sso:manage` | Manage SSO providers | Plan has `sso` feature |

#### 🎨 White Label (4 permissions)

**Required Plan Feature**: `whiteLabel` (Enterprise only)

| Permission | Descrição | Available When |
|------------|-----------|----------------|
| `tenant.branding:view` | View branding settings | Plan has `whiteLabel` feature |
| `tenant.branding:edit` | Edit branding | Plan has `whiteLabel` feature |
| `tenant.branding:uploadLogo` | Upload custom logo | Plan has `whiteLabel` feature |
| `tenant.branding:customDomain` | Configure custom domain | Plan has `whiteLabel` feature |

---

## Plans System Integration

**CRITICAL**: Este sistema de permissions está integrado com o **Hybrid Plans Architecture** (Database + Laravel Pennant + Spatie Permission).

### Como Funciona

```
┌─────────────────┐
│   PLAN MODEL    │  Features: {customRoles: true, sso: true}
│  (Database)     │  Permission Map: {customRoles: ["tenant.roles:*"]}
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ PENNANT FEATURES│  Feature::for($tenant)->active('customRoles')
│   (Runtime)     │  Returns: true/false based on plan + overrides
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ GATE::BEFORE()  │  Checks if permission is in plan_enabled_permissions
│ (Authorization) │  If YES: proceed to Spatie check
└────────┬────────┘  If NO: deny access
         │
         ▼
┌─────────────────┐
│ SPATIE PERMISSION│ $user->can('tenant.roles:view')
│  (User-level)   │  Checks if USER has the permission via role
└─────────────────┘
```

### Wildcard Expansion

**Example**: Professional plan enables `customRoles` feature

```json
// Plan.permission_map
{
  "customRoles": ["tenant.roles:*"]
}
```

When tenant upgrades to Professional plan:
1. `tenant.roles:*` expands to all 4 permissions: `view`, `create`, `edit`, `delete`
2. These 4 permissions are added to `tenant.plan_enabled_permissions` (cached)
3. `Gate::before()` allows access if permission is in cache
4. Spatie checks if USER (not just tenant) has the permission

**Expansion happens in**: `app/Models/Plan.php:98-116` (expandPermissions method)

### Permission Sync on Plan Change

When a tenant's plan changes, permissions are automatically synced via **TenantObserver**:

```php
// app/Observers/TenantObserver.php
public function updated(Tenant $tenant): void
{
    if ($tenant->isDirty('plan_id')) {
        // 1. Regenerate plan permissions
        $tenant->regeneratePlanPermissions();

        // 2. Flush Pennant cache
        Feature::flushCache($tenant);
    }
}
```

### Checking Plan Permissions in Code

#### Backend

```php
use Laravel\Pennant\Feature;

// Check if tenant's plan allows the feature
if (Feature::for($tenant)->active('customRoles')) {
    // Feature is enabled by plan

    // Still need to check if USER has the permission
    if ($user->can('tenant.roles:view')) {
        // User has both: plan feature + user permission
        return view('roles.index');
    }
}
```

**Gate::before() in AppServiceProvider.php:61-77**:
```php
Gate::before(function (User $user, string $ability) {
    // If ability starts with "tenant.", check plan permissions
    if (str_starts_with($ability, 'tenant.')) {
        $tenant = tenant();

        // Check if permission is enabled by tenant's plan
        $planPermissions = $tenant?->getPlanEnabledPermissions() ?? [];
        if (!in_array($ability, $planPermissions)) {
            return false; // Plan doesn't allow this permission
        }
    }

    // Continue to Spatie Permission check
    return null;
});
```

#### Frontend

```tsx
import { usePlan } from '@/hooks/use-plan';
import { usePermissions } from '@/hooks/use-permissions';

export function RolesPage() {
    const { hasFeature } = usePlan();
    const { has } = usePermissions();

    // Check both: plan feature AND user permission
    if (!hasFeature('customRoles')) {
        return <UpgradePrompt feature="Custom Roles" />;
    }

    if (!has('tenant.roles:view')) {
        return <NoAccessMessage />;
    }

    return <RolesList />;
}
```

### Two-Level Authorization

**Level 1: Plan (Tenant-level)**
- Does the tenant's subscription plan include this feature?
- Checked via: `Feature::for($tenant)->active('customRoles')`
- Controls: What features are available to the tenant

**Level 2: Permission (User-level)**
- Does the user have permission to perform this action?
- Checked via: `$user->can('tenant.roles:view')`
- Controls: What individual users can do within enabled features

**Example**:
- Tenant has Professional plan → `customRoles` feature enabled → `tenant.roles:*` permissions available
- User A has role "owner" → has `tenant.roles:view` permission → ✅ Can view roles
- User B has role "member" → NO `tenant.roles:view` permission → ❌ Cannot view roles

### Commands Reference

```bash
# Sync base permissions (creates all 37 permissions)
sail artisan permissions:sync

# Sync plan permission maps (updates plan_enabled_permissions for all tenants)
sail artisan plans:sync-permissions

# Full setup (correct order)
sail artisan migrate
sail artisan permissions:sync
sail artisan db:seed --class=PlanSeeder
sail artisan plans:sync-permissions
```

### Documentation

**Complete Plans System documentation**:
- **[docs/PLANS-REFERENCE.md](PLANS-REFERENCE.md)** - Complete reference guide (READ FIRST)
- **[docs/PLANS-QUICK-START.md](PLANS-QUICK-START.md)** - Quick guide for common tasks
- **[docs/PLANS-README.md](PLANS-README.md)** - System overview and index

**See also**: `CLAUDE.md` Plans System section (line 291-337)

---

## Convenções de Nomenclatura

### ⚠️ IMPORTANTE: TypeScript Compatibility

**Padrão obrigatório**: Use **camelCase** para categories e actions com múltiplas palavras para gerar tipos TypeScript válidos.

**❌ ERRADO** (gera tipos inválidos):
```php
['name' => 'tenant.api-tokens:view', 'category' => 'api-tokens']  // ❌ Hífen
// Gera: export type Api-tokensPermission (INVÁLIDO no TypeScript)

['name' => 'tenant.team:manage-roles', 'category' => 'team']  // ❌ Hífen
// TypeScript quebra ao processar 'manage-roles'
```

**✅ CORRETO** (gera tipos válidos):
```php
['name' => 'tenant.apiTokens:view', 'category' => 'apiTokens']  // ✅ camelCase
// Gera: export type ApiTokensPermission (VÁLIDO no TypeScript)

['name' => 'tenant.team:manageRoles', 'category' => 'team']  // ✅ camelCase
// Gera: 'tenant.team:manageRoles' (VÁLIDO no TypeScript)
```

### Padrão de Nomenclatura

**Formato**: `tenant.{resource}:{action}`

**Examples**:
- ✅ `tenant.projects:view`
- ✅ `tenant.team:invite`
- ✅ `tenant.apiTokens:create` (camelCase para multi-word categories)
- ✅ `tenant.projects:editOwn` (camelCase para multi-word actions)
- ✅ `tenant.team:manageRoles` (camelCase para multi-word actions)
- ❌ `tenant.api-tokens:create` (NUNCA usar hífens em categories)
- ❌ `tenant.projects:edit-own` (NUNCA usar hífens em actions)

**Categories**: Sempre em camelCase quando tiver múltiplas palavras
- ✅ `apiTokens`, `userProfiles`, `customFields`
- ❌ `api-tokens`, `user-profiles`, `custom-fields`

**Actions**: Sempre em camelCase quando tiver múltiplas palavras
- ✅ `editOwn`, `manageRoles`, `viewAll`
- ❌ `edit-own`, `manage-roles`, `view-all`

---

## Comando de Sincronização

### Uso Básico

```bash
# Sincronizar permissions (insert or update)
./vendor/bin/sail artisan permissions:sync
```

### Opções

```bash
# Limpar tudo e recriar do zero (⚠️ CUIDADO: apaga tudo)
./vendor/bin/sail artisan permissions:sync --fresh
```

### O que o comando faz

1. ✅ Cria role "Super Admin" global (sem tenant_id)
2. ✅ Cria/atualiza todas as **37 permissions** (22 base + 15 enterprise)
3. ✅ Cria/atualiza as 3 roles (owner, admin, member)
4. ✅ Sincroniza permissions de cada role
5. ✅ Limpa cache do Spatie Permission
6. ✅ **Gera TypeScript types automaticamente**
7. ✅ Exibe summary com tabelas

**Output Exemplo**:
```
🔄 Syncing Roles & Permissions...

👑 Syncing Global Super Admin Role...
  ✓ Created global role: Super Admin
    → Bypasses all permission checks via Gate::before()

📝 Syncing Permissions...
  ✓ Created: tenant.projects:view
  ✓ Created: tenant.projects:editOwn
  ✓ Created: tenant.team:manageRoles
  ✓ Created: tenant.roles:view
  ✓ Created: tenant.reports:export
  ✓ Created: tenant.sso:configure
  ✓ Created: tenant.branding:uploadLogo
  ...
  ✅ 37 permissions created (22 base + 15 enterprise).

👥 Syncing Roles...
  ✓ Created role: owner (Proprietário)
    → Synced 22 base permissions (enterprise permissions are plan-gated)
  ✓ Created role: admin (Administrador)
    → Synced 13 permissions
  ✓ Created role: member (Membro)
    → Synced 6 permissions

📝 Generating TypeScript types...
✅ TypeScript types generated.

🎉 Roles & Permissions synced successfully!
```

**Note**: Enterprise permissions (15) are created but only accessible when tenant's plan includes the corresponding feature. See [Plans System Integration](#plans-system-integration).

---

## Backend: Como Usar Permissions

### ✅ Método Recomendado: `middleware()` static (Laravel 12+)

Use o método static `middleware()` no controller para manter permissions declaradas junto com a lógica:

```php
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TeamController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:tenant.team:view', only: ['index']),
            new Middleware('permission:tenant.team:invite', only: ['invite']),
            new Middleware('permission:tenant.team:manageRoles', only: ['updateRole']),
            new Middleware('permission:tenant.team:remove', only: ['remove']),
        ];
    }

    public function index()
    {
        // Autorização já feita pelo middleware
        $tenant = tenant();
        $members = $tenant->users()->get();

        return Inertia::render('tenant/team/index', ['members' => $members]);
    }
}
```

**Exemplo real**: `app/Http/Controllers/Tenant/TeamController.php:23-31`

### Usando Policies para Ownership

Para recursos que precisam verificar ownership (como projetos), use policies:

```php
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tenant.projects:view');
    }

    public function update(User $user, Project $project): bool
    {
        // Pode editar se tem permission "edit" (qualquer project)
        if ($user->can('tenant.projects:edit')) {
            return true;
        }

        // Ou se tem permission "editOwn" E é o criador
        return $user->can('tenant.projects:editOwn')
            && $project->user_id === $user->id;
    }
}
```

**Exemplo real**: `app/Policies/ProjectPolicy.php:39-48`

### Vantagens desta Abordagem

✅ **Auto-documentado**: Permissions visíveis ao abrir o controller
✅ **Rotas limpas**: Sem poluir o arquivo de rotas
✅ **Type-safe**: `only:` e `except:` evitam typos
✅ **Padrão Laravel 12**: Segue as convenções modernas
✅ **DRY**: Uma permission pode proteger múltiplos métodos

---

## Frontend: Como Usar Permissions

### 🎯 Hook `usePermissions()` - Método Recomendado

**Este é o método principal e recomendado para verificar permissions no frontend.**

```typescript
import { usePermissions } from '@/hooks/use-permissions';

export function ProjectsPage() {
  const { has, hasAny, hasAll, role, isOwner } = usePermissions();

  return (
    <div>
      {/* ✅ Single permission check */}
      {has('tenant.projects:create') && (
        <Button>Create Project</Button>
      )}

      {/* ✅ OR logic - user tem edit OU editOwn */}
      {hasAny('tenant.projects:edit', 'tenant.projects:editOwn') && (
        <Button>Edit Project</Button>
      )}

      {/* ✅ AND logic - user tem view E download */}
      {hasAll('tenant.projects:view', 'tenant.projects:download') && (
        <Button>Download Project</Button>
      )}

      {/* ✅ Role info (UI only - badges, display) */}
      {isOwner && <Badge>Owner</Badge>}
      <span>Role: {role?.name}</span>
    </div>
  );
}
```

**Métodos disponíveis**:
- `has(permission)` - Verifica single permission
- `hasAny(...permissions)` - OR logic (qualquer uma)
- `hasAll(...permissions)` - AND logic (todas)
- `all()` - Retorna array de todas permissions do user
- `role` - Role metadata (apenas para UI display)
- `isOwner`, `isAdmin`, `isAdminOrOwner`, `isSuperAdmin` - Atalhos para role checks

**Implementação**: `resources/js/hooks/use-permissions.ts`

### 🔧 Hook `useCan()` - Para Checks Simples

Ideal para verificações simples e inline:

```typescript
import { useCan } from '@/hooks/use-permissions';

export function CreateProjectButton() {
  const canCreate = useCan('tenant.projects:create');

  if (!canCreate) return null;

  return <Button>Create Project</Button>;
}
```

### 🎨 Componente `<Can>` - Para JSX Declarativo

Perfeito para JSX limpo e condicional:

```typescript
import { Can } from '@/components/can';

export function ProjectsPage() {
  return (
    <div>
      {/* ✅ Single permission */}
      <Can permission="tenant.projects:create">
        <CreateButton />
      </Can>

      {/* ✅ OR logic - qualquer uma das permissions */}
      <Can any={["tenant.projects:edit", "tenant.projects:editOwn"]}>
        <EditButton />
      </Can>

      {/* ✅ AND logic - todas as permissions */}
      <Can all={["tenant.projects:view", "tenant.projects:download"]}>
        <DownloadButton />
      </Can>

      {/* ✅ Com fallback */}
      <Can
        permission="tenant.billing:manage"
        fallback={<UpgradePrompt />}
      >
        <BillingSettings />
      </Can>
    </div>
  );
}
```

**Props disponíveis**:
- `permission?: Permission` - Single permission check
- `any?: Permission[]` - OR logic (qualquer uma)
- `all?: Permission[]` - AND logic (todas)
- `fallback?: ReactNode` - Renderizar se permissão negada

**Implementação**: `resources/js/components/can.tsx`

### ⚠️ Role Checks vs Permission Checks

**REGRA DE OURO**: Roles são apenas para UI display! Sempre use permissions para autorização.

```typescript
const { has, role, isOwner } = usePermissions();

// ❌ ERRADO: Usar role para autorização
{isOwner && <DeleteButton />}

// ✅ CORRETO: Usar permission para autorização
{has('tenant.projects:delete') && <DeleteButton />}

// ✅ OK: Usar role para UI display
<Badge>{role?.name}</Badge>  // "owner", "admin", "member"
{isOwner && <Crown className="text-yellow-500" />}  // Badge visual
```

**Por quê?**
- Roles podem ter permissions customizadas no futuro
- Segurança deve ser sempre via permissions
- Role é metadata para UX apenas

### 📚 Exemplos Completos

Ver arquivo com todos os exemplos práticos:

**`resources/js/examples/PermissionsUsageExample.tsx`**

Este arquivo contém:
- ✅ `usePermissions()` com has/hasAny/hasAll
- ✅ `useCan()` para single checks
- ✅ `<Can>` component com permission/any/all
- ✅ Exemplos de Projects, Team, Settings, Billing
- ✅ Role display vs permission authorization
- ✅ Complex permission logic
- ✅ Listing all user permissions
- ✅ Comparação de uso correto vs incorreto

### 🔄 Como Funciona (Internamente)

Permissions são automaticamente passadas do backend para o frontend via `HandleInertiaRequests` middleware:

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        'auth' => [
            'user' => $request->user(),
            'permissions' => $request->user()
                ? $request->user()->getAllPermissions()->pluck('name')
                : [],
            'role' => [
                'name' => $request->user()?->roles?->first()?->name,
                'isOwner' => /* ... */,
                'isAdmin' => /* ... */,
                // ...
            ],
        ],
    ];
}
```

### 📝 TypeScript Types

Os types são **auto-gerados** a partir do backend:

```typescript
// resources/js/types/permissions.d.ts (auto-generated)

export type Permission =
  | 'tenant.projects:view'
  | 'tenant.projects:create'
  | 'tenant.projects:edit'
  | 'tenant.projects:editOwn'  // camelCase
  | 'tenant.team:manageRoles'  // camelCase
  | 'tenant.apiTokens:view'    // camelCase
  // ... todas as 22 permissions

export interface Auth {
  user: any | null;
  permissions: Permission[];  // Array dinâmico
  role: Role | null;
}
```

**Vantagens**:
- ✅ Autocomplete funcionando em `has('tenant.pro...')` → sugere todas
- ✅ Compile-time errors se usar permission inexistente
- ✅ Único source of truth: `SyncPermissions.php`
- ✅ Sempre sincronizado com backend

---

## Como Adicionar Novas Permissions

### Passo 1: Editar o Comando

Abra `app/Console/Commands/SyncPermissions.php`:

```php
protected array $permissions = [
    // ... permissions existentes ...

    // Nova categoria (exemplo: Reports)
    ['name' => 'tenant.reports:view', 'description' => 'View reports', 'category' => 'reports'],
    ['name' => 'tenant.reports:export', 'description' => 'Export reports', 'category' => 'reports'],
];
```

**⚠️ IMPORTANTE**: Use camelCase para multi-word names:
- ✅ `tenant.userReports:viewAll`
- ❌ `tenant.user-reports:view-all`

### Passo 2: Adicionar às Roles

```php
protected array $roles = [
    'owner' => [
        'permissions' => [
            // ... permissions existentes ...
            'tenant.reports:view',
            'tenant.reports:export',
        ],
    ],
    'admin' => [
        'permissions' => [
            'tenant.reports:view',
            // admin não pode export
        ],
    ],
];
```

### Passo 3: Sincronizar

```bash
./vendor/bin/sail artisan permissions:sync
```

Isso irá:
1. Criar as novas permissions no banco
2. Atribuir às roles especificadas
3. **Gerar TypeScript types automaticamente**

### Passo 4: Usar no Frontend

```typescript
// Autocomplete já funcionando!
const { has } = usePermissions();

{has('tenant.reports:export') && (
  <Button onClick={handleExport}>Export Report</Button>
)}
```

### Passo 5: Testar

```php
// tests/Feature/ReportTest.php
#[Test]
public function admin_cannot_export_reports()
{
    $admin = $this->createTenantUser('admin');
    $this->actingAs($admin);

    $response = $this->get('/reports/export');
    $response->assertForbidden();
}
```

---

## Roles MVP

### Owner (Proprietário)

**Base Permissions**: 22 (todas as permissions base)

**Enterprise Permissions**: Acesso a enterprise permissions é **plan-gated** (depende do plano do tenant)

**Descrição**: Acesso total incluindo billing e danger zone. Acesso a features enterprise (roles, reports, sso, branding) depende do plano do tenant.

**Quando usar**:
- Criador do tenant
- Pessoa que paga a conta
- Responsável legal

**Plan-Gated Access**:
- ✅ All 22 base permissions (always available)
- 🔒 Custom Roles (4 permissions) - Requires `customRoles` feature (Professional+)
- 🔒 Advanced Reports (4 permissions) - Requires `advancedReports` feature (Enterprise)
- 🔒 SSO (3 permissions) - Requires `sso` feature (Enterprise)
- 🔒 White Label (4 permissions) - Requires `whiteLabel` feature (Enterprise)

### Admin (Administrador)

**Permissions**: 13

**Descrição**: Gerencia projetos e equipe, mas sem acesso a billing e danger zone.

**Não pode**:
- ❌ Gerenciar billing
- ❌ Deletar projetos
- ❌ Acessar danger zone
- ❌ Gerenciar API tokens

**Pode**:
- ✅ Editar qualquer projeto
- ✅ Convidar/remover membros
- ✅ Gerenciar roles (manageRoles)
- ✅ Editar settings

### Member (Membro)

**Permissions**: 6

**Descrição**: Cria e edita próprios projetos. Read-only para team e settings.

**Não pode**:
- ❌ Editar projetos de outros (só próprios via editOwn)
- ❌ Deletar projetos
- ❌ Convidar pessoas
- ❌ Gerenciar billing
- ❌ Editar settings

**Pode**:
- ✅ Ver todos os projetos (view)
- ✅ Criar novos projetos (create)
- ✅ Editar próprios projetos (editOwn)
- ✅ Download de arquivos (download)
- ✅ Ver team members (view)
- ✅ Ver settings (view)

---

## Arquitetura

### Database Schema

```sql
-- permissions table
id, tenant_id, name, guard_name, description, category, created_at, updated_at
UNIQUE(tenant_id, name, guard_name)

-- roles table
id, tenant_id, name, guard_name, description, display_name, created_at, updated_at
UNIQUE(tenant_id, name, guard_name)

-- role_has_permissions (pivot)
permission_id, role_id

-- model_has_permissions (User permissions)
permission_id, model_type, model_id, tenant_id

-- model_has_roles (User roles)
role_id, model_type, model_id, tenant_id
```

### Integração com Tenancy

**CRÍTICO**: Sempre que `tenancy()->initialize()` é chamado, deve-se chamar `setPermissionsTeamId()`:

```php
tenancy()->initialize($tenant);
setPermissionsTeamId($tenant->id);  // OBRIGATÓRIO

// Agora permissions funcionam corretamente
$user->assignRole('admin');
```

**Locais que fazem isso**:
- ✅ `VerifyTenantAccess` middleware
- ✅ `TenantTestCase::setUp()`
- ✅ `User::roleOn()`
- ✅ `TeamController::acceptInvitation()`

### Performance: Zero Queries na Verificação de Acesso

O middleware `VerifyTenantAccess` foi otimizado para **zero queries** ao verificar se o usuário pertence ao tenant.

#### ✅ Solução Otimizada

```php
// Usa cache existente do Spatie Permission
setPermissionsTeamId($tenantId);
$user->unsetRelation('roles')->unsetRelation('permissions');

// Se user tem qualquer role no tenant = pertence ao tenant
// Usa cache do Spatie - ZERO queries!
$hasAnyRole = $user->getRoleNames()->isNotEmpty();
```

**Benefícios**:
- ✅ **Zero queries** após cache aquecido (~0.1ms vs 5-20ms)
- ✅ **99% redução**: 1000 queries/min → ~0 queries/min
- ✅ **Usa cache existente** do Spatie Permission
- ✅ **Future-proof** - Funciona com qualquer role

### Super Admin (Global Platform Admin)

Super Admin é uma **role global** (sem `tenant_id`) que bypassa todas as permissions:

```php
// app/Providers/AppServiceProvider.php
Gate::before(function (User $user, string $ability) {
    $currentTeamId = getPermissionsTeamId();
    setPermissionsTeamId(null);
    $isSuperAdmin = $user->hasRole('Super Admin');
    setPermissionsTeamId($currentTeamId);

    return $isSuperAdmin ? true : null;
});
```

**Características**:
- ✅ Role Global: `tenant_id = null`
- ✅ Bypass Total: Ignora todas as verificações
- ✅ Multi-Tenant: Acessa qualquer tenant
- ✅ Frontend: Disponível em `auth.role.isSuperAdmin`

**Quando Usar**:
- **Super Admin**: Administrador da plataforma, suporte técnico
- **Owner**: Dono de um tenant específico

---

## Testing

### Setup em TenantTestCase

```php
protected function setUp(): void
{
    parent::setUp();

    tenancy()->initialize($this->tenant);
    setPermissionsTeamId($this->tenant->id);

    // Sync permissions (chama permissions:sync)
    $this->syncPermissionsForTests();

    // Owner role já existe após sync
    $ownerRole = Role::findByName('owner', 'web');
    $this->user->assignRole($ownerRole);
}
```

### Testar Permissions

```php
#[Test]
public function member_cannot_delete_projects()
{
    $member = $this->createTenantUser('member');
    $this->actingAs($member);

    $project = Project::factory()->create();

    $response = $this->delete("/projects/{$project->id}");
    $response->assertForbidden();
}

#[Test]
public function member_can_edit_own_project()
{
    $member = $this->createTenantUser('member');
    $this->actingAs($member);

    // Projeto do próprio member
    $project = Project::factory()->create(['user_id' => $member->id]);

    $response = $this->patch("/projects/{$project->id}", [
        'name' => 'Updated by Member',
    ]);

    $response->assertRedirect();
}
```

### Helper para Criar Users com Roles

```php
// Usar helper do TenantTestCase
$owner = $this->user;  // Owner já criado no setUp
$admin = $this->createTenantUser('admin');
$member = $this->createTenantUser('member');

// Roles válidas: owner, admin, member
```

---

## Troubleshooting

### Permission não funciona

**Problema**: `$user->can('tenant.projects:view')` retorna `false`.

**Soluções**:

1. Verificar se permission existe:
```bash
./vendor/bin/sail artisan tinker
>>> \App\Models\Permission::where('name', 'tenant.projects:view')->first();
```

2. Verificar se role tem a permission:
```bash
>>> $role = \App\Models\Role::findByName('admin', 'web');
>>> $role->permissions->pluck('name');
```

3. Verificar se `setPermissionsTeamId()` foi chamado:
```php
tenancy()->initialize($tenant);
setPermissionsTeamId($tenant->id);  // OBRIGATÓRIO
```

4. Limpar cache:
```bash
./vendor/bin/sail artisan permission:cache-reset
```

### TypeScript Types Desatualizados

**Problema**: Autocomplete não mostra novas permissions.

**Solução**:
```bash
./vendor/bin/sail artisan permissions:sync
# OU
./vendor/bin/sail artisan permissions:generate-types
```

### Permissions com Hífens Não Funcionam no Frontend

**Problema**: `has('tenant.projects:edit-own')` não funciona.

**Solução**: Use camelCase: `has('tenant.projects:editOwn')`

**Migração**:
1. Atualize `SyncPermissions.php` com nomes camelCase
2. Rode `sail artisan permissions:sync`
3. Atualize código frontend com novos nomes

---

## Referências

**Code Files**:
- **Hooks**: `resources/js/hooks/use-permissions.ts`
- **Component**: `resources/js/components/can.tsx`
- **Exemplos**: `resources/js/examples/PermissionsUsageExample.tsx`
- **Comando Sync**: `app/Console/Commands/SyncPermissions.php`
- **Policies**: `app/Policies/ProjectPolicy.php`
- **Controllers**: `app/Http/Controllers/Tenant/TeamController.php`
- **Middleware**: `app/Http/Middleware/HandleInertiaRequests.php`
- **Observer**: `app/Observers/TenantObserver.php` (plan permission sync)
- **Plan Model**: `app/Models/Plan.php` (wildcard expansion)

**Plans System Documentation**:
- **[PLANS-REFERENCE.md](PLANS-REFERENCE.md)** - Complete reference guide (READ FIRST)
- **[PLANS-QUICK-START.md](PLANS-QUICK-START.md)** - Quick guide for common tasks
- **[PLANS-README.md](PLANS-README.md)** - System overview and index

**Project Documentation**:
- **[CLAUDE.md](../CLAUDE.md)** - Project overview (includes Plans System section)
- **[STANCL-FEATURES.md](STANCL-FEATURES.md)** - Multi-tenancy integration

**External Documentation**:
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
- [Laravel Pennant](https://laravel.com/docs/pennant) (feature flags)
- [Stancl Tenancy](https://tenancyforlaravel.com/)
- [Laravel Authorization](https://laravel.com/docs/authorization)

---

## Changelog

### v3.0.0 (2025-11-21)

**Plans System Integration + Enterprise Permissions** 🚀:

**New Features**:
- ✅ **15 Enterprise Permissions**: Custom Roles (4), Advanced Reports (4), SSO (3), White Label (4)
- ✅ **Total: 37 permissions** (22 base + 15 enterprise)
- ✅ **Plans System Integration**: Permissions now integrated with Hybrid Plans Architecture
- ✅ **Two-Level Authorization**: Plan-level (tenant) + Permission-level (user)
- ✅ **Wildcard Expansion**: `tenant.roles:*` → expands to all 4 roles permissions
- ✅ **Auto-Sync on Plan Change**: TenantObserver regenerates permissions automatically
- ✅ **Gate::before()**: Checks `plan_enabled_permissions` before Spatie check

**Documentation**:
- ✅ New section: **Plans System Integration** (160+ lines)
- ✅ Enterprise permissions documented with plan requirements
- ✅ Wildcard expansion explained
- ✅ Two-level authorization flow diagram
- ✅ Frontend + Backend examples for plan-gated permissions
- ✅ Links to complete Plans System documentation

**Files Updated**:
- `docs/PERMISSIONS.md` - Added 15 enterprise permissions + Plans integration
- `app/Console/Commands/SyncPermissions.php` - Creates all 37 permissions
- `app/Observers/TenantObserver.php` - Auto-syncs permissions on plan change
- `app/Providers/AppServiceProvider.php` - Gate::before() checks plan permissions

**Breaking Changes**:
- None - Existing 22 base permissions unchanged
- Enterprise permissions are additive and plan-gated

### v2.2.0 (2025-11-20)

**camelCase Convention + Documentation Update** 🎨:

**Permission Naming Standardization**:
- ✅ **Categories**: `tenant.apiTokens:*` (was `tenant.api-tokens:*`)
- ✅ **Actions**: `tenant.projects:editOwn` (was `edit-own`)
- ✅ **Actions**: `tenant.team:manageRoles` (was `manage-roles`)
- ✅ **TypeScript**: All generated types now valid (no hyphens)
- ✅ **Convention**: camelCase for all multi-word names

**Files Updated (11 total)**:
- Backend: `SyncPermissions.php`, `TeamController.php`, `ApiTokenController.php`, `ProjectPolicy.php`
- Frontend: `team/index.tsx`, `PermissionsUsageExample.tsx`, `can.tsx`, `use-permissions.ts`
- Docs: `PERMISSIONS.md`, `permissions.d.ts` (auto-generated)

**Database Cleanup**:
- ✅ Deleted old hyphenated permissions
- ✅ Verified 22 unique camelCase permission names
- ✅ Re-synced all permissions and roles

**Documentation Rewrite**:
- ✅ **Focused on `usePermissions()` hook** as primary method
- ✅ **Removed outdated frontend references**
- ✅ **Added complete examples** from `PermissionsUsageExample.tsx`
- ✅ **Clear comparison**: Role checks (UI only) vs Permission checks (authorization)
- ✅ **Step-by-step guide** for adding new permissions with camelCase
- ✅ **Troubleshooting section** for hyphenated permission migration

### v2.1.0 (2025-11-20)

**Super Admin como Role Global + Performance** 🚀

### v2.0.0 (2025-11-20)

**Sistema Dinâmico de Permissões** 🚀

### v1.0.0 (2025-11-20)

**Initial Release**
