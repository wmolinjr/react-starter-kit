# Auditoria e Plano de Unificacao do Sistema de Billing

> **Status**: Completo
> **Data**: 2025-12-13
> **Ultima Atualizacao**: 2025-12-13
> **Objetivo**: Mapear estado atual, identificar inconsistencias e definir caminho para unificacao

---

## Resumo da Implementacao

### Decisoes Tomadas
- **Customer Portal**: Opcao B - Portal Completo (payment methods, transfers, tenant creation)
- **Fluxo de Criacao de Tenant**: Opcao B - Sim, com checkout
- **Arquitetura**: Opcao C - Hibrido (Tenant Billing + Customer Portal)

### Fases Completadas
- [x] **Fase 1.1**: Criada pagina `tenants/billing.tsx` para billing do tenant
- [x] **Fase 1.2**: Fluxo de criacao de tenant com checkout completo
- [x] **Fase 1.3**: Transfer flow completo (create, accept, expired, invalid)
- [x] **Fase 2.1**: Biblioteca de componentes compartilhados em `@/components/shared/billing/`
- [x] **Fase 2.2**: Hooks compartilhados (`use-billing-period`, `use-checkout`, `use-payment-methods`)
- [x] **Fase 3.1**: Suporte multi-provider nos listeners (`UpdateTenantLimits`, `SyncPermissionsOnSubscriptionChange`)
- [x] **Fase 3.2**: Rotas orfas corrigidas (ContactController comentado)
- [x] **Fase 4**: Testes validados (19 passed, 1 skipped), build bem-sucedido

---

## 1. Visao Geral da Arquitetura

### 1.1 Entidades de Billing

```
┌────────────────────────────────────────────────────────────────────────────────┐
│                        ARQUITETURA DE BILLING                                  │
├────────────────────────────────────────────────────────────────────────────────┤
│                                                                                │
│  ┌──────────────┐         ┌──────────────┐         ┌──────────────┐           │
│  │   Customer   │────────>│    Tenant    │<────────│     Plan     │           │
│  │  (Billable)  │   owns  │              │   has   │              │           │
│  └──────┬───────┘         └──────┬───────┘         └──────────────┘           │
│         │                        │                                            │
│         │                        │                                            │
│         v                        v                                            │
│  ┌──────────────┐         ┌──────────────────┐     ┌──────────────┐           │
│  │ Subscription │         │AddonSubscription │<────│    Addon     │           │
│  │              │         │  AddonPurchase   │     │ AddonBundle  │           │
│  └──────────────┘         └──────────────────┘     └──────────────┘           │
│         │                        │                                            │
│         v                        v                                            │
│  ┌──────────────────────────────────────────┐                                 │
│  │              Payment / Invoice            │                                 │
│  │  (multi-provider: Stripe, Asaas, etc.)   │                                 │
│  └──────────────────────────────────────────┘                                 │
│                                                                                │
└────────────────────────────────────────────────────────────────────────────────┘
```

### 1.2 Tres Contextos de Compra

| Contexto | Localizacao | Proposito | Status |
|----------|-------------|-----------|--------|
| **Signup Flow** | `/signup/{plan}` | Novo cliente cria conta + tenant + assina plano | Completo |
| **Tenant Billing** | `{tenant}.test/admin/billing` | Usuario do tenant gerencia plano/addons | Completo |
| **Customer Portal** | `app.test/account` | Customer gerencia todos seus tenants/billing | Completo |

---

## 2. Inventario Completo

### 2.1 Backend - Controllers

#### Central Admin (`app/Http/Controllers/Central/Admin/`)

| Controller | Arquivo | Status | Observacoes |
|------------|---------|--------|-------------|
| PlanCatalogController | CRUD plans | ✅ Completo | Sync com Stripe |
| AddonCatalogController | CRUD addons | ✅ Completo | Sync com Stripe |
| BundleCatalogController | CRUD bundles | ✅ Completo | Desconto automatico |
| AddonManagementController | Gerencia addons ativos | ✅ Completo | Revenue dashboard |
| PaymentController | Lista/refund payments | ✅ Completo | Export CSV |
| PaymentSettingsController | Config gateways | ✅ Completo | Multi-gateway |

