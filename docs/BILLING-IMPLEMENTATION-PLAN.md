# Plano de Implementacao - Sistema de Billing Unificado

> **Status**: Aprovado
> **Data**: 2025-12-13
> **Decisoes**:
> - Customer Portal: Completo
> - Criacao de Tenant: Com checkout de plano
> - Arquitetura: Hibrida (Tenant Billing + Customer Portal)

---

## Visao Geral da Arquitetura Final

```
┌────────────────────────────────────────────────────────────────────────────────┐
│                        ARQUITETURA HIBRIDA DE BILLING                          │
├────────────────────────────────────────────────────────────────────────────────┤
│                                                                                │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                    CUSTOMER PORTAL (app.test/account)                    │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌─────────────┐  │   │
│  │  │  Dashboard   │  │   Tenants    │  │   Payment    │  │  Invoices   │  │   │
│  │  │  (overview)  │  │  (list/new)  │  │   Methods    │  │  (unified)  │  │   │
│  │  └──────────────┘  └──────────────┘  └──────────────┘  └─────────────┘  │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐                   │   │
│  │  │   Profile    │  │  Transfers   │  │   Tenant     │                   │   │
│  │  │  (billing)   │  │  (ownership) │  │   Billing    │                   │   │
│  │  └──────────────┘  └──────────────┘  └──────────────┘                   │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                      │                                         │
│                                      │ owns                                    │
│                                      ▼                                         │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                    TENANT BILLING ({tenant}.test/admin/billing)          │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌─────────────┐  │   │
│  │  │   Billing    │  │    Plans     │  │   Addons     │  │  Checkout   │  │   │
│  │  │  Dashboard   │  │  (upgrade)   │  │ (marketplace)│  │(multi-pay)  │  │   │
│  │  └──────────────┘  └──────────────┘  └──────────────┘  └─────────────┘  │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐                   │   │
│  │  │  Invoices    │  │ Subscription │  │   Bundles    │                   │   │
│  │  │  (tenant)    │  │   (manage)   │  │  (purchase)  │                   │   │
│  │  └──────────────┘  └──────────────┘  └──────────────┘                   │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                │
└────────────────────────────────────────────────────────────────────────────────┘
```

---

## Fase 1: Completar Customer Portal (Prioridade Alta)

### 1.1 Pagina de Billing do Tenant (FALTANDO)

**Problema**: Rota e controller existem, mas pagina nao existe.

**Arquivo a criar**: `resources/js/pages/central/customer/tenants/billing.tsx`

**Tasks**:
- [ ] **T1.1.1** Criar pagina `tenants/billing.tsx`
  - Mostrar plano atual do tenant
  - Mostrar subscription status
  - Mostrar payment method atual (com opcao de trocar)
  - Mostrar invoices recentes do tenant
  - Link para billing completo no tenant

**Controller**: `CustomerTenantController::billing()` (ja existe)

**Estimativa**: 4-6 horas

---

### 1.2 Fluxo de Criacao de Tenant com Checkout

**Problema**: Atualmente cria tenant sem selecionar plano.

**Tasks**:
- [ ] **T1.2.1** Modificar `tenants/create.tsx` para wizard de 2 etapas:
  1. **Etapa 1**: Dados do workspace (name, slug)
  2. **Etapa 2**: Selecao de plano + checkout

- [ ] **T1.2.2** Criar componente `PlanSelector` reutilizavel
  - Grid de planos disponiveis
  - Toggle monthly/yearly
  - Destacar features de cada plano
  - Mostrar preco

- [ ] **T1.2.3** Modificar `CustomerTenantController::store()`:
  - Validar `plan_id` obrigatorio
  - Integrar com `SignupService` ou criar `TenantCheckoutService`
  - Redirecionar para checkout se necessario

- [ ] **T1.2.4** Criar rota de checkout do customer portal:
  - `POST /account/tenants` → cria PendingTenant
  - `GET /account/tenants/{pending}/checkout` → pagina de pagamento
  - `POST /account/tenants/{pending}/checkout` → processa pagamento

- [ ] **T1.2.5** Criar pagina `tenants/checkout.tsx`:
  - Reutilizar componentes de checkout do tenant billing
  - Suporte multi-provider (Stripe, Asaas, PIX, Boleto)

**Estimativa**: 16-24 horas

---

### 1.3 Transfer Flow Completo

**Problema**: Rotas existem, frontend parcial.

**Tasks**:
- [ ] **T1.3.1** Revisar `TransferController` e garantir todos os metodos funcionam
- [ ] **T1.3.2** Completar traducoes de transfer (pt_BR, es)
- [ ] **T1.3.3** Adicionar testes E2E para transfer flow
- [ ] **T1.3.4** Adicionar notificacao por email para destinatario

**Estimativa**: 8-12 horas

---

## Fase 2: Unificar Componentes de Billing

### 2.1 Criar Biblioteca de Componentes Compartilhados

**Objetivo**: Single source of truth para UI de billing.

