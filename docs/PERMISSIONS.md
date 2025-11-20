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

### ✅ Abordagem Recomendada: Middleware nas Rotas

A melhor prática é usar middleware `can:` nas rotas. Isso mantém os controllers limpos e centraliza a autorização.

```php
// routes/tenant.php

// ✅ RECOMENDADO: Middleware nas rotas
Route::prefix('team')->name('team.')->group(function () {
    Route::get('/', [TeamController::class, 'index'])
        ->middleware('can:tenant.team:view')
        ->name('index');

    Route::post('/invite', [TeamController::class, 'invite'])
        ->middleware('can:tenant.team:invite')
        ->name('invite');

    Route::patch('/{user}/role', [TeamController::class, 'updateRole'])
        ->middleware('can:tenant.team:manage-roles')
        ->name('update-role');
});

// Para Policies (com route model binding)
Route::prefix('projects')->name('projects.')->group(function () {
    Route::get('/{project}', [ProjectController::class, 'show'])
        ->middleware('can:view,project')  // usa ProjectPolicy::view()
        ->name('show');

    Route::patch('/{project}', [ProjectController::class, 'update'])
        ->middleware('can:update,project')  // usa ProjectPolicy::update()
        ->name('update');
});
```

### Em Controllers

Com middleware nas rotas, os controllers ficam limpos (sem `Gate::authorize()`):

```php
class TeamController extends Controller
{
    public function index()
    {
        // Autorização já feita pelo middleware
        $tenant = tenant();
        $members = $tenant->users()->get();

        return Inertia::render('tenant/team/index', [
            'members' => $members,
        ]);
    }

    public function invite(Request $request)
    {
        // Autorização já feita pelo middleware
        $validated = $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:admin,member',
        ]);

        // ...
    }
}
```

**Nota**: Só use `Gate::authorize()` ou `$user->can()` no controller se a lógica de autorização for dinâmica e não puder ser feita no middleware.

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

## Referências

- [Spatie Laravel Permission Docs](https://spatie.be/docs/laravel-permission)
- [Stancl Tenancy Docs](https://tenancyforlaravel.com/)
- [Laravel Authorization Docs](https://laravel.com/docs/authorization)

---

## Changelog

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
