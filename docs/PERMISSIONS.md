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

### Em Controllers

```php
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index()
    {
        // Opção 1: Via Gate
        Gate::authorize('tenant.projects:view');

        // Opção 2: Via Policy (recomendado)
        Gate::authorize('viewAny', Project::class);

        // ...
    }

    public function store(Request $request)
    {
        // Check manual
        if (!$request->user()->can('tenant.projects:create')) {
            abort(403, 'Você não tem permissão para criar projetos.');
        }

        // ...
    }
}
```

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

### Em Blade/Inertia

```php
// Em controllers (props para Inertia)
return Inertia::render('ProjectIndex', [
    'can' => [
        'create' => $user->can('tenant.projects:create'),
        'edit' => $user->can('tenant.projects:edit'),
        'delete' => $user->can('tenant.projects:delete'),
    ],
]);
```

```tsx
// Em React (Inertia)
import { usePage } from '@inertiajs/react';

const { can } = usePage().props;

{can.create && (
    <Button onClick={handleCreate}>Create Project</Button>
)}
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

### Owner Bypass

O sistema tem um bypass especial para owners, mas **APENAS para gates legacy**:

```php
// AuthServiceProvider.php
Gate::before(function (User $user, string $ability) {
    // Não aplicar bypass para permissions com prefixo tenant.
    if (str_starts_with($ability, 'tenant.')) {
        return null; // Deixar verificação de permission continuar
    }

    // Bypass para gates legacy
    if ($ability !== 'manage-billing' && tenancy()->initialized) {
        if ($user->isOwner()) {
            return true;
        }
    }
});
```

**Importante**: Permissions novas (com prefixo `tenant.`) **NÃO** são bypassadas. Owners precisam ter as permissions explicitamente atribuídas via role.

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
