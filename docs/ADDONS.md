# Sistema de Add-ons

Documentação técnica do sistema de add-ons para desenvolvedores.

## Arquitetura

### Single Source of Truth

| Layer | Purpose |
|-------|---------|
| `AddonSeeder.php` | Code-based source of truth for addon definitions |
| Database (addons table) | Runtime source of truth |
| `stripe:sync --addons` | Creates Stripe products/prices and stores IDs in DB |

O sistema suporta 5 modalidades de monetização:

| Modalidade | Billing Period | Uso |
|------------|---------------|-----|
| **Manual Overrides** | `manual` | Admin ajusta limites manualmente |
| **Subscription Items** | `monthly`/`yearly` | Cobrado via Stripe Subscription |
| **Metered Billing** | `metered` | Usage-based (storage, bandwidth) |
| **One-Time Purchase** | `one_time` | Compra única via Stripe Checkout |
| **Addons Table** | Qualquer | Registro local sem Stripe |

### Fluxo de Compra (E2E)

```
1. Tenant acessa /addons
2. Seleciona addon e clica "Add Monthly/Yearly"
3. Modal de compra abre com quantidade e billing period
4. Clica "Continue to Checkout"
5. Redireciona para Stripe Checkout (ad-hoc ou Price ID)
6. Cliente preenche cartão e paga
7. Stripe processa pagamento
8. Webhook customer.subscription.created é disparado
9. Handler cria AddonSubscription no banco
10. Cliente é redirecionado para /addons/success
11. Admin visualiza addon em /admin/addons
```

---

## Estrutura de Arquivos

### Backend

```
app/
├── Enums/
│   ├── AddonType.php          # storage, users, projects, feature, etc.
│   ├── AddonStatus.php        # pending, active, canceled, expired, failed
│   └── BillingPeriod.php      # monthly, yearly, one_time, metered, manual
├── Models/
│   └── Central/
│       ├── Addon.php              # Catálogo de addons (central DB)
│       ├── AddonSubscription.php  # Add-ons ativos do tenant
│       └── AddonPurchase.php      # Histórico de compras
├── Services/
│   ├── AddonService.php       # CRUD e sync de limites
│   ├── CheckoutService.php    # Stripe Checkout sessions
│   └── MeteredBillingService.php # Report usage to Stripe
├── Observers/
│   └── AddonSubscriptionObserver.php # Auto-sync em created/updated/deleted
├── Http/Controllers/
│   ├── Tenant/AddonController.php      # UI do tenant
│   ├── Admin/AddonManagementController.php # Admin panel
│   └── Billing/AddonWebhookController.php  # Stripe webhooks
├── Console/Commands/
│   ├── SyncAddons.php         # addons:sync
│   └── ReportMeteredUsage.php # billing:report-usage
└── Exceptions/Central/
    ├── AddonException.php
    └── AddonLimitExceededException.php

database/seeders/
└── AddonSeeder.php            # Single Source of Truth for addon catalog

routes/
├── tenant.php                 # Rotas /addons/*
├── admin.php                  # Rotas /admin/addons/*
└── webhooks.php               # POST /stripe/webhook
```

### Frontend

```
resources/js/
├── types/
│   └── addons.d.ts            # TypeScript interfaces
├── hooks/
│   ├── use-addons.ts          # Acesso aos dados de addons
│   └── use-purchase.ts        # Ações de compra/cancelamento
├── components/addons/
│   ├── addon-card.tsx         # Card do catálogo
│   ├── active-addon-card.tsx  # Card de addon ativo
│   ├── purchase-modal.tsx     # Modal de compra
│   ├── quantity-selector.tsx  # Seletor de quantidade
│   ├── billing-period-toggle.tsx # Toggle mensal/anual
│   └── usage-meter.tsx        # Barra de uso
└── pages/tenant/addons/
    ├── index.tsx              # Lista de addons
    ├── usage.tsx              # Dashboard de uso
    └── success.tsx            # Página de sucesso
```

---

## Configuração

### Variáveis de Ambiente

