# Permissions & Roles System

## Visão Geral

Este projeto utiliza um sistema robusto de permissões baseado em [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) integrado com multi-tenancy via [Stancl Tenancy](https://tenancyforlaravel.com/).

**Características**:
- ✅ **Idempotente**: Comando `permissions:sync` pode rodar múltiplas vezes sem duplicar
- ✅ **Tenant-isolated**: Permissions e roles são isoladas por tenant
- ✅ **Metadata rica**: Cada permission tem `description` e `category`
- ✅ **Display names**: Roles têm `display_name` para UI
- ✅ **Nomenclatura clara**: `tenant.resource:action` (ex: `tenant.projects:view`)

---

## 📋 Índice

1. [Estrutura Completa](#estrutura-completa)
2. [Comando de Sincronização](#comando-de-sincronização)
3. [Como Usar Permissions](#como-usar-permissions)
4. [Como Adicionar Novas Permissions](#como-adicionar-novas-permissions)
5. [Roles MVP](#roles-mvp)
6. [Arquitetura](#arquitetura)
7. [Testing](#testing)
8. [Troubleshooting](#troubleshooting)

---

## Estrutura Completa

### Permissions (19 total)

#### 📦 Projects (8 permissions)

| Permission | Descrição | Owner | Admin | Member |
|------------|-----------|-------|-------|--------|
| `tenant.projects:view` | View all projects | ✅ | ✅ | ✅ |
| `tenant.projects:create` | Create new projects | ✅ | ✅ | ✅ |
| `tenant.projects:edit` | Edit any project | ✅ | ✅ | ❌ |
| `tenant.projects:edit-own` | Edit own projects only | ✅ | ❌ | ✅ |
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
| `tenant.team:manage-roles` | Manage roles | ✅ | ✅ | ❌ |
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

---

## Comando de Sincronização

### Uso Básico

```bash
# Sincronizar permissions (insert or update)
php artisan permissions:sync

# Com Sail
./vendor/bin/sail artisan permissions:sync
```

### Opções

```bash
# Limpar tudo e recriar do zero (⚠️ CUIDADO: apaga tudo)
php artisan permissions:sync --fresh
```

### O que o comando faz

1. ✅ Cria/atualiza todas as 19 permissions
2. ✅ Cria/atualiza as 3 roles (owner, admin, member)
3. ✅ Sincroniza permissions de cada role
4. ✅ Limpa cache do Spatie Permission
5. ✅ Exibe summary com tabelas

**Output Exemplo**:
```
🔄 Syncing Roles & Permissions...

📝 Syncing Permissions...
  ✓ Created: tenant.projects:view
  ✓ Created: tenant.projects:create
  ...
  ✅ 19 permissions created, 0 updated.

👥 Syncing Roles...
  ✓ Created role: owner (Proprietário)
    → Synced 19 permissions
  ✓ Created role: admin (Administrador)
    → Synced 13 permissions
  ✓ Created role: member (Membro)
    → Synced 6 permissions
  ✅ 3 roles created, 0 updated.

📊 Summary:
+----------+-------+
| Category | Count |
+----------+-------+
| projects | 8     |
| team     | 5     |
| settings | 3     |
| billing  | 3     |
| TOTAL    | 19    |
+----------+-------+
```

---

## Como Usar Permissions

### ✅ Abordagem Recomendada: `middleware()` static no Controller (Laravel 12+)

A melhor prática é usar o método static `middleware()` no controller. Mantém as permissions declaradas junto com a lógica de negócio e as rotas limpas.

```php
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TeamController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:tenant.team:view', only: ['index']),
            new Middleware('permission:tenant.team:invite', only: ['invite']),
            new Middleware('permission:tenant.team:manage-roles', only: ['updateRole']),
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

### Usando Policies para Ownership (Projetos)

Para recursos que precisam verificar ownership, use policies com `can:`:

```php
class ProjectController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            // Permissions diretas (sem model binding)
            new Middleware('permission:tenant.projects:view', only: ['index']),
            new Middleware('permission:tenant.projects:create', only: ['create', 'store']),

            // Policies (com model binding - verificam ownership)
            new Middleware('can:view,project', only: ['show']),
            new Middleware('can:update,project', only: ['edit', 'update']),
            new Middleware('can:delete,project', only: ['destroy']),
        ];
    }
}
```

### Vantagens desta Abordagem

✅ **Auto-documentado**: Permissions visíveis ao abrir o controller
✅ **Rotas limpas**: Sem poluir o arquivo de rotas
✅ **Type-safe**: `only:` e `except:` evitam typos
✅ **Padrão Laravel 12**: Segue as convenções modernas
✅ **DRY**: Uma permission pode proteger múltiplos métodos
✅ **Melhor que Gate::authorize()**: Falha mais cedo (antes do método executar)

### Em Policies

```php
namespace App\Policies;

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

        // Ou se tem "edit-own" E é o criador
        return $user->can('tenant.projects:edit-own')
            && $project->user_id === $user->id;
    }
}
```

### Em React/Inertia (Frontend)

**IMPORTANTE**: Permissions são **automaticamente** passadas para o frontend via `HandleInertiaRequests` middleware.

#### Acesso via usePage()

```tsx
import { usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import { Button } from '@/components/ui/button';

function ProjectsPage() {
    const { auth } = usePage<PageProps>().props;
    const { permissions } = auth;

    return (
        <div>
            {/* ✅ RECOMENDADO: Permissions granulares */}
            {permissions?.projects.create && (
                <Button onClick={handleCreate}>
                    Create Project
                </Button>
            )}

            {permissions?.projects.edit && (
                <Button onClick={handleEdit}>
                    Edit
                </Button>
            )}

            {permissions?.projects.delete && (
                <Button variant="destructive" onClick={handleDelete}>
                    Delete
                </Button>
            )}

            {permissions?.projects.upload && (
                <input type="file" onChange={handleUpload} />
            )}
        </div>
    );
}
```

#### Permissions Disponíveis no Frontend

Todas as permissions são automaticamente passadas em:
```tsx
auth.permissions.projects.{view, create, edit, editOwn, delete, upload, download, archive}
auth.permissions.team.{view, invite, remove, manageRoles, activity}
auth.permissions.settings.{view, edit, danger}
auth.permissions.billing.{view, manage, invoices}
```

#### Exemplo Completo de Componente

```tsx
// resources/js/pages/projects/index.tsx
import { usePage, Link } from '@inertiajs/react';
import { PageProps } from '@/types';
import { Button } from '@/components/ui/button';
import { PlusIcon, TrashIcon } from 'lucide-react';

export default function ProjectsIndex({ projects }: { projects: Project[] }) {
    const { auth } = usePage<PageProps>().props;
    const { permissions } = auth;

    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <h1>Projects</h1>

                {/* Show create button only if user can create */}
                {permissions?.projects.create && (
                    <Link href="/projects/create">
                        <Button>
                            <PlusIcon className="mr-2" />
                            Create Project
                        </Button>
                    </Link>
                )}
            </div>

            <div className="grid gap-4">
                {projects.map((project) => {
                    // Can edit if has global edit OR (has edit-own AND is owner)
                    const canEdit = permissions?.projects.edit ||
                        (permissions?.projects.editOwn && project.user_id === auth.user?.id);

                    return (
                        <div key={project.id} className="border p-4 rounded">
                            <h3>{project.name}</h3>

                            <div className="flex gap-2 mt-4">
                                {/* Edit button */}
                                {canEdit && (
                                    <Link href={`/projects/${project.id}/edit`}>
                                        <Button variant="outline">Edit</Button>
                                    </Link>
                                )}

                                {/* Delete button - only if user can delete */}
                                {permissions?.projects.delete && (
                                    <Button
                                        variant="destructive"
                                        onClick={() => handleDelete(project.id)}
                                    >
                                        <TrashIcon className="mr-2" />
                                        Delete
                                    </Button>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
```

#### Helper Hooks (Opcional)

Criar hook customizado para simplificar uso:

```tsx
// resources/js/hooks/use-permissions.ts
import { usePage } from '@inertiajs/react';
import { PageProps } from '@/types';

export function usePermissions() {
    const { auth } = usePage<PageProps>().props;
    return auth.permissions;
}

export function useHasPermission(path: string): boolean {
    const permissions = usePermissions();
    const [category, action] = path.split('.');

    if (!permissions || !category || !action) return false;

    return permissions[category]?.[action] ?? false;
}

// Uso:
function MyComponent() {
    const canCreate = useHasPermission('projects.create');
    const canDelete = useHasPermission('projects.delete');

    return (
        <div>
            {canCreate && <Button>Create</Button>}
            {canDelete && <Button>Delete</Button>}
        </div>
    );
}
```

#### ⚠️ IMPORTANTE: Role Checks no Frontend

**Roles são apenas para display (badges, labels) - NÃO para autorização!**

```tsx
// ✅ OK: Usar role para display
<div className="badge">
    {permissions?.isOwner && <span>Owner</span>}
    {permissions?.isAdmin && <span>Admin</span>}
    {permissions?.role === 'member' && <span>Member</span>}
</div>

// ❌ ERRADO: Não usar role para autorização
{permissions?.isOwner && <Button>Delete Project</Button>}

// ✅ CORRETO: Usar permission
{permissions?.projects.delete && <Button>Delete Project</Button>}
```

#### Exemplo Completo

Ver arquivo completo com mais exemplos:
```
resources/js/examples/PermissionsUsageExample.tsx
```

### Em Middleware

```php
// routes/tenant.php
Route::middleware(['auth', 'can:tenant.projects:create'])
    ->post('/projects', [ProjectController::class, 'store']);
```

### Check Direto no User

```php
$user = auth()->user();

// Check single permission
if ($user->can('tenant.projects:view')) {
    // ...
}

// Check multiple permissions (any)
if ($user->hasAnyPermission(['tenant.projects:edit', 'tenant.projects:delete'])) {
    // ...
}

// Check multiple permissions (all)
if ($user->hasAllPermissions(['tenant.projects:view', 'tenant.projects:create'])) {
    // ...
}

// Via role (menos recomendado)
if ($user->hasRole('owner')) {
    // ...
}
```

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

### Passo 2: Adicionar às Roles

```php
protected array $roles = [
    'owner' => [
        'display_name' => 'Proprietário',
        'description' => 'Acesso total incluindo billing',
        'permissions' => [
            // ... permissions existentes ...
            'tenant.reports:view',
            'tenant.reports:export',
        ],
    ],
    'admin' => [
        // ... adicionar apenas tenant.reports:view
    ],
    // ...
];
```

### Passo 3: Sincronizar

```bash
php artisan permissions:sync
```

### Passo 4: Usar no Código

```php
// Em Policy
public function exportReport(User $user): bool
{
    return $user->can('tenant.reports:export');
}

// Em Controller
Gate::authorize('tenant.reports:export');
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

**Permissions**: 19 (todas)

**Descrição**: Acesso total incluindo billing e danger zone.

**Quando usar**:
- Criador do tenant
- Pessoa que paga a conta
- Responsável legal

**Limitações**: Nenhuma (acesso completo)

---

### Admin (Administrador)

**Permissions**: 13

**Descrição**: Gerencia projetos e equipe, mas sem acesso a billing e danger zone.

**Quando usar**:
- Gerente de projeto
- Team lead
- Pessoa de confiança que pode gerenciar tudo exceto billing

**Não pode**:
- ❌ Gerenciar billing
- ❌ Deletar projetos
- ❌ Acessar danger zone (delete tenant)
- ❌ Upload de arquivos (apenas owner e admin com tenant.projects:upload)

**Pode**:
- ✅ Editar qualquer projeto
- ✅ Convidar/remover membros
- ✅ Gerenciar roles
- ✅ Editar settings

---

### Member (Membro)

**Permissions**: 6

**Descrição**: Cria e edita próprios projetos. Read-only para team e settings.

**Quando usar**:
- Colaborador individual
- Desenvolvedor
- Designer
- Qualquer pessoa que trabalha no tenant

**Não pode**:
- ❌ Editar projetos de outros
- ❌ Deletar projetos
- ❌ Convidar pessoas
- ❌ Gerenciar billing
- ❌ Editar settings

**Pode**:
- ✅ Ver todos os projetos
- ✅ Criar novos projetos
- ✅ Editar próprios projetos
- ✅ Download de arquivos
- ✅ Ver team members
- ✅ Ver settings

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

**Critical**: Sempre que `tenancy()->initialize()` é chamado, deve-se chamar `setPermissionsTeamId()`:

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
- ✅ `User::switchToTenant()`
- ✅ `TeamController::acceptInvitation()`
- ✅ `Tenant::getUsersByRole()`

### Super Admin Bypass

O sistema tem um bypass para super admins (acesso total):

```php
// AuthServiceProvider.php
Gate::before(function (User $user, string $ability) {
    if ($user->is_super_admin) {
        return true;
    }
});
```

**Importante**: Para usuários normais (incluindo owners), **TODAS as permissions** devem ser atribuídas explicitamente via roles. Não há bypass automático.

---

## Testing

### Setup em TenantTestCase

```php
protected function setUp(): void
{
    parent::setUp();

    // ... criar tenant e user ...

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
public function admin_can_edit_any_project()
{
    $admin = $this->createTenantUser('admin');
    $this->actingAs($admin);

    $project = Project::factory()->create([
        'user_id' => $this->user->id, // projeto do owner
    ]);

    $response = $this->patch("/projects/{$project->id}", [
        'name' => 'Updated by Admin',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => 'Updated by Admin',
    ]);
}
```

### Helper para Criar Users com Roles

```php
// Usar helper do TenantTestCase
$admin = $this->createTenantUser('admin');
$member = $this->createTenantUser('member');

// Roles válidas: owner, admin, member
```

---

## Troubleshooting

### Permission não funciona

**Problema**: `$user->can('tenant.projects:view')` retorna `false` mesmo tendo a role correta.

**Soluções**:
1. Verificar se permission existe:
```bash
php artisan tinker
>>> \App\Models\Permission::where('name', 'tenant.projects:view')->get();
```

2. Verificar se role tem a permission:
```bash
>>> \App\Models\Role::with('permissions')->find(1);
```

3. Verificar se `setPermissionsTeamId()` foi chamado:
```php
// Deve ser chamado após tenancy()->initialize()
tenancy()->initialize($tenant);
setPermissionsTeamId($tenant->id);  // OBRIGATÓRIO
```

4. Limpar cache:
```bash
php artisan permission:cache-reset
```

---

### Role não encontrada em testes

**Problema**: `RoleDoesNotExist` exception em testes.

**Solução**: Garantir que `syncPermissionsForTests()` é chamado no `setUp()`:

```php
protected function setUp(): void
{
    parent::setUp();
    // ...
    tenancy()->initialize($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    $this->syncPermissionsForTests();  // ← Isso cria todas as roles
}
```

---

### Permissions duplicadas

**Problema**: Rodando `permissions:sync` várias vezes criou duplicatas.

**Solução**: O comando é idempotente e **não deveria** criar duplicatas. Se aconteceu:

1. Limpar tudo:
```bash
php artisan permissions:sync --fresh
```

2. Verificar unique constraints:
```sql
SELECT name, guard_name, tenant_id, COUNT(*)
FROM permissions
GROUP BY name, guard_name, tenant_id
HAVING COUNT(*) > 1;
```

---

### Owner bypass não funciona

**Problema**: Owner não consegue fazer algo que deveria poder.

**Verificações**:
1. Tem a role `owner`?
```php
$user->hasRole('owner'); // deve ser true
```

2. É permission nova (com `tenant.` prefix)?
```php
// Owner bypass NÃO funciona para permissions tenant.*
// Owner precisa ter a permission via role
$owner->hasPermissionTo('tenant.projects:delete'); // deve ser true
```

3. Verificar se owner role tem todas as permissions:
```bash
php artisan permissions:sync  # Re-sincronizar
```

---

## Sistema Dinâmico de Permissões (Frontend)

### Visão Geral

O sistema de permissões frontend foi projetado para ser **dinâmico**, **escalável** e **type-safe**:

- ✅ **Dinâmico**: Envia apenas permissions que o usuário TEM (não todas com booleans)
- ✅ **Performático**: 1 query vs 19+ queries (`getAllPermissions` vs múltiplos `can()`)
- ✅ **Escalável**: Funciona com 100+ permissions sem impacto
- ✅ **Type-safe**: Types TypeScript auto-gerados do backend
- ✅ **Flexível**: Múltiplas formas de verificar permissions (hook, component, inline)

###  Geração Automática de TypeScript Types

As permissions TypeScript são **auto-geradas** a partir do backend, garantindo sincronização perfeita.

#### Gerar Types

```bash
# Gerar manualmente
php artisan permissions:generate-types

# Automaticamente ao rodar permissions:sync
php artisan permissions:sync  # já gera os types
```

#### Arquivo Gerado

`resources/js/types/permissions.d.ts`:

```typescript
/**
 * Auto-generated TypeScript types for Laravel permissions
 * DO NOT EDIT THIS FILE MANUALLY
 */

export type Permission =
  | 'tenant.projects:view'
  | 'tenant.projects:create'
  | 'tenant.projects:edit'
  // ... todas as 19 permissions

export interface Role {
  name: string | null;
  isOwner: boolean;
  isAdmin: boolean;
  isAdminOrOwner: boolean;
}

export interface Auth {
  user: any | null;
  permissions: Permission[];  // Array simples
  role: Role | null;
}
```

**Vantagens**:
- Autocomplete funcionando em `has('tenant.pro...')` → sugere todas
- Compile-time errors se usar permission inexistente
- Único source of truth: `SyncPermissions.php`
- Sempre sincronizado com backend

### 🎯 Frontend: Como Usar Permissions

#### Abordagem 1: Hook `usePermissions()`

**Recomendado para múltiplas verificações ou lógica complexa.**

```typescript
import { usePermissions } from '@/hooks/use-permissions';

export function ProjectsPage() {
  const { has, hasAny, hasAll, role } = usePermissions();

  return (
    <div>
      {/* Single permission */}
      {has('tenant.projects:create') && <CreateButton />}

      {/* OR logic - user tem edit OU edit-own */}
      {hasAny('tenant.projects:edit', 'tenant.projects:edit-own') && (
        <EditButton />
      )}

      {/* AND logic - user tem view E download */}
      {hasAll('tenant.projects:view', 'tenant.projects:download') && (
        <DownloadButton />
      )}

      {/* Role info (UI only - não usar para autorização!) */}
      <Badge>{role?.name}</Badge>
    </div>
  );
}
```

**Métodos disponíveis**:
- `has(permission)` - Verifica single permission
- `hasAny(...permissions)` - OR logic
- `hasAll(...permissions)` - AND logic
- `all()` - Retorna array de todas permissions do user
- `role` - Role metadata (apenas para UI)
- `isOwner`, `isAdmin`, `isAdminOrOwner` - Atalhos para role checks

#### Abordagem 2: Hook `useCan()`

**Ideal para verificações simples e inline.**

```typescript
import { useCan } from '@/hooks/use-permissions';

export function CreateProjectButton() {
  const canCreate = useCan('tenant.projects:create');

  if (!canCreate) return null;

  return <Button>Create Project</Button>;
}
```

#### Abordagem 3: Componente `<Can>`

**Perfeito para JSX limpo e declarativo.**

```typescript
import { Can } from '@/components/can';

export function ProjectsPage() {
  return (
    <div>
      {/* Single permission */}
      <Can permission="tenant.projects:create">
        <CreateButton />
      </Can>

      {/* OR logic */}
      <Can any={["tenant.projects:edit", "tenant.projects:edit-own"]}>
        <EditButton />
      </Can>

      {/* AND logic */}
      <Can all={["tenant.projects:view", "tenant.projects:download"]}>
        <DownloadButton />
      </Can>

      {/* Com fallback */}
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

### ⚠️ Role Checks vs Permission Checks

**IMPORTANTE**: Roles são apenas para UI display! Sempre use permissions para autorização.

```typescript
const { has, role, isOwner } = usePermissions();

// ❌ ERRADO: Usar role para autorização
{isOwner && <DeleteButton />}

// ✅ CORRETO: Usar permission
{has('tenant.projects:delete') && <DeleteButton />}

// ✅ OK: Usar role para UI display
<Badge>{role?.name}</Badge>  // "owner", "admin", "member"
{isOwner && <Crown className="text-yellow-500" />}  // Badge visual
```

**Por quê?**
- Roles podem ter permissions customizadas no futuro
- Segurança deve ser sempre via permissions
- Role é metadata para UX apenas

### 📊 Payload Comparison

**Antes (Nested Structure)**:
```json
{
  "permissions": {
    "projects": {
      "view": true,
      "create": true,
      "edit": false,
      "editOwn": true,
      "delete": false,
      "upload": false,
      "download": true,
      "archive": false
    },
    "team": { /* 5 booleans */ },
    "settings": { /* 3 booleans */ },
    "billing": { /* 3 booleans */ },
    "role": "member",
    "isOwner": false,
    "isAdmin": false,
    "isAdminOrOwner": false
  }
}
```
**Payload**: ~500 bytes, 19+ queries

**Depois (Dynamic Array)**:
```json
{
  "permissions": [
    "tenant.projects:view",
    "tenant.projects:create",
    "tenant.projects:edit-own",
    "tenant.projects:download",
    "tenant.team:view",
    "tenant.settings:view"
  ],
  "role": {
    "name": "member",
    "isOwner": false,
    "isAdmin": false,
    "isAdminOrOwner": false
  }
}
```
**Payload**: ~150 bytes (member), 1 query

**Benefícios**:
- 70% menos dados trafegando
- 95% menos queries (1 vs 19+)
- 100+ permissions sem impacto
- Type-safe com autocomplete

### 🔄 Migration Guide (Old → New)

Se você tem código usando o sistema antigo, migre assim:

```typescript
// ❌ OLD: Nested structure
const { permissions } = auth;
if (permissions?.projects.create) { /* ... */ }

// ✅ NEW: usePermissions hook
const { has } = usePermissions();
if (has('tenant.projects:create')) { /* ... */ }
```

```typescript
// ❌ OLD: Can with legacy names
<Can permission="canManageTeam">
  <Button />
</Can>

// ✅ NEW: Can with granular permissions
<Can permission="tenant.team:invite">
  <Button />
</Can>
```

```typescript
// ❌ OLD: Direct access to permissions object
{permissions?.isOwner && <Badge>Owner</Badge>}

// ✅ NEW: usePermissions hook
const { isOwner } = usePermissions();
{isOwner && <Badge>Owner</Badge>}
```

### 📚 Exemplos Práticos

Ver arquivo completo com exemplos: `resources/js/examples/PermissionsUsageExample.tsx`

Contém exemplos de:
- ✅ Hook `usePermissions()` com has/hasAny/hasAll
- ✅ Hook `useCan()` para single checks
- ✅ Component `<Can>` com permission/any/all
- ✅ Role display vs permission authorization
- ✅ Complex permission logic
- ✅ Listing all user permissions

---

## Referências

- [Spatie Laravel Permission Docs](https://spatie.be/docs/laravel-permission)
- [Stancl Tenancy Docs](https://tenancyforlaravel.com/)
- [Laravel Authorization Docs](https://laravel.com/docs/authorization)

---

## Changelog

### v2.0.0 (2025-11-20)

**Sistema Dinâmico de Permissões** 🚀:
- ✅ **Performance**: Redução de 95% em queries (1 vs 19+)
- ✅ **Payload**: 70% menor (~150 bytes vs ~500 bytes para member)
- ✅ **TypeScript auto-gerado**: Comando `permissions:generate-types`
- ✅ **Type-safe**: Union type `Permission` com autocomplete completo
- ✅ **Hooks refatorados**: `usePermissions()` com has/hasAny/hasAll
- ✅ **Component refatorado**: `<Can>` com permission/any/all props
- ✅ **Backend otimizado**: `getAllPermissions()` vs múltiplos `can()`
- ✅ **Frontend**: Apenas permissions que user TEM (dynamic array)
- ✅ **Escalável**: Suporta 100+ permissions sem impacto
- ✅ **Exemplos completos**: `PermissionsUsageExample.tsx`
- ✅ **Migration guide**: Documentação completa old → new
- ✅ **79 testes passando**: Sem breaking changes no backend

**Breaking Changes (Frontend Only)**:
- `auth.permissions` agora é array de strings (não nested object)
- `auth.role` agora é object separado (não dentro de permissions)
- `usePermissions()` retorna methods (has, hasAny) em vez de object
- `<Can>` aceita Permission type (não keyof Permissions)

### v1.0.0 (2025-11-20)

**Initial Release**:
- ✅ 19 permissions organizadas em 4 categorias
- ✅ 3 roles MVP (owner, admin, member)
- ✅ Comando idempotente `permissions:sync`
- ✅ Integração completa com multi-tenancy
- ✅ Metadata rica (description, category, display_name)
- ✅ Nomenclatura padronizada `tenant.resource:action`
- ✅ 79 testes passando
- ✅ Documentação completa