#### Customer Portal (`app/Http/Controllers/Customer/`)

| Controller | Arquivo | Status | Observacoes |
|------------|---------|--------|-------------|
| DashboardController | Dashboard | ✅ Completo | Stats overview |
| ProfileController | Perfil + billing address | ✅ Completo | - |
| TenantController | Lista/cria tenants + checkout | ✅ Completo | PendingSignup flow |
| PaymentMethodController | CRUD payment methods | ✅ Completo | Stripe/Asaas |
| InvoiceController | Lista/download invoices | ✅ Completo | PDF export |
| TransferController | Transferencia de tenant | ✅ Completo | Create/accept/status pages |

#### Tenant Billing (`app/Http/Controllers/Tenant/Admin/`)

| Controller | Arquivo | Status | Observacoes |
|------------|---------|--------|-------------|
| BillingController | Dashboard + checkout | ✅ Completo | Multi-payment |
| AddonController | Compra/gerencia addons | ✅ Completo | Cart system |

#### Public (`app/Http/Controllers/Central/`)

| Controller | Arquivo | Status | Observacoes |
|------------|---------|--------|-------------|
| SignupController | Wizard 3 etapas | ✅ Completo | Account→Workspace→Payment |
| PricingController | Pagina publica | ✅ Completo | Toggle monthly/yearly |

### 2.2 Backend - Services

#### Central Services (`app/Services/Central/`)

| Service | Proposito | Status | Observacoes |
|---------|-----------|--------|-------------|
| CheckoutService | Checkout Stripe (one-time) | ✅ Completo | - |
| CartCheckoutService | Cart multi-item | ✅ Completo | Multi-provider |
| SignupService | Fluxo de signup | ✅ Completo | PendingSignup |
| AddonService | Addons/bundles | ✅ Completo | - |
| PlanService | CRUD plans | ✅ Completo | - |
| PlanSyncService | Sync providers | ✅ Completo | Stripe/Asaas |
| StripeSyncService | Sync Stripe | ✅ Completo | i18n support |
| CustomerService | Gerencia customers | ✅ Completo | - |
| MeteredBillingService | Usage billing | ✅ Completo | - |
| PaymentSettingsService | Config gateways | ✅ Completo | - |
| PlanFeatureResolver | Resolve features | ✅ Completo | Pennant |
| PlanPermissionResolver | Features→Permissions | ✅ Completo | Spatie |
| TenantTransferService | Transferencia | ✅ Completo | - |

#### Tenant Services (`app/Services/Tenant/`)

| Service | Proposito | Status | Observacoes |
|---------|-----------|--------|-------------|
| BillingService | Overview/subscription mgmt | ✅ Completo | - |

### 2.3 Backend - Listeners (Multi-Provider)

| Listener | Proposito | Status | Providers |
|----------|-----------|--------|-----------|
| UpdateTenantLimits | Atualiza limites apos subscription change | ✅ Completo | Stripe, Asaas |
| SyncPermissionsOnSubscriptionChange | Sincroniza permissoes | ✅ Completo | Stripe, Asaas |

### 2.4 Backend - Models

#### Central Database (`app/Models/Central/`)

