# Plano: Reestruturação de Namespaces dos Models

## Resumo Executivo

Reorganizar os models em `app/Models/` em uma estrutura baseada em namespaces que separa claramente Models Central, Tenant e Universal. A mudança principal inclui renomear `Admin` para `User` no namespace Central.

---

## 1. Análise do Estado Atual

### 1.1 Inventário de Models

| Model | Arquivo | Contexto DB | Usa `CentralConnection` |
|-------|---------|-------------|-------------------------|
| `Admin` | `app/Models/Admin.php` | Central | Sim |
| `User` | `app/Models/User.php` | Tenant | Não |
| `Tenant` | `app/Models/Tenant.php` | Central | Sim |
| `Domain` | `app/Models/Domain.php` | Central | Sim |
| `Plan` | `app/Models/Plan.php` | Central | Sim |
| `Addon` | `app/Models/Addon.php` | Central | Sim |
| `AddonBundle` | `app/Models/AddonBundle.php` | Central | Não (BUG!) |
| `AddonSubscription` | `app/Models/AddonSubscription.php` | Central | Sim |
| `AddonPurchase` | `app/Models/AddonPurchase.php` | Central | Sim |
| `TenantInvitation` | `app/Models/TenantInvitation.php` | Central | Sim |
| `Project` | `app/Models/Project.php` | Tenant | Não |
| `Activity` | `app/Models/Activity.php` | Tenant | Não |
| `Media` | `app/Models/Media.php` | Tenant | Não |
| `Role` | `app/Models/Role.php` | Ambos | Não |
| `Permission` | `app/Models/Permission.php` | Ambos | Não |
| `TenantTranslationOverride` | `app/Models/TenantTranslationOverride.php` | Tenant | Não |

### 1.2 Observações

1. **`Admin`** usa `CentralConnection` - são usuários administrativos do painel central
2. **`User`** NÃO usa `CentralConnection` - vive apenas em bancos tenant
3. **`Role` e `Permission`** são especiais - existem em AMBOS os bancos (Spatie Permission)
4. **`AddonBundle`** está faltando trait `CentralConnection` (BUG a corrigir!)

---

## 2. Estrutura Proposta

### 2.1 Nova Organização de Namespaces

```
app/Models/
├── Central/
│   ├── User.php              # Renomeado de Admin.php
│   ├── Tenant.php
│   ├── Domain.php
│   ├── Plan.php
│   ├── Addon.php
│   ├── AddonBundle.php
│   ├── AddonSubscription.php
│   ├── AddonPurchase.php
│   └── TenantInvitation.php
│
├── Tenant/
│   ├── User.php              # Movido de User.php
│   ├── Project.php
│   ├── Activity.php
│   ├── Media.php
│   └── TenantTranslationOverride.php
│
└── Shared/
    ├── Role.php              # Funciona em ambos contextos
    └── Permission.php
```

### 2.2 Classes Completas

```php
// Central Models
App\Models\Central\User::class           // Era Admin
App\Models\Central\Tenant::class
App\Models\Central\Domain::class
App\Models\Central\Plan::class
App\Models\Central\Addon::class
App\Models\Central\AddonBundle::class
App\Models\Central\AddonSubscription::class
App\Models\Central\AddonPurchase::class
App\Models\Central\TenantInvitation::class

// Tenant Models
App\Models\Tenant\User::class
App\Models\Tenant\Project::class
App\Models\Tenant\Activity::class
App\Models\Tenant\Media::class
App\Models\Tenant\TenantTranslationOverride::class

// Universal Models
App\Models\Shared\Role::class
App\Models\Shared\Permission::class
```

---

## 3. Etapas de Migração

### Fase 1: Preparação

1. Criar estrutura de diretórios
2. Corrigir bug do `AddonBundle` (adicionar `CentralConnection`)

### Fase 2: Mover Models Central

1. `Admin.php` → `Central/User.php` (RENOMEAR!)
2. `Tenant.php` → `Central/Tenant.php`
3. `Domain.php` → `Central/Domain.php`
4. `Plan.php` → `Central/Plan.php`
5. `Addon.php` → `Central/Addon.php`
6. `AddonBundle.php` → `Central/AddonBundle.php`
7. `AddonSubscription.php` → `Central/AddonSubscription.php`
8. `AddonPurchase.php` → `Central/AddonPurchase.php`
9. `TenantInvitation.php` → `Central/TenantInvitation.php`

### Fase 3: Mover Models Tenant

