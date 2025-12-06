# Namespace Reorganization Plan

## Overview

Este plano reorganiza as pastas restantes da aplicação para seguir o padrão de namespace Central/Tenant/Shared já estabelecido em:

- `app/Models/` - Central/, Tenant/, Shared/
- `app/Services/` - Central/, Tenant/
- `app/Http/Controllers/` - Central/, Tenant/, Shared/
- `app/Http/Resources/` - Central/, Tenant/, Shared/
- `app/Http/Requests/` - Central/, Tenant/, Shared/
- `app/Observers/` - Central/, Tenant/

### Critérios de Classificação

| Contexto | Descrição | Exemplos |
|----------|-----------|----------|
| **Central** | Operações no banco central | tenants, plans, domains, billing, addons, Stripe webhooks |
| **Tenant** | Operações no contexto do tenant | team, projects, tenant users, settings |
| **Shared** | Funciona em ambos os contextos | authentication flows, profile settings, security headers |

---

## 1. Listeners

### Estado Atual
```
app/Listeners/
    UpdateTenantLimits.php
    SyncPermissionsOnSubscriptionChange.php
```

### Análise
| Arquivo | Contexto | Razão |
|---------|----------|-------|
| `UpdateTenantLimits.php` | Central | Escuta Stripe WebhookReceived, opera no model Tenant central |
| `SyncPermissionsOnSubscriptionChange.php` | Central | Escuta Stripe WebhookReceived, opera nos models Tenant/Plan centrais |

### Estrutura Proposta
```
app/Listeners/
├── Central/
│   ├── UpdateTenantLimits.php
│   └── SyncPermissionsOnSubscriptionChange.php
├── Tenant/
│   └── .gitkeep
└── Shared/
    └── .gitkeep
```

### Mudanças de Namespace
```php
// UpdateTenantLimits.php
// Antes: namespace App\Listeners;
// Depois: namespace App\Listeners\Central;

// SyncPermissionsOnSubscriptionChange.php
// Antes: namespace App\Listeners;
// Depois: namespace App\Listeners\Central;
```

### Arquivos a Atualizar
- `app/Providers/AppServiceProvider.php` - imports dos listeners

---

## 2. Jobs

### Estado Atual
```
app/Jobs/
    SyncTenantPermissions.php
    SeedTenantDatabase.php
```

### Análise
| Arquivo | Contexto | Razão |
|---------|----------|-------|
| `SyncTenantPermissions.php` | Central | Despachado do contexto central, usa `$tenant->run()` para trocar de banco |
| `SeedTenantDatabase.php` | Central | Despachado após criação do tenant, usa `$tenant->run()` para trocar de banco |

**Nota**: Embora esses jobs operem nos bancos dos tenants, são **despachados do contexto central** e recebem um model Tenant do banco central. Usam `$tenant->run()` internamente para trocar contexto.

### Estrutura Proposta
```
app/Jobs/
├── Central/
│   ├── SyncTenantPermissions.php
│   └── SeedTenantDatabase.php
├── Tenant/
│   └── .gitkeep
└── Shared/
    └── .gitkeep
```

### Mudanças de Namespace
```php
// SyncTenantPermissions.php
// Antes: namespace App\Jobs;
// Depois: namespace App\Jobs\Central;

// SeedTenantDatabase.php
// Antes: namespace App\Jobs;
// Depois: namespace App\Jobs\Central;
```

### Arquivos a Atualizar
- `app/Providers/TenancyServiceProvider.php` - import do SeedTenantDatabase
- `app/Listeners/Central/SyncPermissionsOnSubscriptionChange.php` - import do SyncTenantPermissions
- `app/Console/Commands/SyncTenantPermissionsCommand.php` - import do SyncTenantPermissions
- `tests/Feature/TenantRoleControllerTest.php`
- `tests/Feature/SyncTenantPermissionsTest.php`

---

## 3. Mail

### Estado Atual
```
app/Mail/
    TeamInvitation.php
```

### Análise
| Arquivo | Contexto | Razão |
|---------|----------|-------|
| `TeamInvitation.php` | Tenant | Usado pelo TeamService (Tenant) para convidar usuários |

### Estrutura Proposta
```
app/Mail/
├── Central/
│   └── .gitkeep
├── Tenant/
│   └── TeamInvitation.php
└── Shared/
    └── .gitkeep
```