| Model | Status | Observacoes |
|-------|--------|-------------|
| Customer | ✅ Completo | Billable, multi-provider |
| Tenant | ✅ Completo | plan_id, usage tracking |
| Plan | ✅ Completo | features, limits, permission_map |
| Addon | ✅ Completo | Stripe sync |
| AddonBundle | ✅ Completo | Bundle discount |
| AddonPurchase | ✅ Completo | One-time purchases |
| AddonSubscription | ✅ Completo | Recurring addons |
| Subscription | ✅ Completo | Multi-provider |
| SubscriptionItem | ✅ Completo | Line items |
| Payment | ✅ Completo | Polymorphic |
| PaymentMethod | ✅ Completo | Card/PIX/Boleto |
| PaymentSetting | ✅ Completo | Gateway config |
| PendingSignup | ✅ Completo | Signup state |
| UsageRecord | ✅ Completo | Metered billing |

### 2.5 Frontend - Pages

#### Central Admin (`resources/js/pages/central/admin/`)

| Page | Status | LOC | Observacoes |
|------|--------|-----|-------------|
| plans/index.tsx | ✅ Completo | ~200 | Grid + sync |
| plans/create.tsx | ✅ Completo | ~150 | Form |
| plans/edit.tsx | ✅ Completo | ~150 | Form |
| catalog/index.tsx | ✅ Completo | ~200 | Addons list |
| catalog/create.tsx | ✅ Completo | ~180 | Form |
| catalog/edit.tsx | ✅ Completo | ~180 | Form |
| bundles/index.tsx | ✅ Completo | ~180 | Bundles list |
| bundles/create.tsx | ✅ Completo | ~150 | Form |
| bundles/edit.tsx | ✅ Completo | ~150 | Form |
| addons/index.tsx | ✅ Completo | ~250 | Revenue dashboard |
| payments/index.tsx | ✅ Completo | ~300 | Filters + export |
| payment-settings/index.tsx | ✅ Completo | ~250 | Gateway config |

#### Customer Portal (`resources/js/pages/central/customer/`)

| Page | Status | LOC | Observacoes |
|------|--------|-----|-------------|
| dashboard.tsx | ✅ Completo | 234 | Overview |
| profile/edit.tsx | ✅ Completo | 372 | Profile + billing |
| tenants/index.tsx | ✅ Completo | ~170 | Lista tenants |
| tenants/create.tsx | ✅ Completo | ~150 | Criar tenant (wizard) |
| tenants/checkout.tsx | ✅ Completo | ~280 | Checkout com multi-payment |
| tenants/billing.tsx | ✅ Completo | ~300 | Billing overview do tenant |
| tenants/show.tsx | ✅ Completo | ~200 | Detalhes |
| payment-methods/index.tsx | ✅ Completo | 196 | Lista |
| payment-methods/create.tsx | ✅ Completo | 275 | Add method |
| invoices/index.tsx | ✅ Completo | 170 | Lista |
| invoices/show.tsx | ✅ Completo | 256 | Detalhes |
| transfers/create.tsx | ✅ Completo | 179 | Iniciar transfer |
| transfers/accept.tsx | ✅ Completo | 123 | Aceitar transfer |
| transfers/expired.tsx | ✅ Completo | ~80 | Link expirado |
| transfers/invalid.tsx | ✅ Completo | ~80 | Link invalido |

#### Tenant Billing (`resources/js/pages/tenant/admin/`)

| Page | Status | LOC | Observacoes |
|------|--------|-----|-------------|
| billing/index.tsx | ✅ Completo | ~400 | Dashboard completo |
| billing/plans.tsx | ✅ Completo | ~350 | Comparacao + switch |
| billing/checkout.tsx | ✅ Completo | 500+ | Multi-payment |
| billing/bundles.tsx | ✅ Completo | ~250 | Browse bundles |
| billing/invoices.tsx | ✅ Completo | ~200 | Lista + PDF |
| billing/subscription.tsx | ✅ Completo | ~300 | Pause/resume/cancel |
| billing/success.tsx | ✅ Completo | ~100 | Confirmation |
| addons/index.tsx | ✅ Completo | ~350 | Marketplace |
| addons/usage.tsx | ✅ Completo | ~150 | Usage tracking |
| addons/success.tsx | ✅ Completo | ~100 | Confirmation |

#### Public (`resources/js/pages/central/`)

