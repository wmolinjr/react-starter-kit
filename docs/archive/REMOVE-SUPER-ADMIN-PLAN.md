# Plano de Implementação: Remover is_super_admin e Migrar para Roles

> **Status:** ✅ CONCLUÍDO
> **Data:** 2025-12-06
> **Implementado:** 2025-12-06
> **Verificado:** 2025-12-06

## Resumo Executivo

Este documento detalha o plano para remover a funcionalidade de bypass `is_super_admin` do modelo `Central\User`, substituindo-a por um sistema baseado em roles com Spatie Permission. O objetivo é garantir que TODO acesso seja controlado por roles/permissions explícitas, eliminando o bypass implícito.

---

## Verificação de Implementação

### ✅ Backend - Models

**Arquivo**: `app/Models/Central/User.php`

| Item | Status | Detalhes |
|------|--------|----------|
| HasRoles trait | ✅ | `use Spatie\Permission\Traits\HasRoles;` |
| Guard name | ✅ | `protected string $guard_name = 'central';` |
| isSuperAdmin() | ✅ | `return $this->hasRole('super-admin');` |
| canAccessTenant() | ✅ | `return $this->can('tenants:impersonate');` |
| scopeSuperAdmins() | ✅ | `whereHas('roles', fn($q) => $q->where('name', 'super-admin'))` |
| is_super_admin removido | ✅ | Não existe em `$fillable` ou `$casts` |
| getRoleName() | ✅ | Novo método para obter nome do role |
| getRoleDisplayName() | ✅ | Novo método para obter display name traduzido |

### ✅ Backend - Providers

**Arquivo**: `app/Providers/AppServiceProvider.php`

| Item | Status | Detalhes |
|------|--------|----------|
| Bypass removido | ✅ | Gate::before() retorna `null` para Central\User |
| Comentários atualizados | ✅ | Documentação explica que não há bypass |

**Código atual (linhas 70-75)**:
```php
Gate::before(function ($user, $ability) {
    // Central\User: Let Spatie handle via HasRoles trait (guard: central)
    // No bypass - permissions are assigned via roles (super-admin, central-admin, support-admin)
    if ($user instanceof \App\Models\Central\User) {
        return null; // Let normal permission check proceed
    }
    // ...
});
```

### ✅ Backend - Middleware

**Arquivo**: `app/Http/Middleware/Shared/HandleInertiaRequests.php`

| Item | Status | Detalhes |
|------|--------|----------|
| Permissions reais | ✅ | `$user->getAllPermissions()->pluck('name')->toArray()` |
| Role name | ✅ | `$user->getRoleName()` |
| isSuperAdmin | ✅ | `$user->isSuperAdmin()` |

**Código atual (linhas 103-111)**:
```php
if ($user instanceof \App\Models\Central\User) {
    return [
        'user' => $user->toArray(),
        'tenant' => null,
        'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        'role' => $user->getRoleName(),
        'isSuperAdmin' => $user->isSuperAdmin(),
    ];
}
```

### ✅ Backend - Commands

**Arquivo**: `app/Console/Commands/SyncPermissions.php`

| Item | Status | Detalhes |
|------|--------|----------|
| Guard central | ✅ | Todas as roles centrais usam `guard_name: 'central'` |
| super-admin | ✅ | Tem todas as permissions centrais |
| central-admin | ✅ | Tem todas as permissions centrais |
| support-admin | ✅ | Tem permissions limitadas (view, impersonate) |

**Roles centrais definidas**:
```php
protected array $centralRoles = [
    'super-admin' => ['all_permissions' => true],
    'central-admin' => ['all_permissions' => true],
    'support-admin' => ['permissions' => ['tenants:view', 'tenants:show', 'tenants:impersonate', ...]],
];
```

### ✅ Backend - Seeders

**Arquivo**: `database/seeders/AdminSeeder.php`

| Item | Status | Detalhes |
|------|--------|----------|
| is_super_admin removido | ✅ | Não usa mais o campo boolean |
| assignRole() | ✅ | `$superAdmin->assignRole('super-admin')` |
| Support admin | ✅ | `$supportAdmin->assignRole('support-admin')` |