1. `User.php` → `Tenant/User.php`
2. `Project.php` → `Tenant/Project.php`
3. `Activity.php` → `Tenant/Activity.php`
4. `Media.php` → `Tenant/Media.php`
5. `TenantTranslationOverride.php` → `Tenant/TenantTranslationOverride.php`

### Fase 4: Mover Models Universal

1. `Role.php` → `Universal/Role.php`
2. `Permission.php` → `Universal/Permission.php`

### Fase 5: Atualizar Todas as Referências

- Arquivos de configuração
- Service Providers
- Controllers
- Services
- Factories
- Seeders
- Observers
- Jobs
- Commands
- Middleware
- Policies
- Tests

### Fase 6: Limpeza

1. Remover arquivos antigos
2. Rodar testes
3. Corrigir problemas

---

## 4. Arquivos a Atualizar

### 4.1 Configuração

| Arquivo | Mudanças |
|---------|----------|
| `config/auth.php` | `providers.admins.model` → `Central\User`, `providers.users.model` → `Tenant\User` |
| `config/tenancy.php` | `models.tenant` → `Central\Tenant`, `models.domain` → `Central\Domain` |

### 4.2 Service Providers

- `AppServiceProvider.php` - MorphMap, imports, observers
- `TenancyServiceProvider.php` - Referências de models
- `FortifyServiceProvider.php` - User → Tenant\User
- `AuthServiceProvider.php` - Referências de models
- `PlanFeatureServiceProvider.php` - Tenant import
- `TenancyFortifyServiceProvider.php` - Referências de models

### 4.3 Controllers (Principais)

**Central:**
- `Central/Admin/DashboardController.php`
- `Central/Admin/TenantManagementController.php`
- `Central/Admin/UserManagementController.php`
- `Central/Auth/AdminLoginController.php`
- `Central/Admin/ImpersonationController.php`
- `Central/Admin/AddonManagementController.php`

**Tenant:**
- `Tenant/Admin/DashboardController.php`
- `Tenant/Admin/ProjectController.php`
- `Tenant/Admin/TeamController.php`
- `Tenant/Admin/TenantRoleController.php`

### 4.4 Factories

| Arquivo Atual | Novo Arquivo |
|---------------|--------------|
| `AdminFactory.php` | `CentralUserFactory.php` |
| `UserFactory.php` | `TenantUserFactory.php` |
| `TenantFactory.php` | Atualizar namespace |
| `ProjectFactory.php` | Atualizar namespace |
| `PlanFactory.php` | Atualizar namespace |
| `AddonFactory.php` | Atualizar namespace |
| `AddonSubscriptionFactory.php` | Atualizar namespace |
| `AddonPurchaseFactory.php` | Atualizar namespace |

### 4.5 Seeders

| Arquivo Atual | Novo Arquivo |
|---------------|--------------|
| `AdminSeeder.php` | `CentralUserSeeder.php` |
| `TenantSeeder.php` | Atualizar imports |
| `PlanSeeder.php` | Atualizar imports |
| `AddonSeeder.php` | Atualizar imports |

### 4.6 Observers

- `TenantObserver.php` - Atualizar imports
- `UserObserver.php` - User → Tenant\User
- `ProjectObserver.php` - Atualizar imports
- `DomainObserver.php` - Atualizar imports
- `AddonSubscriptionObserver.php` - Atualizar imports

### 4.7 Services

- `AddonService.php`
- `CheckoutService.php`
- `MeteredBillingService.php`
- `PlanPermissionResolver.php`
- `PlanFeatureResolver.php`
- `StripeSyncService.php`

### 4.8 Commands

- `SyncPermissions.php`
- `SyncTenantPermissionsCommand.php`
- `MigrateOverridesToAddons.php`
- `SyncAddons.php`
- E outros...

### 4.9 Tests (~40 arquivos)

Todos os testes em `tests/Feature/` e `tests/Unit/` que referenciam models.

---

## 5. Atualização do MorphMap

### 5.1 MorphMap Atual

```php
Relation::enforceMorphMap([
    'user' => User::class,
    'tenant' => Tenant::class,
    'project' => Project::class,
    'addon_subscription' => AddonSubscription::class,
    'addon_purchase' => AddonPurchase::class,
]);
```

### 5.2 MorphMap Novo

```php
use App\Models\Central\Tenant;
use App\Models\Central\User as CentralUser;
use App\Models\Central\AddonSubscription;
use App\Models\Central\AddonPurchase;
use App\Models\Tenant\User as TenantUser;
use App\Models\Tenant\Project;
use App\Models\Tenant\Activity;
use App\Models\Tenant\Media;

Relation::enforceMorphMap([
    // Central models
    'central_user' => CentralUser::class,
    'tenant' => Tenant::class,
    'addon_subscription' => AddonSubscription::class,
    'addon_purchase' => AddonPurchase::class,

    // Tenant models
    'user' => TenantUser::class,  // Manter 'user' para compatibilidade
    'project' => Project::class,
    'activity' => Activity::class,
    'media' => Media::class,
]);
```

