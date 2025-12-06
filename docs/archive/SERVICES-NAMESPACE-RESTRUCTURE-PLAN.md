# Plano de Reestruturação de Namespaces dos Services

**Data**: 2024-12-05
**Status**: Aprovação Pendente

## Resumo Executivo

Reorganizar `app/Services/` seguindo o mesmo padrão de namespaces dos models:
- `App\Services\Central\*` - Services que operam no banco central
- `App\Services\Tenant\*` - Services que operam no contexto do tenant
- `App\Services\Universal\*` - Services que funcionam em ambos contextos

---

## 1. Estado Atual

### Services Existentes (7 arquivos)

| Service | Localização Atual |
|---------|-------------------|
| `AddonService.php` | `app/Services/AddonService.php` |
| `CheckoutService.php` | `app/Services/CheckoutService.php` |
| `StripeSyncService.php` | `app/Services/StripeSyncService.php` |
| `MeteredBillingService.php` | `app/Services/MeteredBillingService.php` |
| `PlanPermissionResolver.php` | `app/Services/PlanPermissionResolver.php` |
| `PlanFeatureResolver.php` | `app/Services/PlanFeatureResolver.php` |
| `PlanSyncService.php` | `app/Services/PlanSyncService.php` |

---

## 2. Análise de Classificação

### 2.1 AddonService

| Aspecto | Detalhe |
|---------|---------|
| **Models Usados** | `Central\Addon`, `Central\AddonBundle`, `Central\AddonSubscription`, `Central\Tenant` |
| **Contexto de BD** | Central database apenas |
| **Análise** | Gerencia compras de addons, cancelamentos e limites do tenant. Todas operações no banco central. |
| **Namespace Destino** | `App\Services\Central\AddonService` |

### 2.2 CheckoutService

| Aspecto | Detalhe |
|---------|---------|
| **Models Usados** | `Central\Addon`, `Central\AddonPurchase`, `Central\Tenant` |
| **Contexto de BD** | Central database apenas |
| **Análise** | Cria sessões Stripe Checkout para compras de addons. |
| **Namespace Destino** | `App\Services\Central\CheckoutService` |

### 2.3 StripeSyncService

| Aspecto | Detalhe |
|---------|---------|
| **Models Usados** | `Central\Addon`, `Central\AddonBundle` |
| **Contexto de BD** | Central database apenas |
| **Análise** | Sincroniza catálogo de addons e bundles com Stripe. |
| **Namespace Destino** | `App\Services\Central\StripeSyncService` |

### 2.4 MeteredBillingService

| Aspecto | Detalhe |
|---------|---------|
| **Models Usados** | `Central\Addon`, `Central\AddonSubscription`, `Central\Tenant` |
| **Contexto de BD** | Central database apenas |
| **Análise** | Reporta uso metered (storage, bandwidth) para o Stripe. |
| **Namespace Destino** | `App\Services\Central\MeteredBillingService` |

### 2.5 PlanPermissionResolver

| Aspecto | Detalhe |
|---------|---------|
| **Models Usados** | `Central\Tenant`, usa `TenantPermission` enum |
| **Contexto de BD** | Central database (lê do plano do tenant) |
| **Análise** | Resolve quais permissões um tenant deve ter baseado no plano. Opera no banco central. |
| **Namespace Destino** | `App\Services\Central\PlanPermissionResolver` |

### 2.6 PlanFeatureResolver

| Aspecto | Detalhe |
|---------|---------|
| **Models Usados** | `Central\Tenant`, usa `PlanFeature`/`PlanLimit` enums |
| **Contexto de BD** | Central database apenas |
| **Análise** | Resolve valores de features Pennant baseado nos planos. |
| **Namespace Destino** | `App\Services\Central\PlanFeatureResolver` |

### 2.7 PlanSyncService

| Aspecto | Detalhe |
|---------|---------|
| **Models Usados** | `Central\Plan` |
| **Contexto de BD** | Central database apenas |
| **Análise** | Sincroniza planos de assinatura com Stripe. |
| **Namespace Destino** | `App\Services\Central\PlanSyncService` |

---

## 3. Plano de Migração

### Fase 1: Criar Estrutura de Diretórios

```
app/Services/
├── Central/
│   ├── AddonService.php
│   ├── CheckoutService.php
│   ├── MeteredBillingService.php
│   ├── PlanFeatureResolver.php
│   ├── PlanPermissionResolver.php
│   ├── PlanSyncService.php
│   └── StripeSyncService.php
├── Tenant/
│   └── .gitkeep
└── Shared/
    └── .gitkeep
```

### Fase 2: Mover Arquivos e Atualizar Namespaces