**Estrutura proposta**:
```
resources/js/components/billing/
├── cards/
│   ├── plan-card.tsx           # Card de plano individual
│   ├── addon-card.tsx          # Card de addon
│   ├── bundle-card.tsx         # Card de bundle
│   └── subscription-card.tsx   # Card de subscription status
├── selectors/
│   ├── plan-selector.tsx       # Grid de planos para selecao
│   ├── addon-selector.tsx      # Marketplace de addons
│   ├── payment-method-selector.tsx  # Selector Card/PIX/Boleto
│   └── billing-period-toggle.tsx    # Toggle monthly/yearly
├── checkout/
│   ├── checkout-cart.tsx       # Carrinho de compras
│   ├── checkout-summary.tsx    # Resumo do pedido
│   ├── checkout-payment.tsx    # Formulario de pagamento
│   ├── pix-payment.tsx         # QR Code PIX
│   └── boleto-payment.tsx      # Codigo de barras
├── tables/
│   ├── invoice-table.tsx       # Tabela de invoices
│   └── payment-history-table.tsx
├── widgets/
│   ├── billing-overview.tsx    # Overview de billing
│   ├── usage-meter.tsx         # Medidor de uso
│   └── cost-breakdown.tsx      # Breakdown de custos
└── index.ts                    # Re-exports
```

**Tasks**:
- [ ] **T2.1.1** Mover componentes existentes para estrutura unificada
- [ ] **T2.1.2** Criar re-exports em `@/components/billing`
- [ ] **T2.1.3** Atualizar imports em todas as paginas
- [ ] **T2.1.4** Documentar props de cada componente

**Estimativa**: 8-12 horas

---

### 2.2 Criar Hooks Compartilhados

**Estrutura**:
```
resources/js/hooks/billing/
├── use-billing.ts        # Dados de billing do contexto atual
├── use-checkout.ts       # Estado do checkout/cart
├── use-plan.ts           # Features e limits (existe)
├── use-subscription.ts   # Status da subscription
└── use-payment-methods.ts # Payment methods do customer
```

**Tasks**:
- [ ] **T2.2.1** Criar `useBilling()` hook
- [ ] **T2.2.2** Criar `useSubscription()` hook
- [ ] **T2.2.3** Criar `usePaymentMethods()` hook
- [ ] **T2.2.4** Refatorar paginas para usar hooks

**Estimativa**: 6-8 horas

---

## Fase 3: Corrigir TODOs e Inconsistencias

### 3.1 Suporte Multi-Provider nos Listeners

**Problema**: `SyncPermissionsOnSubscriptionChange` e `UpdateTenantLimits` so suportam Stripe.

**Tasks**:
- [ ] **T3.1.1** Refatorar `SyncPermissionsOnSubscriptionChange`:
  ```php
  // Antes
  // TODO: Add support for other providers

  // Depois
  match ($event->subscription->provider) {
      'stripe' => $this->handleStripe($event),
      'asaas' => $this->handleAsaas($event),
      default => Log::warning("Unsupported provider: {$event->subscription->provider}"),
  };
  ```

- [ ] **T3.1.2** Refatorar `UpdateTenantLimits` da mesma forma

- [ ] **T3.1.3** Criar testes para cada provider

**Estimativa**: 4-6 horas

---

### 3.2 Validar Rotas Orfas

**Tasks**:
- [ ] **T3.2.1** Listar todas as rotas de billing:
  ```bash
  sail artisan route:list | grep -E "billing|payment|checkout|addon|invoice"
  ```

- [ ] **T3.2.2** Verificar se cada rota tem:
  - Controller method implementado
  - Pagina frontend correspondente
  - Traducoes completas

- [ ] **T3.2.3** Remover rotas sem implementacao ou implementar

**Estimativa**: 2-4 horas

---

## Fase 4: Testes e Documentacao

### 4.1 Testes de Integracao

**Tasks**:
- [ ] **T4.1.1** Criar `CustomerPortalBillingTest`:
  - Listar tenants
  - Criar tenant com checkout
  - Ver billing do tenant
  - Trocar payment method

- [ ] **T4.1.2** Criar `TenantBillingFlowTest`:
  - Upgrade de plano
  - Comprar addon
  - Cancelar subscription
  - Pause/resume

- [ ] **T4.1.3** Criar `MultiProviderPaymentTest`:
  - Checkout com Stripe
  - Checkout com Asaas
  - Checkout com PIX
  - Checkout com Boleto

**Estimativa**: 12-16 horas

---

### 4.2 Testes E2E (Playwright)

**Tasks**:
- [ ] **T4.2.1** Criar `billing-flow.spec.ts`:
  - Fluxo completo de signup → billing → addons
  - Fluxo de upgrade de plano
  - Fluxo de checkout multi-payment

- [ ] **T4.2.2** Criar `customer-portal.spec.ts`:
  - Login customer
  - Criar tenant
  - Ver invoices
  - Gerenciar payment methods

