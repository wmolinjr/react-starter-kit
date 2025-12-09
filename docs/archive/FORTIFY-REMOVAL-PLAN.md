# Fortify Removal and Custom Tenant Authentication Implementation Plan

> **STATUS: ✅ CONCLUÍDO** (Dezembro 2024)
>
> Este plano foi completamente implementado. Laravel Fortify foi removido como provedor de rotas
> e agora é utilizado apenas como biblioteca para funcionalidade 2FA.

## Summary of Changes

### What Was Done

1. **Created 8 Custom Tenant Auth Controllers** in `app/Http/Controllers/Tenant/Auth/`:
   - `LoginController.php` - Login form and authentication
   - `LogoutController.php` - Logout handling
   - `RegisterController.php` - Registration form and user creation
   - `ForgotPasswordController.php` - Password reset request
   - `ResetPasswordController.php` - Password reset form and processing
   - `VerifyEmailController.php` - Email verification handling
   - `TwoFactorChallengeController.php` - 2FA challenge during login
   - `ConfirmPasswordController.php` - Password confirmation for sensitive actions

2. **Added Authentication Routes** in `routes/tenant.php`:
   - All routes prefixed with `tenant.auth.*`
   - Guest routes: login, register, password reset, 2FA challenge
   - Authenticated routes: logout, password confirmation, email verification

3. **Created Custom Password Confirmation Middleware**:
   - `RequireTenantPassword` middleware (`app/Http/Middleware/Tenant/RequireTenantPassword.php`)
   - Registered as `tenant.password.confirm` alias

4. **Removed Fortify Components**:
   - Removed `FortifyServiceProvider.php`
   - Removed `TenancyFortifyServiceProvider.php`
   - Removed `RedirectFortifyOnCentral.php` middleware
   - Removed `FortifyRouteBootstrapper` from tenancy config
   - Removed `app/Actions/Fortify/Tenant/` directory

5. **Updated Fortify Configuration**:
   - `config/fortify.php` now only configures 2FA features
   - Added `Fortify::ignoreRoutes()` in AppServiceProvider to disable Fortify routes
   - Features enabled: registration, resetPasswords, emailVerification, twoFactorAuthentication

6. **Updated Tests**:
   - All auth tests updated to use `tenant.auth.*` routes
   - Tests use `$this->tenantUrl()` helper for cross-domain testing

7. **Regenerated Wayfinder Routes**:
   - TypeScript route helpers updated for new route names

### Current Architecture

```
app/Http/Controllers/
├── Central/Auth/           # Central admin authentication (unchanged)
│   ├── AdminLoginController.php
│   ├── AdminLogoutController.php
│   ├── ForgotPasswordController.php
│   ├── ResetPasswordController.php
│   ├── TwoFactorChallengeController.php
│   └── ConfirmPasswordController.php
└── Tenant/Auth/            # NEW: Tenant user authentication
    ├── LoginController.php
    ├── LogoutController.php
    ├── RegisterController.php
    ├── ForgotPasswordController.php
    ├── ResetPasswordController.php
    ├── TwoFactorChallengeController.php
    ├── ConfirmPasswordController.php
    └── VerifyEmailController.php
```

### Fortify Usage (Library Only)

Fortify is kept as a dependency ONLY for 2FA functionality:
- `TwoFactorAuthenticatable` trait on User models
- `TwoFactorAuthenticationProvider` for TOTP verification
- `Features::twoFactorAuthentication()` for feature flag checks
- Actions: `EnableTwoFactorAuthentication`, `DisableTwoFactorAuthentication`, etc.

**Routes are completely disabled** via `Fortify::ignoreRoutes()`.

---

## Original Plan (Historical Reference)

The sections below document the original implementation plan for reference.

---

## Overview

Este documento detalha o plano para remover o Laravel Fortify do projeto e substituir por controllers custom para autenticação do Tenant, seguindo a mesma arquitetura já implementada para Central Admin.

