# Plano de Refatoração: Renomear Guards de Autenticação

## Resumo das Mudanças

| Guard Atual | Novo Nome | Uso |
|-------------|-----------|-----|
| `admin` | `central` | Autentica `Central\User` no banco central |
| `web` | `tenant` | Autentica `Tenant\User` no banco do tenant |

**Benefícios:**
- Nomenclatura semântica (guard reflete o contexto de uso)
- Consistência com a arquitetura multi-tenant
- Clareza no código (`auth:central` vs `auth:admin`)

---

## Fase 1: Configurações Principais

### 1.1 config/auth.php

**Mudanças:**
- Default guard: `'guard' => 'tenant'`
- Renomear guards: `web` → `tenant`, `admin` → `central`

```php
// ANTES
'defaults' => [
    'guard' => env('AUTH_GUARD', 'web'),
],
'guards' => [
    'web' => [...],
    'admin' => [...],
],

// DEPOIS
'defaults' => [
    'guard' => env('AUTH_GUARD', 'tenant'),
],
'guards' => [
    'tenant' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'central' => [
        'driver' => 'session',
        'provider' => 'admins',
    ],
],
```

### 1.2 config/fortify.php

```php
// ANTES
'guard' => 'web',

// DEPOIS
'guard' => 'tenant',
```

### 1.3 config/sanctum.php

```php
// ANTES
'guard' => ['web'],

// DEPOIS
'guard' => ['tenant'],
```

---

## Fase 2: Bootstrap e Middleware

### 2.1 bootstrap/app.php

```php
// ANTES
if (auth('admin')->check()) {
    return route('central.admin.dashboard');
}

// DEPOIS
if (auth('central')->check()) {
    return route('central.admin.dashboard');
}
```

---

## Fase 3: Rotas

### 3.1 routes/central-admin.php

```php
// ANTES
Route::middleware('guest:admin')->group(...)
Route::middleware('auth:admin')->group(...)

// DEPOIS
Route::middleware('guest:central')->group(...)
Route::middleware('auth:central')->group(...)
```

### 3.2 routes/central.php

```php
// ANTES
['auth:admin']

// DEPOIS
['auth:central']
```

### 3.3 routes/tenant.php

```php
// ANTES
Auth::guard($impersonationToken->auth_guard ?? 'web')->login($user);

// DEPOIS
Auth::guard($impersonationToken->auth_guard ?? 'tenant')->login($user);
```

---

## Fase 4: Controllers

### 4.1 AdminLoginController.php

```php
// ANTES
Auth::guard('admin')->login($admin, $request->boolean('remember'));

// DEPOIS
Auth::guard('central')->login($admin, $request->boolean('remember'));
```

### 4.2 AdminLogoutController.php

```php
// ANTES
Auth::guard('admin')->logout();

// DEPOIS
Auth::guard('central')->logout();
```

### 4.3 DashboardController.php (Central)

```php
// ANTES
Auth::guard('admin')->check()
Auth::guard('web')->check()

// DEPOIS
Auth::guard('central')->check()
Auth::guard('tenant')->check()
```

---

## Fase 5: Services

### 5.1 ImpersonationService.php

```php
// ANTES
auth('admin')->user()

// DEPOIS
auth('central')->user()
```

### 5.2 RoleService.php (Central e Tenant)

```php
// ANTES
'guard_name' => 'web'

// DEPOIS
'guard_name' => 'tenant'
```

---

## Fase 6: Jobs

### 6.1 SeedTenantDatabase.php

```php
// ANTES
'guard_name' => 'web'

// DEPOIS
'guard_name' => 'tenant'
```

### 6.2 SyncTenantPermissions.php

```php
// ANTES
'guard_name' => 'web'

// DEPOIS
'guard_name' => 'tenant'
```

---

## Fase 7: Console Commands

### 7.1 SyncPermissions.php

```php
// ANTES
'guard_name' => 'web'

// DEPOIS
'guard_name' => 'tenant'
```

---

## Fase 8: Testes

### 8.1 TenantTestCase.php