| Localização Atual | Nova Localização | Novo Namespace |
|-------------------|------------------|----------------|
| `app/Services/AddonService.php` | `app/Services/Central/AddonService.php` | `App\Services\Central` |
| `app/Services/CheckoutService.php` | `app/Services/Central/CheckoutService.php` | `App\Services\Central` |
| `app/Services/StripeSyncService.php` | `app/Services/Central/StripeSyncService.php` | `App\Services\Central` |
| `app/Services/MeteredBillingService.php` | `app/Services/Central/MeteredBillingService.php` | `App\Services\Central` |
| `app/Services/PlanPermissionResolver.php` | `app/Services/Central/PlanPermissionResolver.php` | `App\Services\Central` |
| `app/Services/PlanFeatureResolver.php` | `app/Services/Central/PlanFeatureResolver.php` | `App\Services\Central` |
| `app/Services/PlanSyncService.php` | `app/Services/Central/PlanSyncService.php` | `App\Services\Central` |

### Fase 3: Atualizar Imports

#### 3.1 AddonService (7 arquivos)

| Arquivo | Import Antigo | Import Novo |
|---------|---------------|-------------|
| `app/Http/Controllers/Billing/AddonWebhookController.php` | `use App\Services\AddonService;` | `use App\Services\Central\AddonService;` |
| `app/Http/Controllers/Tenant/Admin/AddonController.php` | `use App\Services\AddonService;` | `use App\Services\Central\AddonService;` |
| `app/Http/Controllers/Central/Admin/AddonManagementController.php` | `use App\Services\AddonService;` | `use App\Services\Central\AddonService;` |
| `app/Console/Commands/SyncAddons.php` | `use App\Services\AddonService;` | `use App\Services\Central\AddonService;` |
| `app/Observers/AddonSubscriptionObserver.php` | `use App\Services\AddonService;` | `use App\Services\Central\AddonService;` |
| `tests/Feature/AddonServiceTest.php` | `use App\Services\AddonService;` | `use App\Services\Central\AddonService;` |
| `tests/Feature/AddonBundleTest.php` | `use App\Services\AddonService;` | `use App\Services\Central\AddonService;` |

#### 3.2 CheckoutService (3 arquivos)

| Arquivo | Import Antigo | Import Novo |
|---------|---------------|-------------|
| `app/Http/Controllers/Billing/AddonWebhookController.php` | `use App\Services\CheckoutService;` | `use App\Services\Central\CheckoutService;` |
| `app/Http/Controllers/Tenant/Admin/AddonController.php` | `use App\Services\CheckoutService;` | `use App\Services\Central\CheckoutService;` |
| `tests/Feature/CheckoutServiceTest.php` | `use App\Services\CheckoutService;` | `use App\Services\Central\CheckoutService;` |

#### 3.3 StripeSyncService (6 arquivos)

| Arquivo | Import Antigo | Import Novo |
|---------|---------------|-------------|
| `app/Http/Controllers/Central/Admin/AddonCatalogController.php` | `use App\Services\StripeSyncService;` | `use App\Services\Central\StripeSyncService;` |
| `app/Http/Controllers/Central/Admin/BundleCatalogController.php` | `use App\Services\StripeSyncService;` | `use App\Services\Central\StripeSyncService;` |
| `app/Console/Commands/SyncStripeProducts.php` | `use App\Services\StripeSyncService;` | `use App\Services\Central\StripeSyncService;` |
| `tests/Unit/StripeSyncServiceTest.php` | `use App\Services\StripeSyncService;` | `use App\Services\Central\StripeSyncService;` |
| `tests/Feature/StripeSyncServiceTest.php` | `use App\Services\StripeSyncService;` | `use App\Services\Central\StripeSyncService;` |
| `tests/Feature/BundleCatalogControllerTest.php` | `use App\Services\StripeSyncService;` | `use App\Services\Central\StripeSyncService;` |

#### 3.4 MeteredBillingService (3 arquivos)

| Arquivo | Import Antigo | Import Novo |
|---------|---------------|-------------|
| `app/Http/Controllers/Billing/AddonWebhookController.php` | `use App\Services\MeteredBillingService;` | `use App\Services\Central\MeteredBillingService;` |
| `app/Console/Commands/ReportMeteredUsage.php` | `use App\Services\MeteredBillingService;` | `use App\Services\Central\MeteredBillingService;` |
| `tests/Feature/MeteredBillingTest.php` | `use App\Services\MeteredBillingService;` | `use App\Services\Central\MeteredBillingService;` |

#### 3.5 PlanPermissionResolver (8 arquivos)