**Estado Anterior:**
- **Central Admin**: Usa controllers custom (`AdminLoginController`, `ForgotPasswordController`, `ResetPasswordController`, `VerifyEmailController`, `TwoFactorChallengeController`, `ConfirmPasswordController`)
- **Tenant**: Usava Laravel Fortify v1.30 com guard `tenant`

**Estado Atual:**
- **Central Admin**: Controllers custom (sem mudanças)
- **Tenant**: Controllers custom seguindo o mesmo padrão do Central Admin

## Motivação

1. **Consistência**: Mesma arquitetura para autenticação Central e Tenant
2. **Simplicidade**: Remove dependência do Fortify e sua complexidade de middlewares
3. **Controle**: Controle total sobre fluxos de autenticação sem "magia" do Fortify
4. **Integração Tenancy**: Integração mais limpa com Stancl/Tenancy v4 (sem necessidade de `FortifyRouteBootstrapper`)
5. **Manutenção**: Um único padrão para toda autenticação

---

## Parte 1: Controllers a Criar

Criar os seguintes controllers em `app/Http/Controllers/Tenant/Auth/`:

| Controller | Descrição | Referência |
|------------|-----------|------------|
| `LoginController.php` | View de login e autenticação | `Central\Auth\AdminLoginController` |
| `LogoutController.php` | Logout handling | `Central\Auth\AdminLogoutController` |
| `RegisterController.php` | View de registro e criação de usuário | `CreateNewUser` action |
| `ForgotPasswordController.php` | Solicitação de link de reset | `Central\Auth\ForgotPasswordController` |
| `ResetPasswordController.php` | Form e processamento de reset | `Central\Auth\ResetPasswordController` |
| `VerifyEmailController.php` | Views e handling de verificação | `Central\Auth\VerifyEmailController` |
| `TwoFactorChallengeController.php` | Desafio 2FA durante login | `Central\Auth\TwoFactorChallengeController` |
| `ConfirmPasswordController.php` | Confirmação de senha para ações sensíveis | `Central\Auth\ConfirmPasswordController` |

### Detalhes de Implementação

#### 1. LoginController

```php
namespace App\Http\Controllers\Tenant\Auth;

// Diferenças do AdminLoginController:
// - Usa guard 'tenant' ao invés de 'central'
// - Usa Tenant\User model ao invés de Central\User
// - Redireciona para 'tenant.admin.dashboard' ao invés de 'central.admin.dashboard'
// - Session key: 'tenant.login.id' ao invés de 'central_admin.login.id'
// - Renderiza página Inertia 'tenant/auth/login'
```

#### 2. LogoutController

```php
// Logout simples com guard 'tenant'
// Redireciona para página de login do tenant
```

#### 3. RegisterController

```php
// Cria novos usuários tenant
// Usa CreateNewUser action existente ou lógica inline
// Auto-autentica após registro
// Redireciona para 'tenant.admin.dashboard'
```

#### 4. ForgotPasswordController

```php
// Usa Password::broker('tenant_users')
// Renderiza página Inertia 'tenant/auth/forgot-password'
```

#### 5. ResetPasswordController

```php
// Usa Password::broker('tenant_users')
// Redireciona para página de login do tenant após sucesso
// Renderiza página Inertia 'tenant/auth/reset-password'
```

#### 6. VerifyEmailController

```php
// Usa auth()->guard('tenant')
// Redireciona para 'tenant.admin.dashboard'
// Renderiza página Inertia 'tenant/auth/verify-email'
```

#### 7. TwoFactorChallengeController

```php
// Session key: 'tenant.login.id'
// Usa guard 'tenant' para login
// Redireciona para 'tenant.admin.dashboard'
// Usa TwoFactorAuthenticationProvider do Fortify (ainda necessário)
```

#### 8. ConfirmPasswordController

```php
// Usa auth()->guard('tenant')
// Session key: 'auth.password_confirmed_at'
// Renderiza página Inertia 'tenant/auth/confirm-password'
```

---