```env
# Stripe (Test Mode)
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

**Stripe Price IDs e Meter IDs são armazenados no banco de dados**, não em variáveis de ambiente. Use o comando `stripe:sync` para criá-los.

### Catálogo de Add-ons

O catálogo é definido em `database/seeders/AddonSeeder.php`:

```php
protected function getAddons(): array
{
    return [
        'storage_50gb' => [
            'name' => [
                'en' => 'Storage 50GB',
                'pt_BR' => 'Armazenamento 50GB',
            ],
            'description' => [
                'en' => 'Add 50GB of additional storage',
                'pt_BR' => 'Adicione 50GB de armazenamento extra',
            ],
            'type' => AddonType::STORAGE,
            'unit_value' => 50000, // MB
            'price_monthly' => 4900, // $49.00 em centavos
            'price_yearly' => 49000, // $490.00
            'available_for_plans' => ['starter', 'professional'],
            'min_quantity' => 1,
            'max_quantity' => 20,
            'features' => [
                'High-performance SSD storage',
                'Automatic backups included',
            ],
        ],
    ];
}
```

Para adicionar/modificar addons:

```bash
# 1. Edite database/seeders/AddonSeeder.php
# 2. Execute o seeder
sail artisan db:seed --class=AddonSeeder

# 3. Sincronize com Stripe (cria products/prices e salva IDs no DB)
sail artisan stripe:sync --addons
```

---

## Uso

### Hook useAddons

```tsx
import { useAddons } from '@/hooks/use-addons';

function MyComponent() {
    const {
        active,              // AddonSubscription[] - addons ativos
        catalog,             // AddonCatalogItem[] - catálogo disponível
        formattedMonthlyCost, // string - "$29.99"
        hasAddon,            // (slug: string) => boolean
        getQuantity,         // (slug: string) => number
        canPurchase,         // (slug: string, qty?: number) => boolean
    } = useAddons();

    if (hasAddon('storage_50gb')) {
        const qty = getQuantity('storage_50gb');
        // ...
    }
}
```

### Hook usePurchase

```tsx
import { usePurchase } from '@/hooks/use-purchase';

function PurchaseButton() {
    const { purchase, cancel, isPurchasing, error } = usePurchase();

    const handleBuy = () => {
        purchase('storage_50gb', 2, 'monthly');
    };

    return (
        <Button onClick={handleBuy} disabled={isPurchasing}>
            {isPurchasing ? 'Processing...' : 'Buy Now'}
        </Button>
    );
}
```

### AddonService (Backend)

```php
use App\Services\Central\AddonService;

$service = app(AddonService::class);

// Obter addon do catálogo
$addon = $service->getAddon('storage_50gb');

// Comprar addon
$tenantAddon = $service->purchase($tenant, 'storage_50gb', 2, BillingPeriod::MONTHLY);

// Cancelar
$service->cancel($tenantAddon, 'User requested');

// Atualizar quantidade
$service->updateQuantity($tenantAddon, 5);

// Reativar
$service->reactivate($tenantAddon);

// Sincronizar limites do tenant
$service->syncTenantLimits($tenant);
```

---

## Stripe CLI (Desenvolvimento)

### Opção 1: Docker Compose

```bash
# Subir containers
docker compose up -d

# Iniciar Stripe CLI
docker compose --profile stripe up -d stripe-cli

# Ver webhook secret
docker compose logs stripe-cli | grep "signing secret"
# Copie o whsec_xxx para .env STRIPE_WEBHOOK_SECRET
```

### Opção 2: Stripe CLI Local

```bash
# Instalar
brew install stripe/stripe-cli/stripe  # macOS
# ou https://stripe.com/docs/stripe-cli

# Login
stripe login

# Iniciar listener
stripe listen --forward-to localhost/stripe/webhook

# Copie o webhook signing secret para .env
```

### Testar Webhooks

```bash
# Subscription criada
stripe trigger customer.subscription.created

# Checkout completo
stripe trigger checkout.session.completed

# Pagamento de invoice
stripe trigger invoice.payment_succeeded

# Subscription atualizada
stripe trigger customer.subscription.updated
```

---

## Comandos Artisan

```bash
# Seed addon catalog
sail artisan db:seed --class=AddonSeeder

# Sync com Stripe (cria products/prices, salva IDs no DB)
sail artisan stripe:sync --addons

# Sincronizar limites de todos os tenants
sail artisan addons:sync

# Sincronizar tenant específico
sail artisan addons:sync --tenant=1

# Reportar uso metered para Stripe
sail artisan billing:report-usage