| Arquivo | Import Antigo | Import Novo |
|---------|---------------|-------------|
| `app/Jobs/SeedTenantDatabase.php` | `use App\Services\PlanPermissionResolver;` | `use App\Services\Central\PlanPermissionResolver;` |
| `app/Jobs/SyncTenantPermissions.php` | `use App\Services\PlanPermissionResolver;` | `use App\Services\Central\PlanPermissionResolver;` |
| `app/Console/Commands/SyncTenantPermissionsCommand.php` | `use App\Services\PlanPermissionResolver;` | `use App\Services\Central\PlanPermissionResolver;` |
| `app/Http/Controllers/Tenant/Admin/TenantRoleController.php` | `use App\Services\PlanPermissionResolver;` | `use App\Services\Central\PlanPermissionResolver;` |
| `app/Listeners/SyncPermissionsOnSubscriptionChange.php` | `use App\Services\PlanPermissionResolver;` | `use App\Services\Central\PlanPermissionResolver;` |
| `tests/Feature/TenantRoleControllerTest.php` | `use App\Services\PlanPermissionResolver;` | `use App\Services\Central\PlanPermissionResolver;` |
| `tests/Feature/PlanPermissionResolverTest.php` | `use App\Services\PlanPermissionResolver;` | `use App\Services\Central\PlanPermissionResolver;` |
| `tests/Feature/SyncTenantPermissionsTest.php` | `use App\Services\PlanPermissionResolver;` | `use App\Services\Central\PlanPermissionResolver;` |

#### 3.6 PlanFeatureResolver (1 arquivo)

| Arquivo | Import Antigo | Import Novo |
|---------|---------------|-------------|
| `app/Providers/PlanFeatureServiceProvider.php` | `use App\Services\PlanFeatureResolver;` | `use App\Services\Central\PlanFeatureResolver;` |

#### 3.7 PlanSyncService (2 arquivos)

| Arquivo | Import Antigo | Import Novo |
|---------|---------------|-------------|
| `app/Console/Commands/SyncStripePlans.php` | `use App\Services\PlanSyncService;` | `use App\Services\Central\PlanSyncService;` |
| `tests/Unit/PlanSyncServiceTest.php` | `use App\Services\PlanSyncService;` | `use App\Services\Central\PlanSyncService;` |

---

## 4. Bugs a Corrigir Durante a Migração

### 4.1 SyncStripeProducts.php (linha ~70)

```php
// Atual (incorreto)
$addon = \App\Models\Addon::where('slug', $addonSlug)->first();

// Correto
$addon = \App\Models\Central\Addon::where('slug', $addonSlug)->first();
```

### 4.2 SyncStripePlans.php (linha ~70)

```php
// Atual (incorreto)
\App\Models\Plan::...

// Correto
\App\Models\Central\Plan::...
```

### 4.3 ReportMeteredUsage.php (linhas ~28, ~51)

```php
// Atual (incorreto)
\App\Models\Tenant::...

// Correto
\App\Models\Central\Tenant::...
```

---

## 5. Checklist de Implementação

### Pré-Migração
- [ ] Rodar suite de testes completa (baseline)
- [ ] Commit do estado atual

### Migração
- [ ] Criar diretório `app/Services/Central/`
- [ ] Criar diretório `app/Services/Tenant/` (com .gitkeep)
- [ ] Criar diretório `app/Services/Shared/` (com .gitkeep)
- [ ] Mover e atualizar `AddonService.php`
- [ ] Mover e atualizar `CheckoutService.php`
- [ ] Mover e atualizar `StripeSyncService.php`
- [ ] Mover e atualizar `MeteredBillingService.php`
- [ ] Mover e atualizar `PlanPermissionResolver.php`
- [ ] Mover e atualizar `PlanFeatureResolver.php`
- [ ] Mover e atualizar `PlanSyncService.php`
- [ ] Atualizar imports em 30 arquivos
- [ ] Corrigir bugs de referência de models
- [ ] Rodar `composer dump-autoload`

### Pós-Migração
- [ ] Rodar suite de testes completa
- [ ] Atualizar CLAUDE.md
- [ ] Atualizar docs/SYSTEM-ARCHITECTURE.md
- [ ] Smoke test visual no browser

---

## 6. Estatísticas

| Categoria | Quantidade |
|-----------|------------|
| Services a mover | 7 |
| Arquivos a atualizar imports | 30 (únicos) |
| Bugs a corrigir | 3 |

---

## 7. Resultado Esperado

```
app/Services/
├── Central/
│   ├── AddonService.php           # Gerenciamento de addons
│   ├── CheckoutService.php        # Stripe Checkout
│   ├── MeteredBillingService.php  # Billing metered
│   ├── PlanFeatureResolver.php    # Resolução de features
│   ├── PlanPermissionResolver.php # Resolução de permissões
│   ├── PlanSyncService.php        # Sync de planos com Stripe
│   └── StripeSyncService.php      # Sync de produtos com Stripe
├── Tenant/
│   └── .gitkeep                   # Placeholder para futuros services
└── Shared/
    └── .gitkeep                   # Placeholder para futuros services
```