### Mudanças de Namespace
```php
// TeamInvitation.php
// Antes: namespace App\Mail;
// Depois: namespace App\Mail\Tenant;
```

### Arquivos a Atualizar
- `app/Services/Tenant/TeamService.php` - import do TeamInvitation

---

## 4. Middleware

### Estado Atual
```
app/Http/Middleware/
    HandleInertiaRequests.php
    AllowAdminMode.php
    VerifyTenantAccess.php
    AddSecurityHeaders.php
    HandleAppearance.php
    CheckPlan.php
    SetLocale.php
```

### Análise
| Arquivo | Contexto | Razão |
|---------|----------|-------|
| `HandleInertiaRequests.php` | Shared | Funciona em ambos os contextos, compartilha props diferentes por contexto |
| `AllowAdminMode.php` | Tenant | Usado apenas em rotas tenant para impersonation |
| `VerifyTenantAccess.php` | Tenant | Usado apenas em rotas tenant para verificar acesso |
| `AddSecurityHeaders.php` | Shared | Headers de segurança globais |
| `HandleAppearance.php` | Shared | Aparência baseada em cookie, agnóstico de contexto |
| `CheckPlan.php` | Tenant | Usado apenas em rotas tenant para controle de acesso por plano |
| `SetLocale.php` | Shared | Funciona em ambos os contextos |

### Estrutura Proposta
```
app/Http/Middleware/
├── Central/
│   └── .gitkeep
├── Tenant/
│   ├── AllowAdminMode.php
│   ├── VerifyTenantAccess.php
│   └── CheckPlan.php
└── Shared/
    ├── HandleInertiaRequests.php
    ├── AddSecurityHeaders.php
    ├── HandleAppearance.php
    └── SetLocale.php
```

### Mudanças de Namespace
```php
// Tenant Middleware
// AllowAdminMode.php → namespace App\Http\Middleware\Tenant;
// VerifyTenantAccess.php → namespace App\Http\Middleware\Tenant;
// CheckPlan.php → namespace App\Http\Middleware\Tenant;

// Shared Middleware
// HandleInertiaRequests.php → namespace App\Http\Middleware\Shared;
// AddSecurityHeaders.php → namespace App\Http\Middleware\Shared;
// HandleAppearance.php → namespace App\Http\Middleware\Shared;
// SetLocale.php → namespace App\Http\Middleware\Shared;
```

### Arquivos a Atualizar
- `bootstrap/app.php` - imports e alias registrations
- `routes/tenant.php` - import do VerifyTenantAccess

---

## 5. Exceptions

### Estado Atual
```
app/Exceptions/
    TeamAuthorizationException.php
    PlanException.php
    AddonException.php
    AddonLimitExceededException.php
    TeamException.php
    SettingsException.php
    RoleException.php
```

### Análise
| Arquivo | Contexto | Razão |
|---------|----------|-------|
| `TeamAuthorizationException.php` | Tenant | Operações de team são tenant-only |
| `TeamException.php` | Tenant | Operações de team são tenant-only |
| `PlanException.php` | Central | Gerenciamento de planos é central-only |
| `AddonException.php` | Central | Gerenciamento de addons é central-only |
| `AddonLimitExceededException.php` | Central | Estende AddonException |
| `SettingsException.php` | Tenant | Operações de settings do tenant |
| `RoleException.php` | Shared | Usado em ambos Central e Tenant RoleService |

### Estrutura Proposta
```
app/Exceptions/
├── Central/
│   ├── PlanException.php
│   ├── AddonException.php
│   └── AddonLimitExceededException.php
├── Tenant/
│   ├── TeamAuthorizationException.php
│   ├── TeamException.php
│   └── SettingsException.php
└── Shared/
    └── RoleException.php
```

### Arquivos a Atualizar

**Central Exception imports:**
- `app/Services/Central/PlanService.php`
- `app/Services/Central/AddonService.php`
- `app/Services/Central/CheckoutService.php`
- `app/Http/Controllers/Central/Admin/PlanCatalogController.php`
- `tests/Feature/CheckoutServiceTest.php`
- `tests/Feature/AddonServiceTest.php`
- `tests/Feature/AddonBundleTest.php`

