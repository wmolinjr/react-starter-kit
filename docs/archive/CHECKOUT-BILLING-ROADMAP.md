# Checkout & Billing Roadmap

> Plano de execução para completar o sistema de billing multi-pagamento.
>
> **Status**: Fase 6 e 7 concluídas ✅
> **Última atualização**: 2025-12-10
> **Progresso**: ~85% completo

## Visão Geral

Este documento detalha o plano de implementação para o sistema completo de billing com suporte a múltiplos métodos de pagamento (Card, PIX, Boleto).

### Resumo do Progresso

| Fase | Descrição | Status | Progresso |
|------|-----------|--------|-----------|
| 1 | Webhooks e Confirmação | ✅ Concluída | 100% |
| 2 | Integração Frontend | ✅ Concluída | 100% |
| 3 | Notificações por Email | ✅ Concluída | 100% |
| 4 | Admin Dashboard Pagamentos | ✅ Concluída | 100% |
| 5 | Metered Billing | ⏳ Parcial | ~60% |
| 6 | Subscription Management | ✅ Concluída | 100% |
| 7 | Melhorias de UX | ✅ Concluída | 100% |
| 8 | Testes Adicionais | ⏳ Parcial | ~60% |

**Progresso Total**: ~85%

### Arquitetura Atual