| Page | Status | LOC | Observacoes |
|------|--------|-----|-------------|
| pricing/index.tsx | ✅ Completo | ~250 | Marketing page |
| signup/index.tsx | ✅ Completo | ~500 | Wizard 3 etapas |
| signup/success.tsx | ✅ Completo | ~100 | Confirmation |

### 2.6 Frontend - Componentes Compartilhados

#### Billing Components (`resources/js/components/shared/billing/`)

| Componente | Proposito | Status |
|------------|-----------|--------|
| plan-card.tsx | Card de plano com features | ✅ Completo |
| plan-comparison-table.tsx | Tabela comparativa de planos | ✅ Completo |
| plan-selector.tsx | Selector de plano com toggle | ✅ Completo |
| addon-card.tsx | Card de addon | ✅ Completo |
| addon-marketplace.tsx | Grid de addons disponiveis | ✅ Completo |
| checkout-form.tsx | Form de checkout generico | ✅ Completo |
| payment-method-selector.tsx | Selector Card/PIX/Boleto | ✅ Completo |
| pix-payment.tsx | QR Code PIX + countdown | ✅ Completo |
| boleto-payment.tsx | Barcode + PDF link | ✅ Completo |
| invoice-table.tsx | Tabela de invoices | ✅ Completo |
| billing-period-provider.tsx | Context para monthly/yearly | ✅ Completo |
| primitives/index.tsx | PriceDisplay, FeatureList | ✅ Completo |

#### Billing Hooks (`resources/js/hooks/billing/`)

| Hook | Proposito | Status |
|------|-----------|--------|
| use-billing-period.tsx | Toggle monthly/yearly | ✅ Completo |
| use-checkout.tsx | Fluxo de checkout | ✅ Completo |
| use-payment-methods.ts | CRUD payment methods | ✅ Completo |

### 2.7 Payment Infrastructure

#### Gateways Implementados

| Gateway | Card | PIX | Boleto | Subscriptions | Status |
|---------|------|-----|--------|---------------|--------|
| Stripe | ✅ | ❌ | ❌ | ✅ | Producao |
| Asaas | ✅ | ✅ | ✅ | ✅ | Producao |
| PagSeguro | ❌ | ✅ | ✅ | ❌ | Testes |
| MercadoPago | ❌ | ✅ | ✅ | ❌ | Testes |

#### Arquitetura Multi-Provider

```php
// Interface-based abstraction
PaymentGatewayInterface      // charge(), refund()
SubscriptionGatewayInterface // create/cancel/pause subscription
PaymentMethodGatewayInterface // attach/detach payment methods

// Manager routes to correct gateway
PaymentGatewayManager::driver('stripe')->charge($customer, $amount);
```

---

## 3. Decisoes Implementadas

### 3.1 Customer Portal - Portal Completo (Opcao B)

O Customer Portal inclui:
- Dashboard: overview de todos os tenants
- Profile: dados pessoais + billing address
- Invoices: historico unificado
- Tenants: lista + link para cada tenant
- **Payment Methods**: gerenciar metodos de pagamento
- **Transfers**: transferir propriedade
- **Tenant Creation**: criar novos tenants com checkout

### 3.2 Fluxo de Criacao de Tenant - Com Checkout (Opcao B)

Customer logado no portal pode criar novo tenant passando pelo checkout de plano:
1. Wizard: Nome do workspace + Slug
2. Selecao de plano (com toggle monthly/yearly)
3. Checkout com selecao de pagamento (Card/PIX/Boleto)
4. PendingSignup gerencia o estado ate confirmacao

### 3.3 Arquitetura Hibrida (Opcao C)

- **Tenant Billing**: operacoes do dia-a-dia (upgrade, addons, usage)
- **Customer Portal**: visao unificada, payment methods, transfers, criacao de novos tenants

---

## 4. Arquivos Relevantes