**Importante**: Manter `user` apontando para `TenantUser` para compatibilidade com activity logs e media.

---

## 6. Considerações de Banco de Dados

### 6.1 Nenhuma Mudança de Schema Necessária

A reestruturação é puramente organizacional de código. As tabelas permanecem:

| Tabela | Banco | Notas |
|--------|-------|-------|
| `admins` | Central | Manter nome (model renomeado) |
| `users` | Tenant | Sem mudança |
| `tenants` | Central | Sem mudança |
| ... | ... | ... |

### 6.2 Central\User Mantém Tabela `admins`

```php
// app/Models/Central/User.php
class User extends Authenticatable
{
    protected $table = 'admins';  // Manter tabela existente
}
```

---

## 7. Plano de Testes

### 7.1 Testes Incrementais

Após cada fase:
```bash
sail artisan test --stop-on-failure
```

### 7.2 Testes Específicos

```bash
sail artisan test --filter=Admin      # Central User
sail artisan test --filter=Tenant     # Tenant tests
sail artisan test --filter=User       # User models
sail artisan test --filter=Addon      # Addon tests
sail artisan test --filter=Permission # Permission tests
```

### 7.3 Testes E2E

```bash
sail npm run test:e2e
```

---

## 8. Cronograma Estimado

| Fase | Descrição | Arquivos |
|------|-----------|----------|
| 1 | Preparação + Central Models | ~15 |
| 2 | Tenant + Universal Models | ~10 |
| 3 | Providers + Config | ~10 |
| 4 | Controllers | ~25 |
| 5 | Services, Jobs, Commands | ~20 |
| 6 | Factories, Seeders, Observers | ~15 |
| 7 | Tests | ~40 |
| 8 | Limpeza + Validação | - |

**Total: ~135 arquivos a modificar**

---

## 9. Riscos e Mitigações

| Risco | Mitigação |
|-------|-----------|
| Imports não atualizados | Usar grep extensivo + IDE |
| MorphMap quebrar relações | Manter `user` → TenantUser |
| Auth guards falharem | Testar ambos guards |
| Factories não resolverem | Atualizar model references |

---

## 10. Checklist de Implementação

### Fase 1: Preparação
- [ ] Criar `app/Models/Central/`
- [ ] Criar `app/Models/Tenant/`
- [ ] Criar `app/Models/Shared/`
- [ ] Corrigir AddonBundle (adicionar CentralConnection)

### Fase 2: Central Models
- [ ] Mover Admin → Central/User (renomear classe)
- [ ] Mover Tenant → Central/Tenant
- [ ] Mover Domain → Central/Domain
- [ ] Mover Plan → Central/Plan
- [ ] Mover Addon → Central/Addon
- [ ] Mover AddonBundle → Central/AddonBundle
- [ ] Mover AddonSubscription → Central/AddonSubscription
- [ ] Mover AddonPurchase → Central/AddonPurchase
- [ ] Mover TenantInvitation → Central/TenantInvitation

### Fase 3: Tenant Models
- [ ] Mover User → Tenant/User
- [ ] Mover Project → Tenant/Project
- [ ] Mover Activity → Tenant/Activity
- [ ] Mover Media → Tenant/Media
- [ ] Mover TenantTranslationOverride → Tenant/TenantTranslationOverride

### Fase 4: Universal Models
- [ ] Mover Role → Universal/Role
- [ ] Mover Permission → Universal/Permission

### Fase 5: Atualizar Referências
- [ ] config/auth.php
- [ ] config/tenancy.php
- [ ] AppServiceProvider.php
- [ ] Todos os outros Providers
- [ ] Todos os Controllers
- [ ] Todos os Services
- [ ] Todas as Factories
- [ ] Todos os Seeders
- [ ] Todos os Observers
- [ ] Todos os Jobs
- [ ] Todos os Commands
- [ ] Todos os Middleware
- [ ] Todos os Policies
- [ ] Todos os Tests

### Fase 6: Limpeza
- [ ] Remover arquivos antigos de app/Models/
- [ ] Rodar migrate:fresh --seed
- [ ] Rodar test suite completo
- [ ] Testar manualmente login central
- [ ] Testar manualmente login tenant
- [ ] Testar impersonation