## Parte 2: Rotas a Adicionar

Adicionar as seguintes rotas em `routes/tenant.php`:

```php
use App\Http\Controllers\Tenant\Auth\LoginController;
use App\Http\Controllers\Tenant\Auth\LogoutController;
use App\Http\Controllers\Tenant\Auth\RegisterController;
use App\Http\Controllers\Tenant\Auth\ForgotPasswordController;
use App\Http\Controllers\Tenant\Auth\ResetPasswordController;
use App\Http\Controllers\Tenant\Auth\VerifyEmailController;
use App\Http\Controllers\Tenant\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Tenant\Auth\ConfirmPasswordController;

/*
|----------------------------------------------------------------------
| Tenant Authentication Routes (tenant.auth.*)
|----------------------------------------------------------------------
*/

// Guest routes (login, register, password reset, 2FA challenge)
Route::middleware('guest:tenant')->name('auth.')->group(function () {
    // Login
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // Registration (se habilitado)
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

    // Forgot Password
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

    // Reset Password
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');

    // Two-Factor Challenge (durante login)
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.challenge.store');
});

// Authenticated routes (logout, password confirmation, email verification)
Route::middleware('auth:tenant')->name('auth.')->group(function () {
    Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');

    // Password confirmation
    Route::get('/confirm-password', [ConfirmPasswordController::class, 'show'])->name('confirm-password');
    Route::post('/confirm-password', [ConfirmPasswordController::class, 'store'])->name('confirm-password.store');

    // Email Verification
    Route::get('/email/verify', [VerifyEmailController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [VerifyEmailController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});
```

### Mudanças de Nomes de Rotas

| Rota Fortify Atual | Nova Rota Custom |
|--------------------|------------------|
| `login` | `tenant.auth.login` |
| `register` | `tenant.auth.register` |
| `password.request` | `tenant.auth.password.request` |
| `password.email` | `tenant.auth.password.email` |
| `password.reset` | `tenant.auth.password.reset` |
| `password.update` | `tenant.auth.password.update` |
| `two-factor.login` | `tenant.auth.two-factor.challenge` |
| `verification.notice` | `tenant.auth.verification.notice` |
| `verification.verify` | `tenant.auth.verification.verify` |
| `verification.send` | `tenant.auth.verification.send` |
| `logout` | `tenant.auth.logout` |
| `password.confirm` | `tenant.auth.confirm-password` |

---

## Parte 3: Arquivos a Remover ou Modificar

### Arquivos a Remover

1. **`app/Providers/FortifyServiceProvider.php`** - Remover completamente
2. **`config/fortify.php`** - Remover (manter cópia em `docs/archive/`)
3. **`app/Actions/Fortify/Tenant/CreateNewUser.php`** - Mover lógica para RegisterController ou Service
4. **`app/Actions/Fortify/Tenant/ResetUserPassword.php`** - Inline no ResetPasswordController
5. **`app/Http/Middleware/Central/RedirectFortifyOnCentral.php`** - Remover (não mais necessário)

### Arquivos a Modificar

1. **`config/app.php`** - Remover FortifyServiceProvider dos providers
2. **`config/tenancy.php`** - Remover `FortifyRouteBootstrapper` do array de bootstrappers
3. **`bootstrap/app.php`** - Remover configurações de middleware do Fortify

### Nota Importante sobre Pacote Fortify

**NÃO remover `laravel/fortify` do composer.json** porque:
1. `TwoFactorAuthenticationProvider` ainda é usado para verificação 2FA
2. Trait `TwoFactorAuthenticatable` no User model depende dele
3. Actions `EnableTwoFactorAuthentication`, `ConfirmTwoFactorAuthentication`, `DisableTwoFactorAuthentication`, `GenerateNewRecoveryCodes` ainda são usadas pelos Settings do Central

O pacote Fortify pode permanecer como biblioteca apenas para funcionalidade 2FA, sem suas rotas e service provider.

---

## Parte 4: Atualizações Frontend