```php
// ANTES
$this->actingAs($user, 'web')
'guard_name' => 'web'

// DEPOIS
$this->actingAs($user, 'tenant')
'guard_name' => 'tenant'
```

### 8.2 Testes de Feature (substituições globais)

| Antes | Depois |
|-------|--------|
| `actingAs($admin, 'admin')` | `actingAs($admin, 'central')` |
| `assertAuthenticatedAs($admin, 'admin')` | `assertAuthenticatedAs($admin, 'central')` |
| `assertGuest('admin')` | `assertGuest('central')` |
| `assertGuest('web')` | `assertGuest('tenant')` |
| `'guard_name' => 'web'` | `'guard_name' => 'tenant'` |

**Arquivos afetados:**
- `AdminAuthenticationTest.php`
- `AdminImpersonationTest.php`
- `BundleCatalogControllerTest.php`
- `SyncTenantPermissionsTest.php`
- `TenantRoleControllerTest.php`
- `TeamTest.php`

---

## Fase 9: Documentação

### 9.1 CLAUDE.md

Atualizar tabela de guards:
```markdown
// ANTES
- `admin` guard: Central administrators
- `web` guard: Tenant users

// DEPOIS
- `central` guard: Central administrators
- `tenant` guard: Tenant users
```

### 9.2 docs/PERMISSIONS.md

```php
// ANTES
'guard_name' => 'web'

// DEPOIS
'guard_name' => 'tenant'
```

---

## Fase 10: Finalização

### 10.1 Comandos de Verificação

```bash
# Buscar referências restantes ao guard 'admin'
grep -r "auth:admin\|guest:admin\|guard('admin')\|guard(\"admin\")" --include="*.php" --exclude-dir=vendor

# Buscar referências restantes ao guard 'web' (exceto contextos válidos)
grep -r "auth:web\|guest:web\|guard('web')\|guard(\"web\")" --include="*.php" --exclude-dir=vendor

# Buscar guard_name antigos
grep -r "guard_name.*'web'\|guard_name.*'admin'" --include="*.php" --exclude-dir=vendor
```

### 10.2 Reset do Banco

```bash
sail artisan migrate:fresh --seed
sail artisan tenants:migrate
sail artisan tenants:seed
```

### 10.3 Rodar Testes

```bash
sail artisan test
sail npm run test:e2e
```

### 10.4 Verificação Manual

- [ ] Login como admin central (`localhost/admin/login`)
- [ ] Login como tenant user (`tenant1.localhost/login`)
- [ ] Impersonation de tenant
- [ ] Logout de ambos contextos
- [ ] Redirect de guest para login correto

---

## Checklist de Implementação

### Configurações
- [ ] `config/auth.php`
- [ ] `config/fortify.php`
- [ ] `config/sanctum.php`

### Bootstrap/Rotas
- [ ] `bootstrap/app.php`
- [ ] `routes/central-admin.php`
- [ ] `routes/central.php`
- [ ] `routes/tenant.php`

### Controllers
- [ ] `AdminLoginController.php`
- [ ] `AdminLogoutController.php`
- [ ] `DashboardController.php` (Central)

### Services
- [ ] `ImpersonationService.php`
- [ ] `RoleService.php` (Central)
- [ ] `RoleService.php` (Tenant)

### Jobs
- [ ] `SeedTenantDatabase.php`
- [ ] `SyncTenantPermissions.php`

### Commands
- [ ] `SyncPermissions.php`

### Testes
- [ ] `TenantTestCase.php`
- [ ] `AdminAuthenticationTest.php`
- [ ] `AdminImpersonationTest.php`
- [ ] `BundleCatalogControllerTest.php`
- [ ] `SyncTenantPermissionsTest.php`
- [ ] `TenantRoleControllerTest.php`
- [ ] `TeamTest.php`

### Documentação
- [ ] `CLAUDE.md`
- [ ] `docs/PERMISSIONS.md`

### Finalização
- [ ] Verificar grep sem resultados
- [ ] `migrate:fresh --seed`
- [ ] Testes passando
- [ ] Verificação manual OK