### ✅ Backend - Factories

**Arquivo**: `database/factories/AdminFactory.php`

| Item | Status | Detalhes |
|------|--------|----------|
| is_super_admin removido | ✅ | Não existe em `definition()` |
| superAdmin() state | ✅ | Usa `afterCreating` para atribuir role |
| centralAdmin() state | ✅ | Novo state adicionado |
| supportAdmin() state | ✅ | Novo state adicionado |
| ensureRoleExists() | ✅ | Helper para garantir role existe em testes |

### ✅ Backend - Migrations

**Arquivo**: `database/migrations/2025_12_05_000001_create_admins_table.php`

| Item | Status | Detalhes |
|------|--------|----------|
| is_super_admin removido | ✅ | Coluna não existe na migration |
| Índice removido | ✅ | Não há índice para is_super_admin |

**Schema atual**:
```php
Schema::create('admins', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->string('locale', 10)->default('pt_BR');
    $table->text('two_factor_secret')->nullable();
    $table->text('two_factor_recovery_codes')->nullable();
    $table->timestamp('two_factor_confirmed_at')->nullable();
    $table->rememberToken();
    $table->timestamps();
});
```

### ✅ Tests

**Arquivo**: `tests/Feature/AdminAuthenticationTest.php`

| Item | Status | Detalhes |
|------|--------|----------|
| Factory states | ✅ | Usa `Admin::factory()->superAdmin()->create()` |
| isSuperAdmin() test | ✅ | Testa retorno baseado em role |
| scopeSuperAdmins() test | ✅ | Testa scope baseado em roles |

**Arquivo**: `tests/Feature/AdminImpersonationTest.php`

| Item | Status | Detalhes |
|------|--------|----------|
| Factory states | ✅ | Usa `Admin::factory()->superAdmin()->create()` |
| Regular admin | ✅ | `Admin::factory()->create()` sem role |
| canAccessTenant() | ✅ | Testa método baseado em permission |

**Arquivo**: `tests/Feature/BundleCatalogControllerTest.php`

| Item | Status | Detalhes |
|------|--------|----------|
| Factory states | ✅ | Usa `Admin::factory()->superAdmin()->create()` |
| Non-super admin test | ✅ | Testa admin sem role |

### ✅ Frontend

**Arquivo**: `resources/js/pages/central/admin/users/index.tsx`

| Item | Status | Detalhes |
|------|--------|----------|
| Role display | ✅ | Usa `user.role` e `user.role_display_name` |
| Badge variant | ✅ | Baseado em `user.role === 'super-admin'` |
| No is_super_admin | ✅ | Interface usa `role: string \| null` |

**Arquivo**: `resources/js/pages/central/admin/users/show.tsx`

| Item | Status | Detalhes |
|------|--------|----------|
| Role display | ✅ | Usa `user.role_display_name` |
| isSuperAdmin | ✅ | Usa `user.isSuperAdmin` para descrição |
| Interface atualizada | ✅ | `isSuperAdmin: boolean` |

**Arquivo**: `resources/js/types/permissions.d.ts`

| Item | Status | Detalhes |
|------|--------|----------|
| Role interface | ✅ | Inclui `isSuperAdmin: boolean` |
| Types gerados | ✅ | Gerado automaticamente pelo comando |

### ✅ Translations

| Arquivo | Status | Chaves |
|---------|--------|--------|
| `lang/en.json` | ✅ | `admin.users.super_admin_description`, `admin.users.admin_description` |
| `lang/pt_BR.json` | ✅ | Mesmas chaves traduzidas |

---

## Checklist Final

### Fase 1: Preparação ✅
- [x] Adicionar `HasRoles` trait ao `Central\User`
- [x] Configurar guard `central` no modelo
- [x] Atualizar `SyncPermissions.php` para guard `central`
- [x] Definir roles centrais (super-admin, central-admin, support-admin)

