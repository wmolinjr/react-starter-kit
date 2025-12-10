# Checkout & Billing Roadmap

> Plano de execução para completar o sistema de billing multi-pagamento.
>
> **Status**: Em andamento
> **Última atualização**: 2025-12-10

## Visão Geral

Este documento detalha o plano de implementação para o sistema completo de billing com suporte a múltiplos métodos de pagamento (Card, PIX, Boleto).

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

## Fase 6: Subscription Management

**Duração estimada**: 2-3 dias
**Prioridade**: MÉDIA

### 6.1 Pausar/Retomar Assinatura

**Arquivos a modificar**:
- [ ] `app/Services/Tenant/BillingService.php`
- [ ] `app/Http/Controllers/Tenant/Admin/SubscriptionController.php`

**Endpoints**:
```
POST /admin/subscription/pause
POST /admin/subscription/resume
```

### 6.2 Cancelamento com Período de Graça

**Arquivos a modificar**:
- [ ] `app/Services/Tenant/BillingService.php` - `cancelSubscription()`
- [ ] `app/Jobs/Central/HandleSubscriptionGracePeriodJob.php`

**Fluxo**:
```
Cancelar → Marcar ends_at → Manter acesso até ends_at → Downgrade para free
```

### 6.3 Upgrade/Downgrade de Plano

**Arquivos a modificar**:
- [ ] `app/Services/Tenant/BillingService.php` - `changePlan()`
- [ ] Proration handling

### 6.4 UI de Gerenciamento

**Arquivo a criar**:
- [ ] `resources/js/pages/tenant/admin/billing/subscription.tsx`

---

## Fase 7: Melhorias de UX

**Duração estimada**: 1-2 dias
**Prioridade**: BAIXA

### 7.1 Persistência do Carrinho

**Arquivo a modificar**:
- [ ] `resources/js/hooks/billing/use-checkout.tsx`

**Implementação**:
```typescript
// Salvar em localStorage
useEffect(() => {
  localStorage.setItem('checkout_cart', JSON.stringify(items));
}, [items]);

// Recuperar ao montar
useEffect(() => {
  const saved = localStorage.getItem('checkout_cart');
  if (saved) setItems(JSON.parse(saved));
}, []);
```

### 7.2 Animações de Transição

**Arquivo a modificar**:
- [ ] `resources/js/components/shared/billing/checkout/checkout-payment-sheet.tsx`

**Usar**: Framer Motion ou CSS transitions

### 7.3 Histórico de Tentativas

**Arquivo a criar**:
- [ ] `resources/js/components/shared/billing/payment-history.tsx`

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

### Fase 6 - Subscription
- [ ] Pausar/retomar
- [ ] Cancelamento graceful
- [ ] Upgrade/downgrade
- [ ] UI de gerenciamento

### Fase 7 - UX
- [ ] Persistência carrinho
- [ ] Animações
- [ ] Histórico tentativas

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
- [docs/ADDONS.md](./ADDONS.md)
- [docs/SYSTEM-ARCHITECTURE.md](./SYSTEM-ARCHITECTURE.md)