```
┌─────────────────────────────────────────────────────────────────┐
│                        Frontend (React)                          │
├─────────────────────────────────────────────────────────────────┤
│  CheckoutPaymentSheet  │  PixPayment  │  BoletoPayment          │
│  PaymentMethodSelector │  CheckoutSheet │ CheckoutSummary       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        Backend (Laravel)                         │
├─────────────────────────────────────────────────────────────────┤
│  BillingController     │  CartCheckoutService                   │
│  PaymentGatewayManager │  AsaasGateway │ StripeGateway          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                     Payment Providers                            │
├─────────────────────────────────────────────────────────────────┤
│  Stripe (Card)         │  Asaas (PIX, Boleto)                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Fase 1: Webhooks e Confirmação de Pagamento

**Duração estimada**: 2-3 dias
**Prioridade**: CRÍTICA

### 1.1 Asaas Webhook Handler

**Objetivo**: Receber notificações do Asaas quando PIX/Boleto for pago.

**Arquivos a criar/modificar**:
- [ ] `app/Http/Controllers/Webhooks/AsaasWebhookController.php`
- [ ] `app/Services/Payment/Webhooks/AsaasWebhookService.php`
- [ ] `routes/webhooks.php` - Adicionar rota `/webhooks/asaas`
- [ ] `config/payment.php` - Adicionar `asaas.webhook_secret`

**Eventos a tratar**:
```php
// Eventos do Asaas
'PAYMENT_CONFIRMED'   // PIX/Boleto pago
'PAYMENT_RECEIVED'    // Pagamento recebido
'PAYMENT_OVERDUE'     // Boleto vencido
'PAYMENT_REFUNDED'    // Estorno
'PAYMENT_DELETED'     // Cancelado
```

**Fluxo**:
```
Asaas → Webhook → Validar Signature → Processar Evento → Atualizar AddonPurchase → Ativar Addon
```

**Testes**:
- [ ] `tests/Feature/Webhooks/AsaasWebhookTest.php`

### 1.2 Ativação Automática de Addons

**Objetivo**: Quando pagamento confirmar, ativar o addon automaticamente.

**Arquivos a modificar**:
- [ ] `app/Models/Central/AddonPurchase.php` - Adicionar método `activate()`
- [ ] `app/Services/Central/AddonService.php` - Método `activatePurchase()`
- [ ] `app/Listeners/Central/ActivateAddonOnPayment.php`

**Testes**:
- [ ] `tests/Feature/AddonActivationTest.php`

### 1.3 Job para Verificar Pagamentos Pendentes

**Objetivo**: Verificar periodicamente status de pagamentos PIX/Boleto pendentes.

**Arquivos a criar**:
- [ ] `app/Jobs/Central/CheckPendingPaymentsJob.php`
- [ ] `app/Console/Commands/CheckPendingPayments.php`

**Schedule** (em `routes/console.php`):
```php
Schedule::job(new CheckPendingPaymentsJob)->everyFiveMinutes();
```

---

## Fase 2: Integração Frontend

**Duração estimada**: 1-2 dias
**Prioridade**: ALTA

### 2.1 Integrar CheckoutPaymentSheet na Página de Addons

**Arquivo**: `resources/js/pages/tenant/admin/addons/index.tsx`

**Modificações**:
- [ ] Importar `CheckoutPaymentSheet`
- [ ] Substituir `CheckoutSheet` por `CheckoutPaymentSheet`
- [ ] Passar props de métodos de pagamento disponíveis

### 2.2 Integrar na Página de Bundles

**Arquivo**: `resources/js/pages/tenant/admin/billing/bundles.tsx`

**Modificações**:
- [ ] Importar `CheckoutPaymentSheet`
- [ ] Configurar para bundles

### 2.3 Hook usePaymentMethods

**Objetivo**: Hook para gerenciar métodos de pagamento disponíveis.

**Arquivo a criar**:
- [ ] `resources/js/hooks/billing/use-payment-methods.ts`

```typescript
export function usePaymentMethods() {
  // Retorna métodos disponíveis baseado em:
  // - Configuração do tenant
  // - Tipo de item (recurring vs one-time)
  // - País do cliente
}
```

---

## Fase 3: Notificações por Email

**Duração estimada**: 1-2 dias
**Prioridade**: ALTA

### 3.1 Email de PIX Gerado

**Arquivos a criar**:
- [ ] `app/Mail/Tenant/PixPaymentCreated.php`
- [ ] `resources/views/emails/tenant/pix-payment-created.blade.php`

**Conteúdo**:
- QR Code inline (base64)
- Código copia-e-cola
- Valor e descrição
- Tempo de expiração

### 3.2 Email de Boleto Gerado

**Arquivos a criar**:
- [ ] `app/Mail/Tenant/BoletoPaymentCreated.php`
- [ ] `resources/views/emails/tenant/boleto-payment-created.blade.php`

**Conteúdo**:
- Linha digitável
- Link para PDF
- Data de vencimento
- Valor

### 3.3 Email de Pagamento Confirmado

**Arquivos a criar**:
- [ ] `app/Mail/Tenant/PaymentConfirmed.php`
- [ ] `resources/views/emails/tenant/payment-confirmed.blade.php`

### 3.4 Email de Lembrete de Boleto

**Arquivos a criar**:
- [ ] `app/Mail/Tenant/BoletoReminder.php`
- [ ] `app/Jobs/Central/SendBoletoRemindersJob.php`

**Schedule**:
```php
// Enviar lembrete 2 dias antes do vencimento
Schedule::job(new SendBoletoRemindersJob)->dailyAt('09:00');
```

---

## Fase 4: Admin Dashboard de Pagamentos

**Duração estimada**: 2-3 dias
**Prioridade**: MÉDIA

### 4.1 Backend - PaymentController (Central)

**Arquivos a criar**:
- [ ] `app/Http/Controllers/Central/Admin/PaymentController.php`
- [ ] `app/Http/Resources/Central/PaymentAdminResource.php`

**Endpoints**:
```
GET  /admin/payments              # Listar todos os pagamentos
GET  /admin/payments/{id}         # Detalhes do pagamento
POST /admin/payments/{id}/refund  # Estornar pagamento
GET  /admin/payments/export       # Exportar CSV
```

### 4.2 Frontend - Página de Pagamentos

**Arquivos a criar**:
- [ ] `resources/js/pages/central/admin/payments/index.tsx`
- [ ] `resources/js/pages/central/admin/payments/show.tsx`

**Features**:
- DataTable com filtros (status, método, período)
- Busca por tenant/email
- Ações: ver detalhes, estornar
- Exportar para CSV/Excel

### 4.3 Sidebar Item

**Arquivo a modificar**:
- [ ] `resources/js/components/sidebar/central-sidebar-items.tsx`

---

## Fase 5: Metered Billing

**Duração estimada**: 3-4 dias
**Prioridade**: MÉDIA

### 5.1 Modelo de Uso

**Arquivos a criar**:
- [ ] `app/Models/Central/UsageRecord.php`
- [ ] `database/migrations/xxx_create_usage_records_table.php`

**Schema**:
```php
Schema::create('usage_records', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained();
    $table->foreignUuid('addon_subscription_id')->nullable();
    $table->string('metric'); // api_calls, storage_bytes, etc.
    $table->integer('quantity');
    $table->timestamp('recorded_at');
    $table->json('metadata')->nullable();
    $table->timestamps();
});
```

### 5.2 Serviço de Metered Billing

**Arquivos a criar/modificar**:
- [ ] `app/Services/Central/MeteredBillingService.php` (expandir existente)

**Métodos**:
```php
public function recordUsage(Tenant $tenant, string $metric, int $quantity): UsageRecord;
public function getUsageSummary(Tenant $tenant, string $metric, Carbon $from, Carbon $to): array;
public function calculateBillableAmount(Tenant $tenant, string $metric): int;
public function syncUsageToStripe(Tenant $tenant): void;
```

### 5.3 API de Uso

**Endpoints**:
```
POST /api/v1/usage          # Registrar uso (para integrações)
GET  /api/v1/usage/summary  # Resumo de uso atual
```

### 5.4 Dashboard de Uso (Tenant)

**Arquivo a criar**:
- [ ] `resources/js/pages/tenant/admin/addons/usage.tsx`

**Features**:
- Gráficos de uso por métrica
- Projeção de custos
- Alertas de limite

---

## Fase 6: Subscription Management ✅ CONCLUÍDA

**Duração estimada**: 2-3 dias
**Prioridade**: MÉDIA
**Status**: ✅ Concluída em 2025-12-10

### 6.1 Pausar/Retomar Assinatura ✅

**Arquivos modificados**:
- [x] `app/Services/Tenant/BillingService.php` - Métodos `pauseSubscription()`, `unpauseSubscription()`
- [x] `app/Http/Controllers/Tenant/Admin/BillingController.php` - Actions `pauseSubscription()`, `unpauseSubscription()`

**Endpoints**:
```
POST /admin/billing/subscription/pause
POST /admin/billing/subscription/unpause
POST /admin/billing/subscription/resume
```

### 6.2 Cancelamento com Período de Graça ✅

**Arquivos criados/modificados**:
- [x] `app/Services/Tenant/BillingService.php` - `cancelSubscription()`, `resumeSubscription()`
- [x] `app/Jobs/Central/HandleSubscriptionGracePeriodJob.php` - **NOVO**
- [x] `app/Events/Central/SubscriptionExpired.php` - **NOVO**
- [x] `routes/console.php` - Job agendado para meia-noite

**Fluxo**:
```
Cancelar → Marcar ends_at → Manter acesso até ends_at → Job detecta expiração → Downgrade para free → Dispara evento SubscriptionExpired
```

### 6.3 Upgrade/Downgrade de Plano ✅

**Arquivos modificados**:
- [x] `app/Services/Tenant/BillingService.php` - `changePlan()`
- [x] Proration handling via Stripe

### 6.4 UI de Gerenciamento ✅

**Arquivo criado**:
- [x] `resources/js/pages/tenant/admin/billing/subscription.tsx` - **NOVO**

**Features implementadas**:
- Status badges (active, trialing, paused, past_due, canceled)
- Pausar/retomar assinatura
- Retomar de período de graça
- Cancelar com opções (fim do período ou imediato)
- Trocar de plano com seletor visual

---

## Fase 7: Melhorias de UX ✅ CONCLUÍDA

**Duração estimada**: 1-2 dias
**Prioridade**: BAIXA
**Status**: ✅ Concluída em 2025-12-10

### 7.1 Persistência do Carrinho ✅

**Arquivo modificado**:
- [x] `resources/js/hooks/billing/use-checkout.tsx` - Já implementado!

**Implementação existente** (linhas 117-149, 188-196):
```typescript
// loadStoredCart() - Recupera do localStorage com expiração de 24h
const loadStoredCart = useCallback(() => {
    const stored = localStorage.getItem(CART_STORAGE_KEY);
    if (stored) {
        const parsed = JSON.parse(stored);
        if (parsed.expiry > Date.now()) {
            // Restaura items
        }
    }
}, []);