### Páginas React (Sem Mudanças Necessárias)

As páginas existentes podem permanecer como estão:
- `resources/js/pages/tenant/auth/login.tsx`
- `resources/js/pages/tenant/auth/register.tsx`
- `resources/js/pages/tenant/auth/forgot-password.tsx`
- `resources/js/pages/tenant/auth/reset-password.tsx`
- `resources/js/pages/tenant/auth/verify-email.tsx`
- `resources/js/pages/tenant/auth/two-factor-challenge.tsx`
- `resources/js/pages/tenant/auth/confirm-password.tsx`

### Atualização de Rotas Wayfinder

Após criar as novas rotas, regenerar rotas Wayfinder:

```bash
sail artisan wayfinder:generate --with-form
```

Os arquivos de rotas que serão atualizados/regenerados:
- `resources/js/routes/tenant/auth/` - Novas rotas do Tenant

### Imports de Rotas Frontend

Atualizar arquivos frontend que importam rotas para usar os novos nomes:
- Verificar imports de `@/routes/login`
- Verificar imports de `@/routes/register`
- Verificar imports de `@/routes/password`
- Verificar imports de `@/routes/verification`
- Verificar imports de `@/routes/two-factor/login`

---

## Parte 5: Testes a Criar/Atualizar

### Testes a Atualizar

1. **`tests/Feature/Auth/AuthenticationTest.php`**
   - Atualizar nomes de rotas (`login` -> `tenant.auth.login`)
   - Atualizar redirects esperados

2. **`tests/Feature/Auth/RegistrationTest.php`**
   - Atualizar nomes de rotas (`register` -> `tenant.auth.register`)

3. **`tests/Feature/Auth/PasswordResetTest.php`**
   - Atualizar nomes de rotas

4. **`tests/Feature/Auth/EmailVerificationTest.php`**
   - Atualizar nomes de rotas

5. **`tests/Feature/Auth/TwoFactorChallengeTest.php`**
   - Atualizar nomes de rotas e session keys

6. **`tests/Feature/Auth/PasswordConfirmationTest.php`**
   - Atualizar nomes de rotas

### Novos Testes a Criar

Criar em `tests/Feature/Tenant/Auth/`:
- `LoginTest.php`
- `RegisterTest.php`
- `ForgotPasswordTest.php`
- `ResetPasswordTest.php`
- `VerifyEmailTest.php`
- `TwoFactorChallengeTest.php`
- `ConfirmPasswordTest.php`

---

## Parte 6: Ordem de Execução

### Fase 1: Preparação (Baixo Risco)

1. ✅ Criar `docs/FORTIFY-REMOVAL-PLAN.md` (este documento)
2. Arquivar config atual: `cp config/fortify.php docs/archive/`
3. Criar feature branch: `git checkout -b feature/remove-fortify`

### Fase 2: Criar Novos Controllers

4. Criar diretório `app/Http/Controllers/Tenant/Auth/`
5. Criar `LoginController.php` (baseado em AdminLoginController)
6. Criar `LogoutController.php` (baseado em AdminLogoutController)
7. Criar `RegisterController.php` (novo, usando lógica CreateNewUser)
8. Criar `ForgotPasswordController.php` (baseado na versão Central)
9. Criar `ResetPasswordController.php` (baseado na versão Central)
10. Criar `VerifyEmailController.php` (baseado na versão Central)
11. Criar `TwoFactorChallengeController.php` (baseado na versão Central)
12. Criar `ConfirmPasswordController.php` (baseado na versão Central)

### Fase 3: Adicionar Rotas

13. Adicionar novas rotas de auth em `routes/tenant.php`
14. Testar rotas manualmente no browser

### Fase 4: Atualizar Testes

15. Atualizar todos os testes de auth em `tests/Feature/Auth/`
16. Rodar testes: `sail artisan test --filter Auth`

### Fase 5: Remover Fortify