**Tenant Exception imports:**
- `app/Services/Tenant/TeamService.php`
- `app/Services/Tenant/TenantSettingsService.php`
- `app/Http/Controllers/Tenant/Admin/TeamController.php`
- `app/Http/Controllers/Tenant/Admin/TenantSettingsController.php`

**Shared Exception imports:**
- `app/Services/Tenant/RoleService.php`
- `app/Services/Central/RoleService.php`
- `app/Http/Controllers/Tenant/Admin/TenantRoleController.php`
- `app/Http/Controllers/Central/Admin/RoleManagementController.php`

---

## 6. Actions (Fortify)

### Estado Atual
```
app/Actions/
    Fortify/
        ResetUserPassword.php
        CreateNewUser.php
        PasswordValidationRules.php
```

### Análise
| Arquivo | Contexto | Razão |
|---------|----------|-------|
| `CreateNewUser.php` | Tenant | Cria usuários no banco do tenant (usa Tenant\User) |
| `ResetUserPassword.php` | Tenant | Reseta senhas de usuários tenant (usa Tenant\User) |
| `PasswordValidationRules.php` | Shared | Trait usado por ambas actions, agnóstico de contexto |

### Estrutura Proposta
```
app/Actions/
└── Fortify/
    ├── Tenant/
    │   ├── CreateNewUser.php
    │   └── ResetUserPassword.php
    └── Shared/
        └── PasswordValidationRules.php
```

### Mudanças de Namespace
```php
// CreateNewUser.php
// Antes: namespace App\Actions\Fortify;
// Depois: namespace App\Actions\Fortify\Tenant;
// use App\Actions\Fortify\Shared\PasswordValidationRules;

// ResetUserPassword.php
// Antes: namespace App\Actions\Fortify;
// Depois: namespace App\Actions\Fortify\Tenant;
// use App\Actions\Fortify\Shared\PasswordValidationRules;

// PasswordValidationRules.php
// Antes: namespace App\Actions\Fortify;
// Depois: namespace App\Actions\Fortify\Shared;
```

### Arquivos a Atualizar
- `app/Providers/FortifyServiceProvider.php` - imports das actions

---

## 7. Policies

### Estado Atual
```
app/Policies/
    ProjectPolicy.php
```

### Análise
| Arquivo | Contexto | Razão |
|---------|----------|-------|
| `ProjectPolicy.php` | Tenant | Políticas para Project (model tenant) |

### Estrutura Proposta
```
app/Policies/
├── Central/
│   └── .gitkeep
├── Tenant/
│   └── ProjectPolicy.php
└── Shared/
    └── .gitkeep
```

### Arquivos a Atualizar
- `app/Providers/AuthServiceProvider.php` (se existir registro de policies)

---

## 8. Pastas que Mantêm Estrutura Atual

### Console/Commands
**Recomendação**: Manter estrutura flat. Comandos operam via CLI e frequentemente abrangem contextos. A convenção de nomes (ex: `tenant:sync-permissions`, `stripe:sync`) já indica o contexto.

### Enums
**Recomendação**: Manter estrutura flat. Enums definem constantes e são definições agnósticas de contexto. A convenção de nomes (ex: `TenantPermission`, `CentralPermission`) já indica o contexto.

### Bootstrappers
**Recomendação**: Manter estrutura flat - são específicos do framework de tenancy.

### Support/Helpers/Traits
**Recomendação**: Manter estrutura flat - são utilitários globais.

### Providers
**Recomendação**: Manter estrutura flat - providers bootstrappam a aplicação.

---

## 9. Sequência de Implementação

### Fase 1: Criar Estrutura de Diretórios

Criar subdirectórios Central/, Tenant/, Shared/ em:
- `app/Listeners/`
- `app/Jobs/`
- `app/Mail/`
- `app/Http/Middleware/`
- `app/Exceptions/`
- `app/Actions/Fortify/`
- `app/Policies/`

Adicionar `.gitkeep` em diretórios vazios.

### Fase 2: Mover Arquivos (ordem de dependência)

**A ordem importa** - mover arquivos nesta sequência para minimizar imports quebrados:

1. **Exceptions** (sem dependências internas)
2. **Mail** (depende apenas de models)
3. **Jobs** (depende de models, services, enums)
4. **Listeners** (depende de jobs, models, services)
5. **Middleware** (depende de models, enums, services)
6. **Actions** (depende de models, traits)
7. **Policies** (depende de models, enums)