// Persist effect - Salva no localStorage
useEffect(() => {
    if (items.length > 0) {
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify({
            items,
            billingPeriod,
            expiry: Date.now() + CART_EXPIRY_MS, // 24 horas
        }));
    }
}, [items, billingPeriod]);
```

### 7.2 Animações de Transição ✅

**Arquivos modificados**:
- [x] `resources/js/components/shared/billing/checkout/checkout-payment-sheet.tsx`
- [x] `resources/js/pages/tenant/admin/billing/success.tsx`

**Implementação**:
- Transições animadas entre steps (cart → payment → async-payment → success)
- Estado de sucesso com checkmark animado e efeito ripple
- Animações staggered para conteúdo
- Usa `tw-animate-css` (Tailwind animations: `animate-in`, `fade-in`, `slide-in-from-*`, `zoom-in-*`)

**Features de animação**:
- Step transitions com slide/fade
- Success state com animated checkmark
- Ripple effect no ícone de sucesso
- Sparkle decorations na página de sucesso
- Staggered content animations

### 7.3 Histórico de Pagamentos ✅

**Arquivo criado**:
- [x] `resources/js/components/shared/billing/dashboard/payment-history.tsx` - **NOVO**

**Features**:
- 3 variantes de display: `card`, `table`, `timeline`
- Status badges (paid, open, failed, refunded, void)
- Payment method badges (card, pix, boleto)
- Download de invoice
- Empty state handling
- Suporte a `PaymentResource` e `InvoiceDetailResource`
- Paginação com "View All"

---

## Fase 8: Testes Adicionais

**Duração estimada**: 2 dias
**Prioridade**: BAIXA

### 8.1 Testes de Integração Asaas

**Arquivos a criar**:
- [ ] `tests/Feature/Integration/AsaasPixIntegrationTest.php`
- [ ] `tests/Feature/Integration/AsaasBoletoIntegrationTest.php`

**Requisitos**:
- Usar sandbox do Asaas
- Mock de webhooks

### 8.2 Testes de Webhook

**Arquivo a criar**:
- [ ] `tests/Feature/Webhooks/AsaasWebhookTest.php`

### 8.3 Testes E2E Completos

**Arquivo a modificar**:
- [ ] `tests/Browser/checkout-flow.spec.ts`

**Cenários adicionais**:
- Fluxo completo de PIX (gerar → pagar → confirmar)
- Fluxo de Boleto (gerar → simular pagamento)
- Expiração de PIX
- Cancelamento de pagamento

---

## Cronograma Sugerido

| Semana | Fase | Tarefas |
|--------|------|---------|
| 1 | Fase 1 | Webhooks Asaas, Ativação automática |
| 1 | Fase 2 | Integração frontend |
| 2 | Fase 3 | Emails de notificação |
| 2 | Fase 4 | Admin dashboard pagamentos |
| 3 | Fase 5 | Metered billing |
| 3 | Fase 6 | Subscription management |
| 4 | Fase 7 | Melhorias UX |
| 4 | Fase 8 | Testes adicionais |

---

## Checklist de Conclusão

### Fase 1 - Webhooks
- [ ] AsaasWebhookController criado
- [ ] Eventos de pagamento tratados
- [ ] Addons ativados automaticamente
- [ ] Job de verificação de pendentes
- [ ] Testes passando

### Fase 2 - Frontend
- [ ] CheckoutPaymentSheet integrado em addons
- [ ] CheckoutPaymentSheet integrado em bundles
- [ ] Hook usePaymentMethods criado

### Fase 3 - Emails
- [ ] Email PIX gerado
- [ ] Email Boleto gerado
- [ ] Email pagamento confirmado
- [ ] Email lembrete boleto
- [ ] Templates testados

### Fase 4 - Admin Dashboard
- [ ] PaymentController criado
- [ ] Página de listagem
- [ ] Página de detalhes
- [ ] Exportação CSV
- [ ] Filtros funcionando

### Fase 5 - Metered Billing
- [ ] UsageRecord model
- [ ] MeteredBillingService expandido
- [ ] API de uso
- [ ] Dashboard de uso

### Fase 6 - Subscription ✅
- [x] Pausar/retomar - `BillingService` e `BillingController`
- [x] Cancelamento graceful - `HandleSubscriptionGracePeriodJob`
- [x] Upgrade/downgrade - `changePlan()` com proration
- [x] UI de gerenciamento - `subscription.tsx`

### Fase 7 - UX ✅
- [x] Persistência carrinho - `use-checkout.tsx` (24h expiry)
- [x] Animações - `checkout-payment-sheet.tsx`, `success.tsx`
- [x] Histórico pagamentos - `payment-history.tsx`

### Fase 8 - Testes
- [ ] Integração Asaas sandbox
- [ ] Webhook tests
- [ ] E2E completos

---

## Comandos Úteis

```bash
# Rodar testes de billing
sail artisan test --filter=Billing
sail artisan test --filter=Payment
sail artisan test --filter=Checkout

# Rodar E2E
sail npm run test:e2e

# Verificar filas
sail artisan queue:monitor high,default

# Logs do Asaas (se configurado)
sail artisan telescope:clear && sail artisan horizon

# Stripe CLI (para testes locais)
stripe listen --forward-to http://app.test/stripe/webhook
```

---

## Referências

- [Asaas API Docs](https://docs.asaas.com/)
- [Stripe Webhooks](https://stripe.com/docs/webhooks)
- [Laravel Cashier](https://laravel.com/docs/billing)
- [docs/ADDONS.md](../ADDONS.md)
- [docs/SYSTEM-ARCHITECTURE.md](../SYSTEM-ARCHITECTURE.md)