### Fase 2: Migrar Lógica ✅
- [x] Alterar `Central\User::isSuperAdmin()` para usar Spatie
- [x] Alterar `Central\User::canAccessTenant()` para verificar permission
- [x] Alterar `Central\User::scopeSuperAdmins()` para usar roles
- [x] Alterar `AppServiceProvider::Gate::before()` - remover bypass
- [x] Alterar `HandleInertiaRequests` para permissions reais
- [x] Atualizar `AdminSeeder` para usar `assignRole()`
- [x] Atualizar `AdminFactory` states

### Fase 3: Remover Campo ✅
- [x] Remover coluna `is_super_admin` da migration
- [x] Remover campo do `$fillable` e `$casts`
- [x] Atualizar frontend para usar role do backend
- [x] Atualizar todos os testes

### Fase 4: Documentação ✅
- [x] Atualizar este documento com status final
- [x] Verificar todas as implementações

---

## Roles Centrais Implementadas

### Role: super-admin

| Atributo | Valor |
|----------|-------|
| **Name** | `super-admin` |
| **Guard** | `central` |
| **Display Name** | Super Administrator / Super Administrador |
| **Permissions** | TODAS as permissions de `CentralPermission` |
| **Comportamento** | Acesso total ao painel central |

### Role: central-admin

| Atributo | Valor |
|----------|-------|
| **Name** | `central-admin` |
| **Guard** | `central` |
| **Display Name** | Central Administrator / Administrador Central |
| **Permissions** | TODAS as permissions de `CentralPermission` |
| **Comportamento** | Acesso total ao painel central |

### Role: support-admin

| Atributo | Valor |
|----------|-------|
| **Name** | `support-admin` |
| **Guard** | `central` |
| **Display Name** | Support Administrator / Administrador de Suporte |
| **Permissions** | `tenants:view`, `tenants:show`, `tenants:impersonate`, `users:view`, `users:show`, `addons:view` |
| **Comportamento** | Suporte técnico - pode ver e impersonar, mas não editar/deletar |

---

## Comandos Úteis

```bash
# Sincronizar permissions e roles centrais
sail artisan permissions:sync

# Limpar cache de permissions
sail artisan permission:cache-reset

# Rodar testes de admin
sail artisan test --filter=Admin

# Ver roles centrais no banco
sail artisan tinker
>>> \App\Models\Shared\Role::where('guard_name', 'central')->get(['name', 'guard_name']);
```

---

## Arquivos Modificados (Resumo)

1. `app/Models/Central/User.php` - Modelo com HasRoles trait
2. `app/Providers/AppServiceProvider.php` - Gate::before sem bypass
3. `app/Http/Middleware/Shared/HandleInertiaRequests.php` - Permissions reais
4. `app/Console/Commands/SyncPermissions.php` - Roles centrais com guard correto
5. `database/seeders/AdminSeeder.php` - Usa assignRole()
6. `database/factories/AdminFactory.php` - States para roles
7. `database/migrations/2025_12_05_000001_create_admins_table.php` - Sem is_super_admin
8. `tests/Feature/AdminAuthenticationTest.php` - Testes atualizados
9. `tests/Feature/AdminImpersonationTest.php` - Testes atualizados
10. `tests/Feature/BundleCatalogControllerTest.php` - Testes atualizados
11. `resources/js/pages/central/admin/users/index.tsx` - UI atualizada
12. `resources/js/pages/central/admin/users/show.tsx` - UI atualizada
13. `resources/js/types/permissions.d.ts` - Types atualizados
14. `lang/en.json` - Traduções
15. `lang/pt_BR.json` - Traduções

---

## Conclusão

A migração de `is_super_admin` boolean para o sistema de roles do Spatie Permission foi concluída com sucesso. Todos os acessos agora são controlados por roles/permissions explícitas, eliminando o bypass implícito que existia anteriormente.

**Benefícios alcançados:**
- ✅ Sem bypass de autorização - todo acesso é explícito
- ✅ Flexibilidade para criar novos roles centrais
- ✅ Consistência com o sistema de permissions dos tenants
- ✅ Auditabilidade - fácil ver quem tem quais permissions
- ✅ Separação clara entre super-admin, central-admin e support-admin
