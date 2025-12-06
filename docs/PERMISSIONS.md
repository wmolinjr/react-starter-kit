# Permissions & Roles System

## Visao Geral

Este projeto utiliza um sistema robusto de permissoes baseado em [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) integrado com multi-tenancy via [Stancl Tenancy](https://tenancyforlaravel.com/).

**Caracteristicas**:
- **Single Source of Truth**: Enums PHP como unica fonte de verdade
- **Tenant-isolated**: Permissions e roles sao isoladas por tenant (multi-database)
- **Type-safe**: PHP Enums + TypeScript types gerados automaticamente
- **Metadata rica**: Cada permission tem `description` bilingue (en/pt_BR) e `category`
- **Plan-gated**: Permissions enterprise sao habilitadas conforme o plano do tenant

---

## Indice

1. [Arquitetura: Single Source of Truth](#arquitetura-single-source-of-truth)
2. [Tenant Permissions (41 total)](#tenant-permissions-41-total)
3. [Central Admin Permissions (38 total)](#central-admin-permissions-38-total)
4. [Plans System Integration](#plans-system-integration)
5. [Como Adicionar Novas Permissions](#como-adicionar-novas-permissions)
6. [Comando de Sincronizacao](#comando-de-sincronizacao)
7. [Backend: Como Usar](#backend-como-usar)
8. [Frontend: Como Usar](#frontend-como-usar)
9. [Roles MVP](#roles-mvp)
10. [Testing](#testing)
11. [Troubleshooting](#troubleshooting)

---

## Arquitetura: Single Source of Truth

### Estrutura de Arquivos

```
app/Enums/
├── TenantPermission.php    # 41 permissoes tenant + descricoes
├── CentralPermission.php   # 38 permissoes central + descricoes
├── TenantRole.php          # Roles tenant + regras de filtragem
├── PlanFeature.php         # Features de planos
└── PlanLimit.php           # Limites de planos

app/Services/Central/
├── PlanPermissionResolver.php  # Resolve permissoes por plano
└── PlanFeatureResolver.php     # Resolve features Pennant
```

### Fluxo de Dados

```
┌─────────────────────────────┐
│   TenantPermission.php      │  Enum: cases + descriptions
│   CentralPermission.php     │  Single Source of Truth
└─────────────┬───────────────┘
              │
              ▼
┌─────────────────────────────┐
│   permissions:sync          │  Cria/atualiza no banco
│   (Artisan Command)         │  Gera TypeScript types
└─────────────┬───────────────┘
              │
              ▼
┌─────────────────────────────┐
│   Database                  │  permissions table
│   (Central/Tenant)          │  roles table
└─────────────────────────────┘
```

### Metodos Disponiveis nos Enums

```php
use App\Enums\TenantPermission;

// Obter todos os valores (nomes)
TenantPermission::values();
// ['projects:view', 'projects:create', ...]

// Obter array para seeding
TenantPermission::toSeederArray();
// [['name' => 'projects:view', 'category' => 'projects', 'description' => [...]], ...]

// Obter descricao de uma permissao
TenantPermission::descriptionFor('projects:view');
// ['en' => 'View all projects', 'pt_BR' => 'Visualizar todos os projetos']

// Extrair categoria
TenantPermission::extractCategory('projects:view');
// 'projects'

// Obter actions de uma categoria
TenantPermission::actionsFor('projects');
// ['view', 'create', 'edit', 'editOwn', 'delete', 'upload', 'download', 'archive']

// Todas as actions agrupadas por categoria
TenantPermission::actionsByCategory();
// ['projects' => [...], 'team' => [...], ...]

// Permissoes agrupadas por categoria
TenantPermission::byCategory();
// ['projects' => ['projects:view', ...], 'team' => [...], ...]
```

---

## Tenant Permissions (41 total)

**IMPORTANTE**: Este sistema esta integrado com o **Plans System**. Permissions enterprise sao habilitadas apenas para tenants com planos que incluem as features correspondentes.

### Projects (8 permissions)

| Permission | Descricao | Owner | Admin | Member |
|------------|-----------|-------|-------|--------|
| `projects:view` | View all projects | Yes | Yes | Yes |
| `projects:create` | Create new projects | Yes | Yes | Yes |
| `projects:edit` | Edit any project | Yes | Yes | No |
| `projects:editOwn` | Edit own projects only | Yes | No | Yes |
| `projects:delete` | Delete projects | Yes | No | No |
| `projects:upload` | Upload files | Yes | Yes | No |
| `projects:download` | Download files | Yes | Yes | Yes |
| `projects:archive` | Archive projects | Yes | Yes | No |

### Team (5 permissions)

| Permission | Descricao | Owner | Admin | Member |
|------------|-----------|-------|-------|--------|
| `team:view` | View team members | Yes | Yes | Yes |
| `team:invite` | Invite members | Yes | Yes | No |
| `team:remove` | Remove members | Yes | Yes | No |
| `team:manageRoles` | Manage roles | Yes | Yes | No |
| `team:activity` | View activity logs | Yes | Yes | No |

### Settings (3 permissions)

| Permission | Descricao | Owner | Admin | Member |
|------------|-----------|-------|-------|--------|
| `settings:view` | View settings | Yes | Yes | Yes |
| `settings:edit` | Edit settings | Yes | Yes | No |
| `settings:danger` | Danger zone access | Yes | No | No |

### Billing (3 permissions)

| Permission | Descricao | Owner | Admin | Member |
|------------|-----------|-------|-------|--------|
| `billing:view` | View billing | Yes | No | No |
| `billing:manage` | Manage subscriptions | Yes | No | No |
| `billing:invoices` | Download invoices | Yes | No | No |

### API Tokens (3 permissions)

| Permission | Descricao | Owner | Admin | Member |
|------------|-----------|-------|-------|--------|
| `apiTokens:view` | View API tokens | Yes | No | No |
| `apiTokens:create` | Create API tokens | Yes | No | No |
| `apiTokens:delete` | Delete API tokens | Yes | No | No |

### Custom Roles - Pro+ (4 permissions)

**Required Plan Feature**: `customRoles`

| Permission | Descricao |
|------------|-----------|
| `roles:view` | View custom roles |
| `roles:create` | Create custom roles |
| `roles:edit` | Edit custom roles |
| `roles:delete` | Delete custom roles |

### Advanced Reports - Enterprise (4 permissions)

**Required Plan Feature**: `advancedReports`

| Permission | Descricao |
|------------|-----------|
| `reports:view` | View reports |
| `reports:export` | Export reports |
| `reports:schedule` | Schedule reports |
| `reports:customize` | Customize reports |

### SSO - Enterprise (3 permissions)

**Required Plan Feature**: `sso`

| Permission | Descricao |
|------------|-----------|
| `sso:configure` | Configure SSO |
| `sso:manage` | Manage SSO providers |
| `sso:testConnection` | Test SSO connection |

### White Label - Enterprise (4 permissions)

**Required Plan Feature**: `whiteLabel`

| Permission | Descricao |
|------------|-----------|
| `branding:view` | View branding |
| `branding:edit` | Edit branding |
| `branding:preview` | Preview branding |
| `branding:publish` | Publish branding |

### Audit Log - Enterprise (2 permissions)

**Required Plan Feature**: `auditLog`

| Permission | Descricao |
|------------|-----------|
| `audit:view` | View audit logs |
| `audit:export` | Export audit logs |

### Multi-Language (2 permissions)

| Permission | Descricao |
|------------|-----------|
| `locales:view` | View language settings |
| `locales:manage` | Manage language settings |

---

## Central Admin Permissions (38 total)

Permissoes para o painel administrativo central (Super Admins e Central Admins).

### Tenant Management (5 permissions)

| Permission | Descricao |
|------------|-----------|
| `tenants:view` | View all tenants |
| `tenants:show` | View tenant details |
| `tenants:edit` | Edit tenant settings |
| `tenants:delete` | Delete tenants |
| `tenants:impersonate` | Impersonate tenant users |

### User Management (4 permissions)

| Permission | Descricao |
|------------|-----------|
| `users:view` | View all users |
| `users:show` | View user details |
| `users:edit` | Edit user details |
| `users:delete` | Delete users |

### Plan Catalog (5 permissions)

| Permission | Descricao |
|------------|-----------|
| `plans:view` | View all plans |
| `plans:create` | Create new plans |
| `plans:edit` | Edit plans |
| `plans:delete` | Delete plans |
| `plans:sync` | Sync plans with Stripe |

### Addon Catalog (5 permissions)

| Permission | Descricao |
|------------|-----------|
| `catalog:view` | View addon catalog |
| `catalog:create` | Create new addons |
| `catalog:edit` | Edit addons |
| `catalog:delete` | Delete addons |
| `catalog:sync` | Sync addons with Stripe |

### Tenant Addons (4 permissions)

| Permission | Descricao |
|------------|-----------|
| `addons:view` | View tenant addons |
| `addons:revenue` | View addon revenue reports |
| `addons:grant` | Grant addons to tenants |
| `addons:revoke` | Revoke addons from tenants |

### Feature Definitions (4 permissions)

| Permission | Descricao |
|------------|-----------|
| `features:view` | View feature definitions |
| `features:create` | Create feature definitions |
| `features:edit` | Edit feature definitions |
| `features:delete` | Delete feature definitions |

### Limit Definitions (4 permissions)

| Permission | Descricao |
|------------|-----------|
| `limits:view` | View limit definitions |
| `limits:create` | Create limit definitions |
| `limits:edit` | Edit limit definitions |
| `limits:delete` | Delete limit definitions |

### Central Roles (4 permissions)

| Permission | Descricao |
|------------|-----------|
| `roles:view` | View central roles |
| `roles:create` | Create central roles |
| `roles:edit` | Edit central roles |
| `roles:delete` | Delete central roles |

### System Settings (3 permissions)

| Permission | Descricao |
|------------|-----------|
| `system:view` | View system settings |
| `system:edit` | Edit system settings |
| `system:logs` | View system logs |

### Central Roles

| Role | Permissions | Descricao |
|------|-------------|-----------|
| **Super Admin** | ALL (bypass) | Bypassa todas as verificacoes via `Gate::before()` |
| **Central Admin** | 38 | Acesso ao painel administrativo central |

---

## Plans System Integration

### Como Funciona

```
┌─────────────────┐
│   PLAN MODEL    │  Features: {customRoles: true, sso: true}
│  (Database)     │  Permission Map: {customRoles: ["roles:*"]}
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
│ SPATIE PERMISSION│ $user->can('roles:view')
│  (User-level)   │  Checks if USER has the permission via role
└─────────────────┘
```

### Wildcard Expansion

```json
// Plan.permission_map
{
  "customRoles": ["roles:*"]
}
```

O `roles:*` expande para todas as actions usando `TenantPermission::actionsFor('roles')`:
- `roles:view`
- `roles:create`
- `roles:edit`
- `roles:delete`

---

## Como Adicionar Novas Permissions

### Passo 1: Editar o Enum

```php
// app/Enums/TenantPermission.php

// 1. Adicionar o case
case PROJECTS_SHARE = 'projects:share';

// 2. Adicionar descricao no metodo description()
public function description(): array
{
    return match ($this) {
        // ... outras permissoes ...

        self::PROJECTS_SHARE => [
            'en' => 'Share projects externally',
            'pt_BR' => 'Compartilhar projetos externamente'
        ],
    };
}
```

### Passo 2: Sincronizar

```bash
sail artisan permissions:sync
```

Isso ira:
1. Criar a nova permission no banco
2. Gerar TypeScript types automaticamente

### Passo 3: Adicionar a Roles (se necessario)

Se a permission deve ser incluida em roles default, atualizar:
- `app/Enums/TenantRole.php` - Para filtros de role (excludedPermissions, excludedCategories, etc)
- `app/Jobs/SeedTenantDatabase.php` - Se for uma nova categoria

### Passo 4: Usar

**Backend:**
```php
if ($user->can('projects:share')) {
    // ...
}
```

**Frontend:**
```tsx
const { has } = usePermissions();
{has('projects:share') && <ShareButton />}
```

### Para Permissions Central

O processo e o mesmo, mas edite `app/Enums/CentralPermission.php`.

---

## Comando de Sincronizacao

### Uso Basico

```bash
# Sincronizar permissions (insert or update)
sail artisan permissions:sync

# Limpar tudo e recriar (CUIDADO!)
sail artisan permissions:sync --fresh
```

### O que o comando faz

1. Cria/atualiza **41 tenant permissions** do enum `TenantPermission`
2. Cria/atualiza **38 central permissions** do enum `CentralPermission`
3. Cria roles globais: **Super Admin**, **Central Admin**
4. Limpa cache do Spatie Permission
5. Gera TypeScript types automaticamente
6. Exibe summary com contagem por categoria

---

## Backend: Como Usar

### Controller Middleware (Recomendado)

```php
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TeamController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:team:view', only: ['index']),
            new Middleware('permission:team:invite', only: ['invite']),
            new Middleware('permission:team:manageRoles', only: ['updateRole']),
        ];
    }
}
```

### Policy para Ownership

```php
class ProjectPolicy
{
    public function update(User $user, Project $project): bool
    {
        // Pode editar qualquer projeto
        if ($user->can('projects:edit')) {
            return true;
        }

        // Ou apenas os proprios
        return $user->can('projects:editOwn')
            && $project->user_id === $user->id;
    }
}
```

### Verificar no Codigo

```php
// Single check
if ($user->can('projects:delete')) { }

// Multiple (OR)
if ($user->canAny(['projects:edit', 'projects:editOwn'])) { }

// Multiple (AND)
if ($user->can('projects:view') && $user->can('projects:download')) { }
```

---

## Frontend: Como Usar

### Hook usePermissions() (Recomendado)

```tsx
import { usePermissions } from '@/hooks/use-permissions';

export function ProjectsPage() {
    const { has, hasAny, hasAll, isOwner } = usePermissions();

    return (
        <div>
            {/* Single permission */}
            {has('projects:create') && <CreateButton />}

            {/* OR logic */}
            {hasAny('projects:edit', 'projects:editOwn') && <EditButton />}

            {/* AND logic */}
            {hasAll('projects:view', 'projects:download') && <DownloadButton />}

            {/* Role (apenas UI) */}
            {isOwner && <Badge>Owner</Badge>}
        </div>
    );
}
```

### Componente Can

```tsx
import { Can } from '@/components/can';

<Can permission="projects:create">
    <CreateButton />
</Can>

<Can any={["projects:edit", "projects:editOwn"]}>
    <EditButton />
</Can>

<Can
    permission="billing:manage"
    fallback={<UpgradePrompt />}
>
    <BillingSettings />
</Can>
```

### Role vs Permission

```tsx
// ERRADO: Usar role para autorizacao
{isOwner && <DeleteButton />}

// CORRETO: Usar permission
{has('projects:delete') && <DeleteButton />}

// OK: Usar role para UI display
{isOwner && <Crown className="text-yellow-500" />}
```

---

## Roles MVP

### Owner (Proprietario)

**Permissions**: Todas as 26 base + enterprise conforme plano

- Acesso total incluindo billing e danger zone
- Enterprise permissions dependem do plano

### Admin (Administrador)

**Permissions**: 13

**Pode**:
- Editar qualquer projeto
- Convidar/remover membros
- Gerenciar roles
- Editar settings

**Nao pode**:
- Gerenciar billing
- Deletar projetos
- Danger zone
- API tokens

### Member (Membro)

**Permissions**: 6

**Pode**:
- Ver projetos
- Criar projetos
- Editar proprios projetos (editOwn)
- Download arquivos

**Nao pode**:
- Editar projetos de outros
- Deletar projetos
- Convidar pessoas
- Gerenciar billing/settings

---

## Testing

### Setup em TenantTestCase

```php
protected function seedTenantRolesForTests(): void
{
    // Usa enum como single source of truth
    $allPermissions = TenantPermission::values();

    foreach (TenantPermission::toSeederArray() as $permData) {
        Permission::firstOrCreate([
            'name' => $permData['name'],
            'guard_name' => 'web',
        ], [
            'category' => $permData['category'],
            'description' => $permData['description'],
        ]);
    }

    // Seed roles usando TenantRole enum
    foreach (TenantRole::systemRoles() as $tenantRole) {
        $role = Role::firstOrCreate(
            ['name' => $tenantRole->value, 'guard_name' => 'web'],
            [...]
        );
        $rolePermissions = $tenantRole->filterPermissions($allPermissions);
        $role->syncPermissions($rolePermissions);
    }
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
```

---

## Troubleshooting

### Permission nao funciona

```bash
# Verificar se existe
sail artisan tinker
>>> \App\Models\Universal\Permission::where('name', 'projects:view')->first();

# Limpar cache
sail artisan permission:cache-reset
```

### TypeScript types desatualizados

```bash
sail artisan permissions:sync
# ou
sail artisan permissions:generate-types
```

### Verificar enum

```bash
sail artisan tinker
>>> \App\Enums\TenantPermission::values();
>>> \App\Enums\TenantPermission::descriptionFor('projects:view');
```

---

## Referencias

### Codigo

| Arquivo | Descricao |
|---------|-----------|
| `app/Enums/TenantPermission.php` | 41 permissoes tenant (single source of truth) |
| `app/Enums/CentralPermission.php` | 38 permissoes central (single source of truth) |
| `app/Enums/TenantRole.php` | Roles tenant + filtros de permission (owner/admin/member) |
| `app/Services/Central/PlanPermissionResolver.php` | Resolve permissoes por plano |
| `app/Console/Commands/SyncPermissions.php` | Comando de sincronizacao |
| `app/Jobs/SeedTenantDatabase.php` | Seeding de tenant |
| `resources/js/hooks/use-permissions.ts` | Hook frontend |
| `resources/js/components/can.tsx` | Componente Can |

### Documentacao Relacionada

- **[docs/plans/PLANS-REFERENCE.md](plans/PLANS-REFERENCE.md)** - Sistema de planos
- **[docs/STANCL-FEATURES.md](STANCL-FEATURES.md)** - Multi-tenancy
- **[CLAUDE.md](../CLAUDE.md)** - Visao geral do projeto

### External

- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
- [Laravel Pennant](https://laravel.com/docs/pennant)
- [Stancl Tenancy](https://tenancyforlaravel.com/)

---

## Changelog

### v5.2.0 (2025-12-03)

**Permission Model: Colunas description/category removidas**

**Mudancas**:
- **Removidas colunas** `description` e `category` da tabela `permissions`
- **Accessors no modelo**: `getCategoryAttribute()` e `getDescriptionAttribute()` derivam do enum
- **Migrations atualizadas**: Tabela permissions agora tem apenas `id`, `name`, `guard_name`, `timestamps`

**Por que?**:
- Dados eram redundantes (enum já é a fonte de verdade)
- Menos acoplamento entre banco e código
- Single Source of Truth mais puro

**Arquivos atualizados**:
- `app/Models/Permission.php` - Accessors para category/description
- `database/migrations/*_create_permission_tables.php` - Removidas colunas
- `app/Jobs/SeedTenantDatabase.php` - Não passa mais category/description
- `app/Jobs/SyncTenantPermissions.php` - Não passa mais category/description
- `app/Console/Commands/SyncPermissions.php` - Não passa mais category/description
- `tests/TenantTestCase.php` - Não passa mais category/description

### v5.1.0 (2025-12-03)

**TenantRole Enum: Single Source of Truth para Roles**

**Mudancas**:
- **Removido `RolePermissionFilter.php`**: Logica movida para `TenantRole` enum
- **`TenantRole` enum**: Agora contem displayName(), description(), filterPermissions(), excludedPermissions(), excludedCategories()
- **Jobs atualizados**: `SeedTenantDatabase` e `SyncTenantPermissions` usam `TenantRole` diretamente
- **Testes atualizados**: `TenantTestCase` usa `TenantRole` diretamente

**Arquivos removidos**:
- `app/Services/RolePermissionFilter.php`

**Arquivos atualizados**:
- `app/Enums/TenantRole.php` - Adicionada logica de filtragem de permissions
- `app/Jobs/SeedTenantDatabase.php` - Usa TenantRole enum
- `app/Jobs/SyncTenantPermissions.php` - Usa TenantRole enum
- `tests/TenantTestCase.php` - Usa TenantRole enum

### v5.0.0 (2025-12-03)

**Single Source of Truth: Enums**

**Mudancas**:
- **Enums como unica fonte**: `TenantPermission` e `CentralPermission` agora contem permissoes + descricoes
- **Removido `PermissionRegistry`**: Classe intermediaria eliminada
- **Metodos nos enums**: `toSeederArray()`, `descriptionFor()`, `extractCategory()`, `actionsFor()`
- **41 tenant permissions** (was 37)
- **38 central permissions** (was 34)

**Como adicionar permissao agora**:
1. Editar enum (`TenantPermission.php` ou `CentralPermission.php`)
2. Rodar `sail artisan permissions:sync`

**Arquivos removidos**:
- `app/Permissions/PermissionRegistry.php`
- `app/Permissions/` (pasta inteira)

**Arquivos atualizados**:
- `app/Enums/TenantPermission.php` - Adicionados metodos e descricoes
- `app/Enums/CentralPermission.php` - Adicionados metodos e descricoes
- `app/Console/Commands/SyncPermissions.php` - Usa enums direto
- `app/Jobs/SyncTenantPermissions.php` - Usa enums direto
- `app/Jobs/SeedTenantDatabase.php` - Usa enums direto
- `app/Services/PlanPermissionResolver.php` - Usa enums direto
- `tests/TenantTestCase.php` - Usa enums direto

### v4.2.0 (2025-12-03)

**Multi-Database Tenancy: Removed RoleType**

### v4.0.0 (2025-11-28)

**Central Admin Permissions + Route Protection**

### v3.0.0 (2025-11-21)

**Plans System Integration + Enterprise Permissions**