### Fase 3: Atualizar Referências

1. Atualizar `use` statements em todos os arquivos dependentes
2. Atualizar registros de middleware em `bootstrap/app.php`
3. Atualizar arquivos de rotas (`routes/tenant.php`, etc.)
4. Executar testes para verificar imports

### Fase 4: Verificar

```bash
composer dump-autoload
sail artisan config:clear
sail artisan route:clear
sail artisan test
```

---

## 10. Resumo

| Pasta | Arquivos | Central | Tenant | Shared |
|-------|----------|---------|--------|--------|
| Listeners | 2 | 2 | 0 | 0 |
| Jobs | 2 | 2 | 0 | 0 |
| Mail | 1 | 0 | 1 | 0 |
| Middleware | 7 | 0 | 3 | 4 |
| Exceptions | 7 | 3 | 3 | 1 |
| Actions/Fortify | 3 | 0 | 2 | 1 |
| Policies | 1 | 0 | 1 | 0 |
| **Total** | **23** | **7** | **10** | **6** |

---

## 11. Arquivos Críticos

Arquivos que contêm múltiplos registros e devem ser atualizados com cuidado:

| Arquivo | Contém |
|---------|--------|
| `bootstrap/app.php` | Registros de middleware e aliases |
| `app/Providers/AppServiceProvider.php` | Registros de Listeners para Stripe |
| `app/Providers/TenancyServiceProvider.php` | Referência ao job SeedTenantDatabase |
| `app/Providers/FortifyServiceProvider.php` | Registros das Actions do Fortify |
| `routes/tenant.php` | Import do middleware VerifyTenantAccess |

---

## Checklist de Implementação

> **Status: ✅ CONCLUÍDO** (06/12/2025)

### Fase 1: Estrutura
- [x] Criar `app/Listeners/Central/`
- [x] Criar `app/Listeners/Tenant/` com .gitkeep
- [x] Criar `app/Listeners/Shared/` com .gitkeep
- [x] Criar `app/Jobs/Central/`
- [x] Criar `app/Jobs/Tenant/` com .gitkeep
- [x] Criar `app/Jobs/Shared/` com .gitkeep
- [x] Criar `app/Mail/Central/` com .gitkeep
- [x] Criar `app/Mail/Tenant/`
- [x] Criar `app/Mail/Shared/` com .gitkeep
- [x] Criar `app/Http/Middleware/Central/` com .gitkeep
- [x] Criar `app/Http/Middleware/Tenant/`
- [x] Criar `app/Http/Middleware/Shared/`
- [x] Criar `app/Exceptions/Central/`
- [x] Criar `app/Exceptions/Tenant/`
- [x] Criar `app/Exceptions/Shared/`
- [x] Criar `app/Actions/Fortify/Tenant/`
- [x] Criar `app/Actions/Fortify/Shared/`
- [x] Criar `app/Policies/Central/` com .gitkeep
- [x] Criar `app/Policies/Tenant/`
- [x] Criar `app/Policies/Shared/` com .gitkeep

### Fase 2: Mover Arquivos
- [x] Mover Exceptions (7 arquivos)
- [x] Mover Mail (1 arquivo)
- [x] Mover Jobs (2 arquivos)
- [x] Mover Listeners (2 arquivos)
- [x] Mover Middleware (7 arquivos)
- [x] Mover Actions (3 arquivos)
- [x] Mover Policies (1 arquivo)

### Fase 3: Atualizar Imports
- [x] Atualizar `bootstrap/app.php`
- [x] Atualizar `app/Providers/AppServiceProvider.php`
- [x] Atualizar `app/Providers/TenancyServiceProvider.php`
- [x] Atualizar `app/Providers/FortifyServiceProvider.php`
- [x] Atualizar `routes/tenant.php`
- [x] Atualizar Services que usam Exceptions
- [x] Atualizar Controllers que usam Exceptions
- [x] Atualizar Testes

### Fase 4: Verificação
- [x] `composer dump-autoload` ✓
- [x] `php artisan config:clear` ✓
- [x] `php artisan route:clear` ✓
- [x] `php artisan route:list` - aplicação carrega corretamente ✓
- [x] Autoloader verifica todas as classes (php -r com class_exists) ✓
- [x] Verificar sintaxe PHP em todos os 23 arquivos ✓