# Reportar para tenant específico
sail artisan billing:report-usage --tenant=1
```

---

## Rotas

### Tenant Routes (`/addons`)

| Method | URI | Controller | Name |
|--------|-----|------------|------|
| GET | `/addons` | AddonController@index | tenant.addons.index |
| GET | `/addons/usage` | AddonController@usage | tenant.addons.usage |
| GET | `/addons/success` | AddonController@success | tenant.addons.success |
| POST | `/addons/purchase` | AddonController@purchase | tenant.addons.purchase |
| POST | `/addons/{addon}/cancel` | AddonController@cancel | tenant.addons.cancel |
| PATCH | `/addons/{addon}` | AddonController@update | tenant.addons.update |

### Admin Routes (`/admin/addons`)

| Method | URI | Controller | Name |
|--------|-----|------------|------|
| GET | `/admin/addons` | AddonManagementController@index | admin.addons.index |
| GET | `/admin/addons/revenue` | AddonManagementController@revenue | admin.addons.revenue |
| POST | `/admin/addons/tenant/{tenant}/grant` | AddonManagementController@grantAddon | admin.addons.grant |
| POST | `/admin/addons/{addon}/revoke` | AddonManagementController@revokeAddon | admin.addons.revoke |

### Webhook Route

| Method | URI | Controller |
|--------|-----|------------|
| POST | `/stripe/webhook` | AddonWebhookController@handleWebhook |

---

## Webhooks Suportados

| Event | Handler | Descrição |
|-------|---------|-----------|
| `checkout.session.completed` | handleCheckoutSessionCompleted | Processa one-time purchases |
| `customer.subscription.created` | handleCustomerSubscriptionCreated | Cria addon para subscription |
| `customer.subscription.updated` | handleCustomerSubscriptionUpdated | Sync limites |
| `customer.subscription.deleted` | handleCustomerSubscriptionDeleted | Cancela addons |
| `invoice.payment_succeeded` | handleInvoicePaymentSucceeded | Reset metered usage |
| `invoice.payment_failed` | handleInvoicePaymentFailed | Log warning |
| `charge.refunded` | handleChargeRefunded | Processa refunds |

---

## Deploy em Produção

### 1. Configurar Stripe (Produção)

```env
# .env (produção)
STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx  # Obtido no passo 3
```

### 2. Seed e Sync Addons

```bash
# 1. Seed addon catalog no banco
sail artisan db:seed --class=AddonSeeder

# 2. Criar products/prices no Stripe e salvar IDs no DB
sail artisan stripe:sync --addons --locale=en
```

O comando `stripe:sync` irá:
- Criar Products no Stripe para cada addon
- Criar Prices para cada billing period (monthly, yearly, one_time, metered)
- Criar Meters para metered billing
- Salvar todos os IDs automaticamente na tabela `addons`

### 3. Configurar Webhook Endpoint

1. Acesse [Stripe Dashboard → Webhooks](https://dashboard.stripe.com/webhooks)
2. Clique "Add endpoint"
3. Configure:
   - **Endpoint URL**: `https://seu-dominio.com/stripe/webhook`
   - **Events to send**:
     - `checkout.session.completed`
     - `customer.subscription.created`
     - `customer.subscription.updated`
     - `customer.subscription.deleted`
     - `invoice.payment_succeeded`
     - `invoice.payment_failed`
     - `charge.refunded`
4. Copie o "Signing secret" para `STRIPE_WEBHOOK_SECRET`

### 4. Verificar Webhook Route

Certifique-se que a rota está excluída do CSRF:

```php
// bootstrap/app.php ou app/Http/Middleware/VerifyCsrfToken.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'stripe/*',
    ]);
})
```

### 5. Testar em Produção

```bash
# Enviar webhook de teste do Stripe Dashboard
# Stripe Dashboard → Webhooks → seu endpoint → "Send test webhook"

# Verificar logs
tail -f storage/logs/laravel.log | grep -i stripe
```

### 6. Monitoramento

Configure alertas para:
- Falhas de webhook (Stripe Dashboard → Webhooks → Failed events)
- Erros no Laravel (Sentry, Bugsnag, etc.)
- Métricas de billing (Revenue, MRR, Churn)

### Checklist de Produção

- [ ] Stripe keys de produção configuradas
- [ ] Webhook endpoint criado no Stripe Dashboard
- [ ] STRIPE_WEBHOOK_SECRET configurado
- [ ] Rota `/stripe/webhook` excluída do CSRF
- [ ] `db:seed --class=AddonSeeder` executado
- [ ] `stripe:sync --addons` executado (cria products/prices no Stripe)
- [ ] SSL/HTTPS ativo (obrigatório para webhooks)
- [ ] Logs e monitoramento configurados
- [ ] Backup de banco de dados automático

---

## Modelo de Dados

### Addon (Catálogo)