**Estimativa**: 8-12 horas

---

### 4.3 Documentacao

**Tasks**:
- [ ] **T4.3.1** Atualizar `CUSTOMER-PORTAL.md`:
  - Adicionar fluxo de criacao de tenant com checkout
  - Documentar billing do tenant

- [ ] **T4.3.2** Criar `BILLING-FLOWS.md`:
  - Documentar 3 fluxos de compra
  - Diagramas de sequencia
  - Exemplos de uso

- [ ] **T4.3.3** Criar `PAYMENT-PROVIDERS.md`:
  - Como adicionar novo provider
  - Configuracao de webhooks
  - Troubleshooting

**Estimativa**: 6-8 horas

---

## Fase 5: Limpeza Final

### 5.1 Remover Codigo Morto

**Tasks**:
- [ ] **T5.1.1** Buscar e remover imports nao utilizados
- [ ] **T5.1.2** Remover componentes nao referenciados
- [ ] **T5.1.3** Remover traducoes nao utilizadas
- [ ] **T5.1.4** Rodar `npm run lint` e corrigir warnings

**Estimativa**: 2-4 horas

---

### 5.2 Padronizar Nomenclatura

**Tasks**:
- [ ] **T5.2.1** Garantir consistencia nos nomes de rotas:
  - Customer: `central.account.*`
  - Tenant: `tenant.admin.billing.*`

- [ ] **T5.2.2** Garantir consistencia nos nomes de componentes:
  - `*Card` para cards
  - `*Selector` para seletores
  - `*Table` para tabelas
  - `*Widget` para widgets

**Estimativa**: 2-3 horas

---

## Cronograma Sugerido

| Fase | Tasks | Estimativa | Dependencias |
|------|-------|------------|--------------|
| **Fase 1.1** | Billing page | 4-6h | - |
| **Fase 1.2** | Tenant checkout | 16-24h | 1.1 |
| **Fase 1.3** | Transfer flow | 8-12h | - |
| **Fase 2.1** | Componentes | 8-12h | 1.2 |
| **Fase 2.2** | Hooks | 6-8h | 2.1 |
| **Fase 3.1** | Multi-provider | 4-6h | - |
| **Fase 3.2** | Rotas orfas | 2-4h | - |
| **Fase 4.1** | Testes PHP | 12-16h | 1.2, 3.1 |
| **Fase 4.2** | Testes E2E | 8-12h | 4.1 |
| **Fase 4.3** | Docs | 6-8h | Todas |
| **Fase 5** | Limpeza | 4-7h | Todas |

**Total estimado**: 74-115 horas (~2-3 semanas com 1 dev full-time)

---

## Ordem de Execucao Recomendada

### Sprint 1: Fundacao (Semana 1)
1. T1.1.1 - Criar billing page
2. T3.1.1, T3.1.2 - Fix TODOs multi-provider
3. T3.2.* - Validar rotas orfas

### Sprint 2: Tenant Checkout (Semana 2)
1. T1.2.1 - Wizard de criacao
2. T1.2.2 - PlanSelector component
3. T1.2.3, T1.2.4 - Backend checkout
4. T1.2.5 - Frontend checkout

### Sprint 3: Unificacao (Semana 3)
1. T2.1.* - Componentes compartilhados
2. T2.2.* - Hooks compartilhados
3. T1.3.* - Transfer flow

### Sprint 4: Quality (Semana 4)
1. T4.1.* - Testes PHP
2. T4.2.* - Testes E2E
3. T4.3.* - Documentacao
4. T5.* - Limpeza

---

## Metricas de Sucesso

| Metrica | Antes | Depois |
|---------|-------|--------|
| Cobertura de testes billing | ~60% | >90% |
| Componentes duplicados | ~15 | 0 |
| Rotas sem frontend | ~3 | 0 |
| TODOs em billing | 2 | 0 |
| Providers suportados | 2 (parcial) | 4 (completo) |

---

## Riscos e Mitigacoes

| Risco | Probabilidade | Impacto | Mitigacao |
|-------|---------------|---------|-----------|
| Quebrar checkout existente | Media | Alto | Feature flag + testes antes |
| Incompatibilidade Stripe/Asaas | Baixa | Medio | Abstrair via interface |
| Migracao de componentes | Media | Baixo | Fazer gradualmente |

---

## Aprovacoes Necessarias

- [ ] Revisar estimativas com time
- [ ] Confirmar prioridade das fases
- [ ] Definir data de inicio

---

## Referencias

- [BILLING-AUDIT-PLAN.md](./BILLING-AUDIT-PLAN.md) - Auditoria inicial
- [CUSTOMER-PORTAL.md](./CUSTOMER-PORTAL.md) - Documentacao atual
- [SYSTEM-ARCHITECTURE.md](./SYSTEM-ARCHITECTURE.md) - Arquitetura de planos
- [ADDONS.md](./ADDONS.md) - Sistema de addons