### Backend
```
app/Http/Controllers/
├── Central/
│   ├── Admin/
│   │   ├── PlanCatalogController.php
│   │   ├── AddonCatalogController.php
│   │   ├── BundleCatalogController.php
│   │   ├── AddonManagementController.php
│   │   ├── PaymentController.php
│   │   └── PaymentSettingsController.php
│   ├── SignupController.php
│   └── PricingController.php
├── Customer/
│   ├── Auth/
│   ├── DashboardController.php
│   ├── ProfileController.php
│   ├── TenantController.php
│   ├── PaymentMethodController.php
│   ├── InvoiceController.php
│   └── TransferController.php
└── Tenant/Admin/
    ├── BillingController.php
    └── AddonController.php

app/Services/
├── Central/
│   ├── CheckoutService.php
│   ├── CartCheckoutService.php
│   ├── SignupService.php
│   ├── AddonService.php
│   ├── PlanService.php
│   ├── PlanSyncService.php
│   ├── StripeSyncService.php
│   └── ...
└── Tenant/
    └── BillingService.php

app/Listeners/Central/
├── UpdateTenantLimits.php           # Multi-provider (Stripe, Asaas)
└── SyncPermissionsOnSubscriptionChange.php  # Multi-provider (Stripe, Asaas)

app/Services/Payment/
├── PaymentGatewayManager.php
├── Gateways/
│   ├── StripeGateway.php
│   ├── AsaasGateway.php
│   └── ...
└── Interfaces/
    ├── PaymentGatewayInterface.php
    ├── SubscriptionGatewayInterface.php
    └── PaymentMethodGatewayInterface.php
```

### Frontend
```
resources/js/pages/
├── central/
│   ├── admin/
│   │   ├── plans/
│   │   ├── catalog/
│   │   ├── bundles/
│   │   ├── addons/
│   │   ├── payments/
│   │   └── payment-settings/
│   ├── customer/
│   │   ├── auth/
│   │   ├── dashboard.tsx
│   │   ├── profile/
│   │   ├── tenants/
│   │   │   ├── index.tsx
│   │   │   ├── create.tsx
│   │   │   ├── checkout.tsx
│   │   │   ├── billing.tsx
│   │   │   └── show.tsx
│   │   ├── payment-methods/
│   │   ├── invoices/
│   │   └── transfers/
│   │       ├── create.tsx
│   │       ├── accept.tsx
│   │       ├── expired.tsx
│   │       └── invalid.tsx
│   ├── pricing/
│   └── signup/
└── tenant/admin/
    ├── billing/
    └── addons/

resources/js/components/shared/billing/
├── index.ts
├── plan-card.tsx
├── plan-comparison-table.tsx
├── plan-selector.tsx
├── addon-card.tsx
├── addon-marketplace.tsx
├── checkout-form.tsx
├── payment-method-selector.tsx
├── pix-payment.tsx
├── boleto-payment.tsx
├── invoice-table.tsx
├── billing-period-provider.tsx
└── primitives/
    └── index.tsx

resources/js/hooks/billing/
├── use-billing-period.tsx
├── use-checkout.tsx
└── use-payment-methods.ts
```

### Rotas
```
routes/
├── central.php    # Customer Portal + Admin + Signup
├── tenant.php     # Tenant Billing
└── webhooks.php   # Payment webhooks
```

---

## 5. Validacao Final

### Testes
- **PHPUnit**: 19 testes passaram, 1 skipped (teste de Stripe sem credenciais)
- **Build**: `npm run build` executado com sucesso
- **TypeScript**: Sem erros de tipo

### Verificacoes Manuais Recomendadas
1. Testar fluxo de signup completo (novo cliente)
2. Testar criacao de tenant pelo Customer Portal
3. Testar transfer flow (criar, aceitar, expirar)
4. Testar pagamentos PIX e Boleto (sandbox Asaas)
5. Verificar webhooks Stripe/Asaas