```php
Schema::create('addons', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->json('name');                    // Translations
    $table->json('description')->nullable(); // Translations
    $table->string('type');                  // AddonType enum
    $table->boolean('active')->default(true);
    $table->integer('unit_value')->nullable();
    $table->string('unit_label')->nullable();
    $table->integer('min_quantity')->default(1);
    $table->integer('max_quantity')->nullable();
    $table->boolean('stackable')->default(true);
    $table->integer('price_monthly')->nullable();
    $table->integer('price_yearly')->nullable();
    $table->integer('price_one_time')->nullable();
    $table->integer('price_metered')->nullable();
    $table->integer('free_tier')->nullable();
    $table->integer('validity_months')->nullable();
    // Stripe IDs (populated by stripe:sync command)
    $table->string('stripe_product_id')->nullable();
    $table->string('stripe_price_monthly_id')->nullable();
    $table->string('stripe_price_yearly_id')->nullable();
    $table->string('stripe_price_one_time_id')->nullable();
    $table->string('stripe_price_metered_id')->nullable();
    $table->string('stripe_meter_id')->nullable();
    $table->json('features')->nullable();
    $table->string('badge')->nullable();
    $table->json('metadata')->nullable();
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

### AddonSubscription

```php
Schema::create('addon_subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('addon_slug');
    $table->string('addon_type');
    $table->string('name');
    $table->text('description')->nullable();
    $table->integer('quantity')->default(1);
    $table->integer('price')->default(0); // centavos
    $table->string('currency', 3)->default('USD');
    $table->string('billing_period');
    $table->string('status')->default('pending');
    $table->string('stripe_subscription_id')->nullable();
    $table->string('stripe_subscription_item_id')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('canceled_at')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### AddonPurchase

```php
Schema::create('addon_purchases', function (Blueprint $table) {
    $table->id();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('addon_slug');
    $table->string('addon_type');
    $table->integer('quantity')->default(1);
    $table->integer('amount_paid')->default(0);
    $table->string('currency', 3)->default('USD');
    $table->string('billing_period');
    $table->string('status')->default('pending');
    $table->string('stripe_checkout_session_id')->nullable();
    $table->string('stripe_payment_intent_id')->nullable();
    $table->timestamp('valid_until')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('refunded_at')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
});
```

---

## Troubleshooting

### Webhooks não chegam

1. Verificar se `laravel.test` está em `config/tenancy.php` → `central_domains`
2. Verificar se Stripe CLI está rodando: `docker compose logs stripe-cli`
3. Verificar `STRIPE_WEBHOOK_SECRET` no `.env`
4. Verificar se rota está excluída do CSRF
5. Testar endpoint: `curl -X POST https://seu-dominio.com/stripe/webhook`

### Erro "stripe_id column not found"

A tabela `tenants` precisa ter colunas do Cashier:

```bash
sail artisan migrate
```

Ou adicionar manualmente:

```php
$table->string('stripe_id')->nullable()->index();
$table->string('pm_type')->nullable();
$table->string('pm_last_four', 4)->nullable();
$table->timestamp('trial_ends_at')->nullable();
```

### Addon não aparece no catálogo

1. Verificar se o addon está no `AddonSeeder.php`
2. Executar: `sail artisan db:seed --class=AddonSeeder`
3. Verificar se o plano do tenant está em `available_for_plans`

### Limites não sincronizam

Executar manualmente:
```bash
sail artisan addons:sync --tenant={id}
```

### Redirect após pagamento vai para domínio errado

Verificar se `CheckoutService.php` usa `tenant_url()`:

```php
'success_url' => tenant_url('/addons/success?session_id={CHECKOUT_SESSION_ID}'),
'cancel_url' => tenant_url('/addons'),
```

### Stripe IDs não estão no banco

Executar sync com Stripe:
```bash
sail artisan stripe:sync --addons
```

---

## Testes

```bash
# Todos os testes de addon (88 testes)
sail artisan test --filter=Addon

# Testes específicos
sail artisan test --filter=AddonSubscriptionTest
sail artisan test --filter=AddonServiceTest
sail artisan test --filter=AddonPurchaseFlowTest
sail artisan test --filter=CheckoutServiceTest
```

### Testes E2E com Playwright

```bash
# Iniciar Stripe CLI
stripe listen --forward-to localhost/stripe/webhook

# Em outro terminal, rodar testes
npm run test:e2e
```

**Cartão de teste**: `4242 4242 4242 4242` (qualquer data futura e CVC)