17. Remover `FortifyServiceProvider` de `config/app.php`
18. Remover `FortifyRouteBootstrapper` de `config/tenancy.php`
19. Remover middlewares Fortify de `bootstrap/app.php`
20. Deletar `app/Providers/FortifyServiceProvider.php`
21. Deletar `app/Http/Middleware/Central/RedirectFortifyOnCentral.php`
22. Mover `config/fortify.php` para `docs/archive/fortify.php`

### Fase 6: Cleanup

23. Mover Actions do Fortify:
    - `app/Actions/Fortify/Tenant/CreateNewUser.php` -> `app/Services/Tenant/UserService.php` ou inline
    - `app/Actions/Fortify/Tenant/ResetUserPassword.php` -> inline no controller
    - Manter `app/Actions/Fortify/Shared/PasswordValidationRules.php` -> mover para `app/Rules/`
24. Regenerar rotas Wayfinder: `sail artisan wayfinder:generate --with-form`
25. Rodar suite completa de testes: `sail artisan test`
26. Rodar testes E2E Playwright: `sail npm run test:e2e`

### Fase 7: Documentação

27. Atualizar `CLAUDE.md`:
    - Remover referências ao Fortify
    - Atualizar seção de autenticação
28. Arquivar docs antigos se necessário

---

## Riscos e Mitigações

| Risco | Impacto | Mitigação |
|-------|---------|-----------|
| Mudança de nomes de rotas quebra frontend | Alto | Manter aliases de rotas antigas inicialmente |
| 2FA para de funcionar | Alto | Manter pacote Fortify para TwoFactorAuthenticationProvider |
| Testes falham | Médio | Atualizar testes antes de remover Fortify |
| Diferenças no handling de sessão | Médio | Seguir padrões exatos dos controllers Central |
| Rate limiting mal configurado | Baixo | Copiar definições de rate limiter do FortifyServiceProvider |
| Emails de reset de senha falham | Médio | Usar broker correto (`tenant_users`) |

### Plano de Rollback

Se problemas surgirem:
1. Reverter para feature branch anterior
2. Re-habilitar FortifyServiceProvider
3. Restaurar config/fortify.php

---

## Dependências

### Componentes Fortify Ainda Necessários

Após remoção, os seguintes componentes Fortify ainda são usados:
- `Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider`
- `Laravel\Fortify\Actions\EnableTwoFactorAuthentication`
- `Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication`
- `Laravel\Fortify\Actions\DisableTwoFactorAuthentication`
- `Laravel\Fortify\Actions\GenerateNewRecoveryCodes`
- `TwoFactorAuthenticatable` trait nos models User

### Consideração Futura

No futuro, considerar substituir 2FA do Fortify por:
- `pragmarx/google2fa-laravel` para geração/verificação TOTP
- Implementação custom para recovery codes

---

## Arquivos Críticos para Implementação

Lista dos arquivos mais importantes para referência durante implementação:

1. **`app/Http/Controllers/Central/Auth/AdminLoginController.php`** - Referência principal para padrão do LoginController
2. **`app/Http/Controllers/Central/Auth/TwoFactorChallengeController.php`** - Referência crítica para fluxo 2FA com handling de sessão
3. **`app/Providers/FortifyServiceProvider.php`** - Contém configuração de rate limiting a preservar
4. **`routes/tenant.php`** - Arquivo alvo para adicionar novas rotas
5. **`tests/Feature/Auth/AuthenticationTest.php`** - Arquivo de teste principal a atualizar

---

## Estimativa de Tempo

| Fase | Tarefas | Estimativa |
|------|---------|------------|
| Fase 1 | Preparação | 30 min |
| Fase 2 | Criar Controllers | 2-3 horas |
| Fase 3 | Adicionar Rotas | 30 min |
| Fase 4 | Atualizar Testes | 1-2 horas |
| Fase 5 | Remover Fortify | 30 min |
| Fase 6 | Cleanup | 1 hora |
| Fase 7 | Documentação | 30 min |

**Total Estimado**: 6-8 horas
