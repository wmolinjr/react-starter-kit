# 07 - Billing com Laravel Cashier

## Índice

- [Setup do Cashier](#setup-do-cashier)
- [Configurar Planos no Stripe](#configurar-planos-no-stripe)
- [BillingController](#billingcontroller)
- [Webhooks](#webhooks)
- [Páginas Inertia](#páginas-inertia)

---

## Setup do Cashier

### 1. Já instalado em 01-SETUP.md

```bash
composer require laravel/cashier
php artisan cashier:install
php artisan migrate
```

### 2. Tenant Model implementa Billable

**Já configurado em 03-MODELS.md:**

```php
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    use Billable;

    public function stripeCustomerName(): string
    {
        return $this->name;
    }

    public function stripeEmail(): string
    {
        return $this->owners()->first()?->email ?? 'noreply@example.com';
    }
}
```

---

## Configurar Planos no Stripe

### 1. Criar Produtos e Preços no Stripe Dashboard

**Acessar:** https://dashboard.stripe.com/test/products

**Criar 3 produtos:**

| Produto      | Price ID           | Preço   | Limites                                    |
|--------------|-------------------|---------|-------------------------------------------|
| Starter      | `price_starter`   | $9/mês  | 10 users, 50 projects, 1GB storage       |
| Professional | `price_pro`       | $29/mês | 50 users, unlimited projects, 10GB       |
| Enterprise   | `price_enterprise`| $99/mês | unlimited users, unlimited, 100GB        |

### 2. Configurar no `.env`

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Price IDs
STRIPE_PRICE_STARTER=price_1ABC...
STRIPE_PRICE_PRO=price_1DEF...
STRIPE_PRICE_ENTERPRISE=price_1GHI...
```

### 3. Helper de Planos

```php
// app/Helpers/billing_helpers.php

function billing_plans(): array
{
    return [
        'starter' => [
            'name' => 'Starter',
            'price_id' => config('services.stripe.prices.starter'),
            'price' => '$9',
            'interval' => 'month',
            'features' => [
                '10 team members',
                '50 projects',
                '1GB storage',
                'Email support',
            ],
            'limits' => [
                'max_users' => 10,
                'max_projects' => 50,
                'storage_mb' => 1000,
            ],
        ],
        'professional' => [
            'name' => 'Professional',
            'price_id' => config('services.stripe.prices.professional'),
            'price' => '$29',
            'interval' => 'month',
            'features' => [
                '50 team members',
                'Unlimited projects',
                '10GB storage',
                'Priority support',
                'Custom domains',
            ],
            'limits' => [
                'max_users' => 50,
                'max_projects' => null,
                'storage_mb' => 10000,
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'price_id' => config('services.stripe.prices.enterprise'),
            'price' => '$99',
            'interval' => 'month',
            'features' => [
                'Unlimited team members',
                'Unlimited projects',
                '100GB storage',
                '24/7 support',
                'Custom domains',
                'SSO',
                'SLA',
            ],
            'limits' => [
                'max_users' => null,
                'max_projects' => null,
                'storage_mb' => 100000,
            ],
        ],
    ];
}
```

---

## BillingController

```bash
php artisan make:controller BillingController
```

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BillingController extends Controller
{
    /**
     * Página de billing
     */
    public function index()
    {
        $tenant = current_tenant();

        $subscription = $tenant->subscription('default');

        return inertia('billing/index', [
            'plans' => billing_plans(),
            'subscription' => $subscription ? [
                'name' => $subscription->stripe_price,
                'status' => $subscription->stripe_status,
                'trial_ends_at' => $subscription->trial_ends_at,
                'ends_at' => $subscription->ends_at,
                'on_trial' => $subscription->onTrial(),
                'on_grace_period' => $subscription->onGracePeriod(),
                'canceled' => $subscription->canceled(),
            ] : null,
            'invoices' => $tenant->invoices()->map(fn ($invoice) => [
                'id' => $invoice->id,
                'date' => $invoice->date()->toFormattedDateString(),
                'total' => $invoice->total(),
                'download_url' => route('billing.invoice', $invoice->id),
            ]),
        ]);
    }

    /**
     * Iniciar checkout
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'plan' => 'required|in:starter,professional,enterprise',
        ]);

        $tenant = current_tenant();
        $plans = billing_plans();
        $priceId = $plans[$request->plan]['price_id'];

        // Criar ou atualizar subscription
        $checkout = $tenant->newSubscription('default', $priceId)
            ->trialDays(14)
            ->checkout([
                'success_url' => route('billing.success'),
                'cancel_url' => route('billing.index'),
            ]);

        return inertia('billing/checkout', [
            'sessionId' => $checkout->id,
        ]);
    }

    /**
     * Checkout success
     */
    public function success()
    {
        $tenant = current_tenant();

        // Atualizar limits baseado no plano
        $subscription = $tenant->subscription('default');

        if ($subscription) {
            $priceId = $subscription->stripe_price;
            $plan = collect(billing_plans())->first(fn ($p) => $p['price_id'] === $priceId);

            if ($plan) {
                $tenant->updateSetting('limits', $plan['limits']);
            }
        }

        return redirect()->route('billing.index')
            ->with('success', 'Assinatura ativada com sucesso!');
    }

    /**
     * Customer portal (gerenciar cartões, invoices, cancel)
     */
    public function portal()
    {
        $tenant = current_tenant();

        return $tenant->redirectToBillingPortal(route('billing.index'));
    }

    /**
     * Download invoice
     */
    public function invoice(string $invoiceId)
    {
        $tenant = current_tenant();

        return $tenant->downloadInvoice($invoiceId, [
            'vendor' => config('app.name'),
            'product' => 'Subscription',
        ]);
    }
}
```

---

## Webhooks

### 1. Configurar rota

```php
// routes/web.php (FORA do group de tenant)

use Laravel\Cashier\Http\Controllers\WebhookController;

Route::post(
    '/stripe/webhook',
    [WebhookController::class, 'handleWebhook']
);
```

### 2. Registrar webhook no Stripe

**Stripe Dashboard → Developers → Webhooks**

**URL:** `https://setor3.app/stripe/webhook`

**Events:**
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.payment_succeeded`
- `invoice.payment_failed`

### 3. Obter Webhook Secret

Copiar `whsec_...` e adicionar ao `.env`:

```env
STRIPE_WEBHOOK_SECRET=whsec_...
```

### 4. Custom Webhook Handler (opcional)

```php
// app/Providers/EventServiceProvider.php

use Laravel\Cashier\Events\WebhookReceived;

protected $listen = [
    WebhookReceived::class => [
        \App\Listeners\UpdateTenantLimits::class,
    ],
];
```

```bash
php artisan make:listener UpdateTenantLimits
```

```php
<?php

namespace App\Listeners;

use Laravel\Cashier\Events\WebhookReceived;

class UpdateTenantLimits
{
    public function handle(WebhookReceived $event): void
    {
        if ($event->payload['type'] === 'customer.subscription.updated') {
            $customerId = $event->payload['data']['object']['customer'];
            $priceId = $event->payload['data']['object']['items']['data'][0]['price']['id'];

            $tenant = \App\Models\Tenant::where('stripe_id', $customerId)->first();

            if ($tenant) {
                $plan = collect(billing_plans())->first(fn ($p) => $p['price_id'] === $priceId);

                if ($plan) {
                    $tenant->updateSetting('limits', $plan['limits']);
                }
            }
        }
    }
}
```

---

## Páginas Inertia

### Billing Index

```tsx
// resources/js/pages/billing/index.tsx

import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Check } from 'lucide-react';

interface Plan {
  name: string;
  price: string;
  interval: string;
  features: string[];
  price_id: string;
}

interface Props {
  plans: Record<string, Plan>;
  subscription: {
    status: string;
    on_trial: boolean;
    trial_ends_at: string | null;
  } | null;
  invoices: Array<{
    id: string;
    date: string;
    total: string;
    download_url: string;
  }>;
}

export default function BillingIndex({ plans, subscription, invoices }: Props) {
  const handleSubscribe = (planKey: string) => {
    router.post('/billing/checkout', { plan: planKey });
  };

  return (
    <AppLayout>
      <Head title="Billing" />

      <div className="py-12">
        <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
          <h1 className="text-3xl font-bold">Billing & Subscription</h1>

          {/* Current Plan */}
          {subscription && (
            <Card className="mt-6">
              <CardHeader>
                <CardTitle>Plano Atual</CardTitle>
                <CardDescription>
                  Status:{' '}
                  <Badge>{subscription.status}</Badge>
                  {subscription.on_trial && (
                    <span className="ml-2">
                      Trial até {subscription.trial_ends_at}
                    </span>
                  )}
                </CardDescription>
              </CardHeader>
              <CardContent>
                <Button asChild>
                  <Link href="/billing/portal">Gerenciar Assinatura</Link>
                </Button>
              </CardContent>
            </Card>
          )}

          {/* Available Plans */}
          <div className="mt-12">
            <h2 className="text-2xl font-bold">Planos Disponíveis</h2>

            <div className="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
              {Object.entries(plans).map(([key, plan]) => (
                <Card key={key} className="flex flex-col">
                  <CardHeader>
                    <CardTitle>{plan.name}</CardTitle>
                    <CardDescription>
                      <span className="text-4xl font-bold">{plan.price}</span>
                      <span className="text-muted-foreground">/{plan.interval}</span>
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="flex-1">
                    <ul className="space-y-2">
                      {plan.features.map((feature, i) => (
                        <li key={i} className="flex items-center gap-2">
                          <Check className="h-4 w-4 text-green-600" />
                          <span>{feature}</span>
                        </li>
                      ))}
                    </ul>

                    <Button
                      className="mt-6 w-full"
                      onClick={() => handleSubscribe(key)}
                      disabled={subscription?.status === 'active'}
                    >
                      {subscription ? 'Mudar Plano' : 'Assinar'}
                    </Button>
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>

          {/* Invoices */}
          {invoices.length > 0 && (
            <Card className="mt-12">
              <CardHeader>
                <CardTitle>Faturas</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  {invoices.map((invoice) => (
                    <div key={invoice.id} className="flex items-center justify-between border-b pb-2">
                      <div>
                        <p className="font-medium">{invoice.date}</p>
                        <p className="text-sm text-muted-foreground">{invoice.total}</p>
                      </div>
                      <Button asChild size="sm" variant="outline">
                        <a href={invoice.download_url} target="_blank">
                          Download
                        </a>
                      </Button>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
```

---

## Checklist

- [ ] Cashier instalado e configurado
- [ ] Tenant model implementa Billable
- [ ] Planos criados no Stripe
- [ ] Price IDs configurados no `.env`
- [ ] `BillingController` criado
- [ ] Webhooks configurados
- [ ] Página `billing/index.tsx` criada
- [ ] Teste: checkout funciona
- [ ] Teste: customer portal funciona
- [ ] Teste: webhooks atualizam limites

---

## Próximo Passo

➡️ **[08-FILE-STORAGE.md](./08-FILE-STORAGE.md)** - Armazenamento de Arquivos

---

**Versão:** 1.0
**Última atualização:** 2025-11-19
