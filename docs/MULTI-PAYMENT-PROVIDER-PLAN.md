# Multi-Payment Provider Architecture

> **Status**: Planning
> **Effort**: 2-3 semanas
> **Priority**: Alta para mercado brasileiro

## Decisão Arquitetural

**Abordagem**: Provider-agnostic desde o início (sem Laravel Cashier)

**Justificativa**:
- Sistema em desenvolvimento, sem dados legados
- Cashier acopla fortemente ao Stripe
- Arquitetura limpa permite múltiplos providers nativamente
- Menor complexidade (sem camada de compatibilidade)

### Comparação: Cashier vs Provider-Agnostic

| Aspecto | Laravel Cashier | Nossa Abordagem |
|---------|-----------------|-----------------|
| **Providers** | Apenas Stripe | Qualquer provider |
| **Customer ID** | `stripe_id` único | `provider_ids` JSON (múltiplos) |
| **Subscription** | Acoplado ao Stripe | Provider-agnostic |
| **Payment Methods** | Via Stripe | Tabela própria por provider |
| **PIX/Boleto** | Só via Stripe (taxas altas) | Qualquer provider (Asaas, próprio) |
| **Flexibilidade** | Baixa | Alta |
| **Complexidade** | Escondida no pacote | Explícita e controlada |
| **Testes** | Mock difícil | Interface mockável |
| **Vendor lock-in** | Alto (Stripe) | Nenhum |

## Objetivo

Permitir múltiplos provedores de pagamento coexistindo:

### Fase 1 (MVP)
- **Stripe**: Cartões internacionais, Apple Pay, Google Pay, PIX (via Stripe), Boleto (via Stripe)

### Fase 2 (Providers Brasileiros)
- **Asaas**: PIX, Boleto, Cartões (taxas menores para Brasil)
- **PagSeguro**: PIX, Boleto, Cartões
- **MercadoPago**: PIX, Boleto, Cartões

### Fase 3 (Futuro - Próprio)
> Requer contrato bancário direto e maior complexidade operacional

- **PIX Próprio**: QR Code gerado internamente, confirmação via webhook bancário
- **Boleto Próprio**: PDF gerado internamente, confirmação via arquivo retorno CNAB

---

## Arquitetura Proposta

### Visão Geral

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         PAYMENT GATEWAY ARCHITECTURE                         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────┐     ┌──────────────────┐     ┌─────────────────────────┐  │
│  │   Customer  │────▶│ PaymentGateway   │────▶│ PaymentGatewayFactory   │  │
│  │   Portal    │     │    Manager       │     │                         │  │
│  └─────────────┘     └──────────────────┘     └───────────┬─────────────┘  │
│                              │                            │                 │
│                              ▼                            ▼                 │
│                     ┌────────────────┐          ┌─────────────────┐        │
│                     │ PaymentMethod  │          │    Gateways     │        │
│                     │    (Model)     │          ├─────────────────┤        │
│                     └────────────────┘          │ StripeGateway   │ ← MVP  │
│                                                 │ AsaasGateway    │ ← Fase 2│
│                                                 │ PagSeguroGateway│ ← Fase 2│
│                                                 │ MercadoPagoGw   │ ← Fase 2│
│                                                 │ PixOwnGateway   │ ← Futuro│
│                                                 │ BoletoOwnGateway│ ← Futuro│
│                                                 └─────────────────┘        │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Fluxo de Pagamento

```
┌──────────────────────────────────────────────────────────────────────────┐
│                           PAYMENT FLOW                                    │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  1. Customer seleciona método ──▶ 2. Factory cria Gateway apropriado    │
│                                                                          │
│  3. Gateway processa pagamento:                                          │
│     ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                   │
│     │   STRIPE    │  │    PIX      │  │   BOLETO    │                   │
│     ├─────────────┤  ├─────────────┤  ├─────────────┤                   │
│     │ Checkout    │  │ Gera QR     │  │ Gera PDF    │                   │
│     │ Session     │  │ Code + Copia│  │ + Linha     │                   │
│     │             │  │ e Cola      │  │ Digitável   │                   │
│     └──────┬──────┘  └──────┬──────┘  └──────┬──────┘                   │
│            │                │                │                           │
│            ▼                ▼                ▼                           │
│     4. Webhook/Callback confirma pagamento                               │
│            │                │                │                           │
│            └────────────────┴────────────────┘                           │
│                             │                                            │
│                             ▼                                            │
│     5. PaymentConfirmed Event ──▶ Ativa subscription/addon              │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### Nova Tabela: payment_methods

```php
Schema::create('payment_methods', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();

    // Provider info
    $table->string('provider'); // stripe, pix, boleto, asaas
    $table->string('provider_id')->nullable(); // ID no provider externo

    // Tipo do método
    $table->string('type'); // card, pix, boleto, bank_slip

    // Dados do método (criptografados)
    $table->json('details')->nullable(); // brand, last4, bank, agency, etc.

    // Status
    $table->boolean('is_default')->default(false);
    $table->boolean('is_verified')->default(false);
    $table->timestamp('verified_at')->nullable();
    $table->timestamp('expires_at')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->index(['customer_id', 'provider']);
    $table->index(['customer_id', 'is_default']);
});
```

### Nova Tabela: payments

```php
Schema::create('payments', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();

    // Provider info
    $table->string('provider'); // stripe, pix, boleto
    $table->string('provider_payment_id')->nullable();

    // Valores
    $table->integer('amount'); // em centavos
    $table->string('currency', 3)->default('BRL');
    $table->integer('fee')->default(0); // taxa do provider
    $table->integer('net_amount')->storedAs('amount - fee');

    // Status
    $table->string('status'); // pending, processing, paid, failed, refunded, expired
    $table->timestamp('paid_at')->nullable();
    $table->timestamp('expires_at')->nullable();

    // Referência
    $table->string('payable_type'); // subscription, addon_purchase, invoice
    $table->uuid('payable_id');

    // Dados específicos do provider
    $table->json('provider_data')->nullable(); // QR code, linha digitável, etc.
    $table->json('metadata')->nullable();

    // Falhas
    $table->string('failure_code')->nullable();
    $table->text('failure_message')->nullable();

    $table->timestamps();

    $table->index(['customer_id', 'status']);
    $table->index(['provider', 'provider_payment_id']);
    $table->index(['payable_type', 'payable_id']);
    $table->index('status');
});
```

### Nova Tabela: subscriptions (substituindo Cashier)

```php
Schema::create('subscriptions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();

    // Provider info
    $table->string('provider'); // stripe, asaas, pagseguro, mercadopago
    $table->string('provider_subscription_id')->nullable();
    $table->string('provider_customer_id')->nullable();
    $table->string('provider_price_id')->nullable();

    // Subscription details
    $table->string('type')->default('default'); // default, addon, etc.
    $table->string('status'); // active, canceled, past_due, trialing, paused
    $table->integer('quantity')->default(1);

    // Billing cycle
    $table->string('billing_period'); // monthly, yearly
    $table->integer('amount'); // em centavos
    $table->string('currency', 3)->default('BRL');

    // Dates
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('current_period_start')->nullable();
    $table->timestamp('current_period_end')->nullable();
    $table->timestamp('canceled_at')->nullable();
    $table->timestamp('ends_at')->nullable();

    // Metadata
    $table->json('provider_data')->nullable();
    $table->json('metadata')->nullable();

    $table->timestamps();

    $table->index(['customer_id', 'type']);
    $table->index(['provider', 'provider_subscription_id']);
    $table->index('status');
});
```

### Nova Tabela: subscription_items

```php
Schema::create('subscription_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('subscription_id')->constrained()->cascadeOnDelete();

    $table->string('provider_item_id')->nullable();
    $table->string('provider_price_id');
    $table->string('provider_product_id')->nullable();
    $table->integer('quantity')->default(1);

    $table->timestamps();

    $table->index(['subscription_id', 'provider_price_id']);
});
```

### Modificar: customers (remover Cashier)

```php
// REMOVER colunas do Cashier:
// - stripe_id
// - pm_type
// - pm_last_four
// - trial_ends_at (mover para subscription)

// ADICIONAR colunas provider-agnostic:
Schema::table('customers', function (Blueprint $table) {
    // IDs em cada provider (permite múltiplos simultâneos)
    $table->json('provider_ids')->nullable(); // {"stripe": "cus_xxx", "asaas": "cus_yyy"}

    // Método de pagamento padrão
    $table->foreignUuid('default_payment_method_id')->nullable();
});
```

### Customer Model (sem Billable trait)

```php
<?php

namespace App\Models\Central;

use App\Traits\HasPaymentMethods;
use App\Traits\HasSubscriptions;

class Customer extends Authenticatable
{
    // REMOVER: use Billable;

    use HasPaymentMethods;  // Nossa implementação
    use HasSubscriptions;   // Nossa implementação

    protected $casts = [
        'provider_ids' => 'array',
        'billing_address' => 'array',
    ];

    // Relationships
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function defaultPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'default_payment_method_id');
    }

    // Provider IDs
    public function getProviderCustomerId(string $provider): ?string
    {
        return $this->provider_ids[$provider] ?? null;
    }

    public function setProviderCustomerId(string $provider, string $id): void
    {
        $this->update([
            'provider_ids' => array_merge($this->provider_ids ?? [], [$provider => $id]),
        ]);
    }

    // Subscription helpers
    public function subscription(string $type = 'default'): ?Subscription
    {
        return $this->subscriptions()
            ->where('type', $type)
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->first();
    }

    public function subscribed(string $type = 'default'): bool
    {
        return $this->subscription($type)?->isActive() ?? false;
    }

    public function onTrial(string $type = 'default'): bool
    {
        return $this->subscription($type)?->onTrial() ?? false;
    }
}
```

---

## Interfaces e Contracts

### PaymentGatewayInterface

```php
<?php

namespace App\Contracts\Payment;

use App\Models\Central\Customer;
use App\Models\Central\Payment;
use App\DTOs\Payment\ChargeResult;
use App\DTOs\Payment\RefundResult;
use App\DTOs\Payment\PaymentMethodData;

interface PaymentGatewayInterface
{
    /**
     * Identificador único do gateway
     */
    public function getIdentifier(): string;

    /**
     * Nome para exibição
     */
    public function getDisplayName(): string;

    /**
     * Tipos de pagamento suportados
     * @return array<string> ['card', 'pix', 'boleto', 'bank_transfer']
     */
    public function getSupportedTypes(): array;

    /**
     * Moedas suportadas
     * @return array<string> ['BRL', 'USD']
     */
    public function getSupportedCurrencies(): array;

    /**
     * Gateway está disponível para uso?
     */
    public function isAvailable(): bool;

    /**
     * Criar cobrança
     */
    public function charge(Customer $customer, int $amount, array $options = []): ChargeResult;

    /**
     * Processar reembolso
     */
    public function refund(Payment $payment, ?int $amount = null): RefundResult;

    /**
     * Verificar status de pagamento
     */
    public function checkStatus(Payment $payment): string;

    /**
     * Processar webhook do provider
     */
    public function handleWebhook(array $payload, array $headers): void;

    /**
     * Validar assinatura do webhook
     */
    public function validateWebhookSignature(string $payload, string $signature): bool;
}
```

### SubscriptionGatewayInterface

```php
<?php

namespace App\Contracts\Payment;

use App\Models\Central\Customer;
use App\Models\Central\Subscription;
use App\DTOs\Payment\SubscriptionResult;

interface SubscriptionGatewayInterface extends PaymentGatewayInterface
{
    /**
     * Criar assinatura
     */
    public function createSubscription(
        Customer $customer,
        string $priceId,
        array $options = []
    ): SubscriptionResult;

    /**
     * Cancelar assinatura
     */
    public function cancelSubscription(
        Subscription $subscription,
        bool $immediately = false
    ): SubscriptionResult;

    /**
     * Atualizar assinatura (upgrade/downgrade)
     */
    public function updateSubscription(
        Subscription $subscription,
        string $newPriceId,
        array $options = []
    ): SubscriptionResult;

    /**
     * Pausar assinatura
     */
    public function pauseSubscription(Subscription $subscription): SubscriptionResult;

    /**
     * Retomar assinatura pausada
     */
    public function resumeSubscription(Subscription $subscription): SubscriptionResult;
}
```

### PaymentMethodGatewayInterface

```php
<?php

namespace App\Contracts\Payment;

use App\Models\Central\Customer;
use App\Models\Central\PaymentMethod;
use App\DTOs\Payment\PaymentMethodResult;
use App\DTOs\Payment\SetupIntentResult;

interface PaymentMethodGatewayInterface
{
    /**
     * Criar setup intent para adicionar método
     */
    public function createSetupIntent(Customer $customer): SetupIntentResult;

    /**
     * Adicionar método de pagamento
     */
    public function attachPaymentMethod(
        Customer $customer,
        string $providerMethodId
    ): PaymentMethodResult;

    /**
     * Remover método de pagamento
     */
    public function detachPaymentMethod(PaymentMethod $paymentMethod): bool;

    /**
     * Definir método como padrão
     */
    public function setDefaultPaymentMethod(
        Customer $customer,
        PaymentMethod $paymentMethod
    ): bool;

    /**
     * Listar métodos do customer no provider
     */
    public function listPaymentMethods(Customer $customer): array;
}
```

---

## Gateway Implementations

### StripeGateway

```php
<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Payment\SubscriptionGatewayInterface;
use App\Contracts\Payment\PaymentMethodGatewayInterface;
use App\Models\Central\Customer;
use App\Models\Central\Payment;
use App\DTOs\Payment\ChargeResult;
use Stripe\StripeClient;

class StripeGateway implements
    PaymentGatewayInterface,
    SubscriptionGatewayInterface,
    PaymentMethodGatewayInterface
{
    protected StripeClient $client;

    public function __construct()
    {
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    public function getIdentifier(): string
    {
        return 'stripe';
    }

    public function getDisplayName(): string
    {
        return 'Stripe';
    }

    public function getSupportedTypes(): array
    {
        return ['card', 'pix', 'boleto']; // Stripe Brazil suporta
    }

    public function getSupportedCurrencies(): array
    {
        return ['BRL', 'USD', 'EUR'];
    }

    public function isAvailable(): bool
    {
        return !empty(config('services.stripe.secret'));
    }

    public function charge(Customer $customer, int $amount, array $options = []): ChargeResult
    {
        $this->ensureStripeCustomer($customer);

        $paymentIntent = $this->client->paymentIntents->create([
            'customer' => $customer->stripe_id,
            'amount' => $amount,
            'currency' => $options['currency'] ?? 'brl',
            'payment_method' => $options['payment_method_id'] ?? null,
            'payment_method_types' => $options['types'] ?? ['card'],
            'confirm' => $options['confirm'] ?? false,
            'metadata' => $options['metadata'] ?? [],
        ]);

        return new ChargeResult(
            success: in_array($paymentIntent->status, ['succeeded', 'requires_capture']),
            providerPaymentId: $paymentIntent->id,
            status: $this->mapStripeStatus($paymentIntent->status),
            providerData: $paymentIntent->toArray(),
        );
    }

    // ... implementar demais métodos
}
```

### AsaasGateway (Provider Brasileiro)

```php
<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Payment\SubscriptionGatewayInterface;
use App\Models\Central\Customer;
use App\Models\Central\Payment;
use App\Models\Central\Subscription;
use App\DTOs\Payment\ChargeResult;
use App\DTOs\Payment\SubscriptionResult;
use Illuminate\Support\Facades\Http;

class AsaasGateway implements PaymentGatewayInterface, SubscriptionGatewayInterface
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.asaas.environment') === 'production'
            ? 'https://api.asaas.com/v3'
            : 'https://sandbox.asaas.com/api/v3';
        $this->apiKey = config('services.asaas.api_key');
    }

    public function getIdentifier(): string
    {
        return 'asaas';
    }

    public function getDisplayName(): string
    {
        return 'Asaas';
    }

    public function getSupportedTypes(): array
    {
        return ['card', 'pix', 'boleto'];
    }

    public function getSupportedCurrencies(): array
    {
        return ['BRL'];
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Ensure customer exists in Asaas
     */
    protected function ensureAsaasCustomer(Customer $customer): string
    {
        // Check if customer already has Asaas ID
        $asaasId = $customer->provider_ids['asaas'] ?? null;

        if ($asaasId) {
            return $asaasId;
        }

        // Create customer in Asaas
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/customers", [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'cpfCnpj' => $customer->billing_address['tax_id'] ?? null,
                'postalCode' => $customer->billing_address['postal_code'] ?? null,
                'address' => $customer->billing_address['line1'] ?? null,
                'addressNumber' => $customer->billing_address['number'] ?? null,
                'province' => $customer->billing_address['state'] ?? null,
                'externalReference' => $customer->id,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to create Asaas customer: ' . $response->body());
        }

        $asaasId = $response->json('id');

        // Store Asaas ID
        $customer->update([
            'provider_ids' => array_merge($customer->provider_ids ?? [], ['asaas' => $asaasId]),
        ]);

        return $asaasId;
    }

    /**
     * Create PIX charge
     */
    public function chargeWithPix(Customer $customer, int $amount, array $options = []): ChargeResult
    {
        $asaasCustomerId = $this->ensureAsaasCustomer($customer);

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/payments", [
                'customer' => $asaasCustomerId,
                'billingType' => 'PIX',
                'value' => $amount / 100,
                'dueDate' => now()->addDays(1)->toDateString(),
                'description' => $options['description'] ?? 'Pagamento',
                'externalReference' => $options['reference'] ?? null,
            ]);

        if (!$response->successful()) {
            return new ChargeResult(
                success: false,
                status: 'failed',
                failureMessage: $response->json('errors.0.description') ?? 'Unknown error',
            );
        }

        $payment = $response->json();

        // Get PIX QR Code
        $pixResponse = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/payments/{$payment['id']}/pixQrCode");

        return new ChargeResult(
            success: true,
            providerPaymentId: $payment['id'],
            status: $this->mapAsaasStatus($payment['status']),
            providerData: [
                'pix_copy_paste' => $pixResponse->json('payload'),
                'qr_code_base64' => $pixResponse->json('encodedImage'),
                'expires_at' => $pixResponse->json('expirationDate'),
            ],
        );
    }

    /**
     * Create Boleto charge
     */
    public function chargeWithBoleto(Customer $customer, int $amount, array $options = []): ChargeResult
    {
        $asaasCustomerId = $this->ensureAsaasCustomer($customer);

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/payments", [
                'customer' => $asaasCustomerId,
                'billingType' => 'BOLETO',
                'value' => $amount / 100,
                'dueDate' => $options['due_date'] ?? now()->addDays(3)->toDateString(),
                'description' => $options['description'] ?? 'Pagamento',
                'externalReference' => $options['reference'] ?? null,
                'fine' => ['value' => $options['fine'] ?? 2], // 2% multa
                'interest' => ['value' => $options['interest'] ?? 1], // 1% juros/mês
            ]);

        if (!$response->successful()) {
            return new ChargeResult(
                success: false,
                status: 'failed',
                failureMessage: $response->json('errors.0.description') ?? 'Unknown error',
            );
        }

        $payment = $response->json();

        return new ChargeResult(
            success: true,
            providerPaymentId: $payment['id'],
            status: $this->mapAsaasStatus($payment['status']),
            providerData: [
                'linha_digitavel' => $payment['identificationField'],
                'codigo_barras' => $payment['barCode'],
                'nosso_numero' => $payment['nossoNumero'],
                'vencimento' => $payment['dueDate'],
                'pdf_url' => $payment['bankSlipUrl'],
            ],
        );
    }

    /**
     * Create subscription
     */
    public function createSubscription(
        Customer $customer,
        string $priceId,
        array $options = []
    ): SubscriptionResult {
        $asaasCustomerId = $this->ensureAsaasCustomer($customer);

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/subscriptions", [
                'customer' => $asaasCustomerId,
                'billingType' => $options['billing_type'] ?? 'CREDIT_CARD',
                'value' => $options['amount'] / 100,
                'cycle' => $options['interval'] === 'year' ? 'YEARLY' : 'MONTHLY',
                'description' => $options['description'] ?? 'Assinatura',
                'externalReference' => $priceId,
            ]);

        if (!$response->successful()) {
            return new SubscriptionResult(
                success: false,
                status: 'failed',
                failureMessage: $response->json('errors.0.description'),
            );
        }

        $subscription = $response->json();

        return new SubscriptionResult(
            success: true,
            providerSubscriptionId: $subscription['id'],
            status: $this->mapAsaasSubscriptionStatus($subscription['status']),
            providerData: $subscription,
        );
    }

    /**
     * Handle Asaas webhook
     */
    public function handleWebhook(array $payload, array $headers): void
    {
        $event = $payload['event'] ?? null;
        $paymentData = $payload['payment'] ?? null;

        if (!$event || !$paymentData) {
            return;
        }

        $payment = Payment::where('provider', 'asaas')
            ->where('provider_payment_id', $paymentData['id'])
            ->first();

        if (!$payment) {
            return;
        }

        $newStatus = match ($event) {
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => 'paid',
            'PAYMENT_OVERDUE' => 'overdue',
            'PAYMENT_REFUNDED' => 'refunded',
            'PAYMENT_DELETED' => 'canceled',
            default => $payment->status,
        };

        $payment->update([
            'status' => $newStatus,
            'paid_at' => $newStatus === 'paid' ? now() : null,
            'provider_data->webhook' => $payload,
        ]);

        if ($newStatus === 'paid') {
            event(new \App\Events\Payment\PaymentConfirmed($payment));
        }
    }

    protected function mapAsaasStatus(string $status): string
    {
        return match ($status) {
            'CONFIRMED', 'RECEIVED' => 'paid',
            'PENDING' => 'pending',
            'OVERDUE' => 'overdue',
            'REFUNDED' => 'refunded',
            default => 'pending',
        };
    }

    protected function mapAsaasSubscriptionStatus(string $status): string
    {
        return match ($status) {
            'ACTIVE' => 'active',
            'INACTIVE' => 'canceled',
            'EXPIRED' => 'expired',
            default => 'pending',
        };
    }
}
```

---

## Payment Gateway Manager

```php
<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Payment\SubscriptionGatewayInterface;
use App\Models\Central\Customer;
use App\Exceptions\Payment\GatewayNotFoundException;
use App\Exceptions\Payment\GatewayUnavailableException;

class PaymentGatewayManager
{
    /**
     * @var array<string, PaymentGatewayInterface>
     */
    protected array $gateways = [];

    /**
     * Gateway padrão
     */
    protected string $defaultGateway;

    public function __construct()
    {
        $this->defaultGateway = config('payment.default_gateway', 'stripe');
        $this->registerConfiguredGateways();
    }

    /**
     * Registrar gateway
     */
    public function register(PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$gateway->getIdentifier()] = $gateway;
    }

    /**
     * Obter gateway por identificador
     */
    public function gateway(string $identifier): PaymentGatewayInterface
    {
        if (!isset($this->gateways[$identifier])) {
            throw new GatewayNotFoundException("Gateway '{$identifier}' not found");
        }

        $gateway = $this->gateways[$identifier];

        if (!$gateway->isAvailable()) {
            throw new GatewayUnavailableException("Gateway '{$identifier}' is not available");
        }

        return $gateway;
    }

    /**
     * Obter gateway padrão
     */
    public function default(): PaymentGatewayInterface
    {
        return $this->gateway($this->defaultGateway);
    }

    /**
     * Obter gateway que suporta subscriptions
     */
    public function subscriptionGateway(?string $identifier = null): SubscriptionGatewayInterface
    {
        $gateway = $identifier
            ? $this->gateway($identifier)
            : $this->default();

        if (!$gateway instanceof SubscriptionGatewayInterface) {
            throw new \InvalidArgumentException(
                "Gateway '{$gateway->getIdentifier()}' does not support subscriptions"
            );
        }

        return $gateway;
    }

    /**
     * Listar gateways disponíveis
     */
    public function available(): array
    {
        return collect($this->gateways)
            ->filter(fn ($g) => $g->isAvailable())
            ->map(fn ($g) => [
                'id' => $g->getIdentifier(),
                'name' => $g->getDisplayName(),
                'types' => $g->getSupportedTypes(),
                'currencies' => $g->getSupportedCurrencies(),
            ])
            ->values()
            ->all();
    }

    /**
     * Listar gateways por tipo de pagamento
     */
    public function forPaymentType(string $type): array
    {
        return collect($this->gateways)
            ->filter(fn ($g) => $g->isAvailable() && in_array($type, $g->getSupportedTypes()))
            ->map(fn ($g) => [
                'id' => $g->getIdentifier(),
                'name' => $g->getDisplayName(),
            ])
            ->values()
            ->all();
    }

    /**
     * Melhor gateway para customer (baseado em país/moeda)
     */
    public function bestForCustomer(Customer $customer): PaymentGatewayInterface
    {
        $currency = $customer->currency ?? 'BRL';
        $country = $customer->billing_address['country'] ?? 'BR';

        // Prioridade para brasileiros
        if ($country === 'BR' && $currency === 'BRL') {
            // Preferir PIX se disponível (menores taxas)
            if ($this->gateways['pix']?->isAvailable()) {
                return $this->gateways['pix'];
            }
        }

        return $this->default();
    }

    protected function registerConfiguredGateways(): void
    {
        $configured = config('payment.gateways', []);

        foreach ($configured as $identifier => $config) {
            if (!($config['enabled'] ?? true)) {
                continue;
            }

            $gatewayClass = $config['class'];
            $this->register(app($gatewayClass));
        }
    }
}
```

---

## Configuration

### config/payment.php

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    */
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Available Gateways
    |--------------------------------------------------------------------------
    */
    'gateways' => [
        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', true),
            'class' => \App\Services\Payment\Gateways\StripeGateway::class,
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'webhook_tolerance' => 300, // seconds
            // Stripe Brazil supports: card, pix, boleto
            'payment_methods' => ['card', 'pix', 'boleto'],
        ],

        'asaas' => [
            'enabled' => env('ASAAS_ENABLED', false),
            'class' => \App\Services\Payment\Gateways\AsaasGateway::class,
            'api_key' => env('ASAAS_API_KEY'),
            'environment' => env('ASAAS_ENVIRONMENT', 'sandbox'), // sandbox, production
            'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
            // Asaas supports: card, pix, boleto
            'payment_methods' => ['card', 'pix', 'boleto'],
        ],

        'pagseguro' => [
            'enabled' => env('PAGSEGURO_ENABLED', false),
            'class' => \App\Services\Payment\Gateways\PagSeguroGateway::class,
            'email' => env('PAGSEGURO_EMAIL'),
            'token' => env('PAGSEGURO_TOKEN'),
            'environment' => env('PAGSEGURO_ENVIRONMENT', 'sandbox'),
            'payment_methods' => ['card', 'pix', 'boleto'],
        ],

        'mercadopago' => [
            'enabled' => env('MERCADOPAGO_ENABLED', false),
            'class' => \App\Services\Payment\Gateways\MercadoPagoGateway::class,
            'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
            'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
            'payment_methods' => ['card', 'pix', 'boleto'],
        ],

        // FUTURE: PIX/Boleto próprio (requer contrato bancário)
        // 'pix_own' => [
        //     'enabled' => env('PIX_OWN_ENABLED', false),
        //     'class' => \App\Services\Payment\Gateways\PixOwnGateway::class,
        //     'psp' => env('PIX_PSP'), // banco_brasil, itau, bradesco
        //     'key' => env('PIX_KEY'),
        //     'key_type' => env('PIX_KEY_TYPE', 'cnpj'),
        // ],
        // 'boleto_own' => [
        //     'enabled' => env('BOLETO_OWN_ENABLED', false),
        //     'class' => \App\Services\Payment\Gateways\BoletoOwnGateway::class,
        //     'bank_code' => env('BOLETO_BANK_CODE'),
        //     'agency' => env('BOLETO_AGENCY'),
        //     'account' => env('BOLETO_ACCOUNT'),
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Types Display Order (by country)
    |--------------------------------------------------------------------------
    */
    'type_priority' => [
        'BR' => ['pix', 'card', 'boleto'], // Brasil: PIX primeiro
        'default' => ['card', 'pix', 'boleto'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway Priority (fallback order)
    |--------------------------------------------------------------------------
    | If primary gateway fails, try these in order
    */
    'fallback_order' => [
        'BR' => ['asaas', 'stripe', 'pagseguro'], // Brasil: preferir Asaas
        'default' => ['stripe', 'asaas'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fees (for margin calculation and display)
    |--------------------------------------------------------------------------
    */
    'fees' => [
        'stripe' => [
            'card' => ['percentage' => 3.99, 'fixed' => 0.39],
            'pix' => ['percentage' => 1.19, 'fixed' => 0],
            'boleto' => ['percentage' => 3.49, 'fixed' => 3.00],
        ],
        'asaas' => [
            'card' => ['percentage' => 2.99, 'fixed' => 0],
            'pix' => ['percentage' => 0.99, 'fixed' => 0],
            'boleto' => ['percentage' => 0, 'fixed' => 2.49],
        ],
        'pagseguro' => [
            'card' => ['percentage' => 3.99, 'fixed' => 0],
            'pix' => ['percentage' => 0.99, 'fixed' => 0],
            'boleto' => ['percentage' => 0, 'fixed' => 3.50],
        ],
        'mercadopago' => [
            'card' => ['percentage' => 4.98, 'fixed' => 0],
            'pix' => ['percentage' => 0.99, 'fixed' => 0],
            'boleto' => ['percentage' => 0, 'fixed' => 3.49],
        ],
    ],
];
```

---

## Frontend Components

### PaymentMethodSelector

```tsx
// resources/js/components/payment/payment-method-selector.tsx

import { useState } from 'react';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { CreditCard, QrCode, FileText } from 'lucide-react';

interface PaymentMethod {
    id: string;
    name: string;
    types: string[];
}

interface Props {
    availableMethods: PaymentMethod[];
    selectedMethod: string;
    onSelect: (method: string) => void;
}

const icons = {
    card: CreditCard,
    pix: QrCode,
    boleto: FileText,
};

export function PaymentMethodSelector({ availableMethods, selectedMethod, onSelect }: Props) {
    return (
        <RadioGroup value={selectedMethod} onValueChange={onSelect}>
            <div className="grid gap-4 md:grid-cols-3">
                {availableMethods.map((method) => {
                    const Icon = icons[method.types[0] as keyof typeof icons] || CreditCard;

                    return (
                        <Card
                            key={method.id}
                            className={`cursor-pointer transition-all ${
                                selectedMethod === method.id
                                    ? 'border-primary ring-2 ring-primary'
                                    : 'hover:border-muted-foreground'
                            }`}
                            onClick={() => onSelect(method.id)}
                        >
                            <CardContent className="flex items-center gap-4 p-4">
                                <RadioGroupItem value={method.id} id={method.id} />
                                <Icon className="h-6 w-6" />
                                <Label htmlFor={method.id} className="cursor-pointer">
                                    {method.name}
                                </Label>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </RadioGroup>
    );
}
```

### PixPayment Component

```tsx
// resources/js/components/payment/pix-payment.tsx

import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Copy, Check, Clock } from 'lucide-react';
import { useToast } from '@/hooks/use-toast';

interface PixData {
    qr_code_base64: string;
    pix_copy_paste: string;
    expires_at: string;
}

interface Props {
    pixData: PixData;
    onExpired: () => void;
    onPaid: () => void;
}

export function PixPayment({ pixData, onExpired, onPaid }: Props) {
    const [copied, setCopied] = useState(false);
    const [timeLeft, setTimeLeft] = useState<number>(0);
    const { toast } = useToast();

    useEffect(() => {
        const expiresAt = new Date(pixData.expires_at).getTime();

        const timer = setInterval(() => {
            const now = Date.now();
            const diff = Math.max(0, expiresAt - now);
            setTimeLeft(Math.floor(diff / 1000));

            if (diff <= 0) {
                clearInterval(timer);
                onExpired();
            }
        }, 1000);

        return () => clearInterval(timer);
    }, [pixData.expires_at, onExpired]);

    // Polling para verificar pagamento
    useEffect(() => {
        const pollInterval = setInterval(async () => {
            // Verificar status do pagamento via API
            // Se pago, chamar onPaid()
        }, 5000);

        return () => clearInterval(pollInterval);
    }, [onPaid]);

    const copyToClipboard = async () => {
        await navigator.clipboard.writeText(pixData.pix_copy_paste);
        setCopied(true);
        toast({ title: 'Código copiado!' });
        setTimeout(() => setCopied(false), 2000);
    };

    const formatTime = (seconds: number) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Clock className="h-5 w-5" />
                    Pague com PIX
                    <span className="ml-auto text-sm font-normal text-muted-foreground">
                        Expira em {formatTime(timeLeft)}
                    </span>
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                {/* QR Code */}
                <div className="flex justify-center">
                    <img
                        src={`data:image/png;base64,${pixData.qr_code_base64}`}
                        alt="QR Code PIX"
                        className="w-64 h-64 border rounded-lg"
                    />
                </div>

                {/* Copia e Cola */}
                <div className="space-y-2">
                    <p className="text-sm text-muted-foreground text-center">
                        Ou copie o código abaixo:
                    </p>
                    <div className="flex gap-2">
                        <code className="flex-1 p-3 bg-muted rounded text-xs break-all">
                            {pixData.pix_copy_paste}
                        </code>
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={copyToClipboard}
                        >
                            {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                        </Button>
                    </div>
                </div>

                <p className="text-sm text-center text-muted-foreground">
                    Abra o app do seu banco, escolha pagar com PIX e escaneie o QR Code ou cole o código.
                </p>
            </CardContent>
        </Card>
    );
}
```

---

## O que Remover (Cashier)

### Arquivos/Código a Remover

```bash
# Composer
composer remove laravel/cashier

# Migrations do Cashier (se existirem)
database/migrations/*_create_customer_columns.php
database/migrations/*_create_subscriptions_table.php
database/migrations/*_create_subscription_items_table.php

# Código que usa Cashier
- Customer model: remover `use Billable;`
- Qualquer uso de `$customer->charge()`, `$customer->newSubscription()`, etc.
```

### Colunas a Remover da tabela `customers`

```php
// Remover estas colunas (são do Cashier):
$table->dropColumn([
    'stripe_id',
    'pm_type',
    'pm_last_four',
    'trial_ends_at',
]);
```

---

## Implementation Phases

### Fase 1: Foundation + Stripe (Semana 1-2)

> **Objetivo**: Criar arquitetura provider-agnostic do zero

| Task | Status |
|------|--------|
| Remove Laravel Cashier package | Pending |
| Create database migrations (payment_methods, payments, subscriptions) | Pending |
| Update customers migration (provider_ids, default_payment_method_id) | Pending |
| Create interfaces/contracts | Pending |
| Create DTOs (ChargeResult, SubscriptionResult, etc.) | Pending |
| Create Subscription model with provider-agnostic fields | Pending |
| Create PaymentMethod model | Pending |
| Create Payment model | Pending |
| Create HasPaymentMethods trait | Pending |
| Create HasSubscriptions trait | Pending |
| Create PaymentGatewayManager | Pending |
| Create config/payment.php | Pending |
| Register PaymentServiceProvider | Pending |
| Create StripeGateway (using Stripe SDK directly) | Pending |
| Update PaymentMethodController to use gateway | Pending |
| Update CheckoutService to use gateway | Pending |
| Create webhook handlers for Stripe | Pending |
| Enable Stripe PIX + Boleto payment methods | Pending |

### Fase 2: Providers Brasileiros (Semana 2-3)

> **Objetivo**: Adicionar Asaas como primeiro provider alternativo

| Task | Status |
|------|--------|
| Create AsaasGateway | Pending |
| Implement Asaas customer sync | Pending |
| Implement Asaas PIX payments | Pending |
| Implement Asaas Boleto payments | Pending |
| Implement Asaas Card payments | Pending |
| Create Asaas webhook handler | Pending |
| Create AsaasPayment frontend components | Pending |
| Integration tests for Asaas | Pending |

### Fase 3: Frontend & UX (Semana 3)

| Task | Status |
|------|--------|
| Create PaymentMethodSelector component | Pending |
| Create PixPayment component (QR Code display) | Pending |
| Create BoletoPayment component (linha digitável) | Pending |
| Update checkout flow with provider selection | Pending |
| Create payment status polling | Pending |
| Add payment method icons | Pending |
| Translations (pt_BR, en) | Pending |

### Fase 4: Testing & Documentation (Semana 4)

| Task | Status |
|------|--------|
| Unit tests for all gateways | Pending |
| Integration tests | Pending |
| E2E tests with Playwright | Pending |
| API documentation | Pending |
| Update CLAUDE.md | Pending |

### Fase Futura: Providers Adicionais

| Provider | Prioridade | Status |
|----------|------------|--------|
| PagSeguro | Média | Backlog |
| MercadoPago | Média | Backlog |
| Iugu | Baixa | Backlog |
| Pagar.me | Baixa | Backlog |

### Fase Futura: PIX/Boleto Próprio

> **Pré-requisitos**: Contrato bancário, PSP homologado, processamento CNAB

| Task | Status |
|------|--------|
| Create PixOwnGateway (EMV QR Code generator) | Backlog |
| Bank PSP integration (Banco do Brasil, Itaú, etc.) | Backlog |
| PIX webhook from bank | Backlog |
| Create BoletoOwnGateway (PDF generator) | Backlog |
| CNAB 240/400 remessa file generator | Backlog |
| CNAB retorno file processor | Backlog |
| Payment reconciliation system | Backlog |

---

## Comparativo de Taxas (Brasil)

| Provider | Cartão Crédito | PIX | Boleto |
|----------|----------------|-----|--------|
| **Stripe** | 3.99% + R$0.39 | 1.19% | 3.49% + R$3.00 |
| **Asaas** | 2.99% | 0.99% | R$2.49 |
| **PagSeguro** | 3.99% | 0.99% | R$3.50 |
| **MercadoPago** | 4.98% | 0.99% | R$3.49 |
| **PIX Próprio** | - | ~R$0.01 | - |
| **Boleto Próprio** | - | - | ~R$1.50 |

---

## Extensão Futura: Tenant Billing (White-Label)

> **Cenário**: Tenants podem cobrar seus próprios clientes usando suas credenciais de gateway

### Arquitetura Multi-Nível

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      MULTI-LEVEL BILLING ARCHITECTURE                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  NÍVEL 1: Platform → Customer (Atual)                                       │
│  ┌─────────────┐         ┌─────────────┐         ┌─────────────┐           │
│  │   Platform  │────────▶│  Customer   │────────▶│   Tenant    │           │
│  │  (Central)  │ cobra   │  (Billing)  │  usa    │ (Workspace) │           │
│  └─────────────┘         └─────────────┘         └─────────────┘           │
│        │                                                                     │
│        └── Credenciais: config/payment.php (nossas)                         │
│                                                                              │
│  NÍVEL 2: Tenant → End Customer (Futuro)                                    │
│  ┌─────────────┐         ┌─────────────┐         ┌─────────────┐           │
│  │   Tenant    │────────▶│ End Customer│────────▶│   Product   │           │
│  │ (Workspace) │ cobra   │  (do Tenant)│ compra  │ (do Tenant) │           │
│  └─────────────┘         └─────────────┘         └─────────────┘           │
│        │                                                                     │
│        └── Credenciais: tenant_payment_settings (deles)                     │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Database Schema (Tenant Database)

```php
// Migration no banco do TENANT (não central)
Schema::create('tenant_payment_settings', function (Blueprint $table) {
    $table->uuid('id')->primary();

    // Gateways habilitados pelo tenant
    $table->json('enabled_gateways'); // ['stripe', 'asaas']

    // Credenciais por gateway (criptografadas)
    $table->json('gateway_credentials');
    // {
    //   "stripe": {"secret_key": "sk_xxx", "publishable_key": "pk_xxx", "webhook_secret": "whsec_xxx"},
    //   "asaas": {"api_key": "xxx", "webhook_token": "xxx"}
    // }

    // Configurações de checkout
    $table->string('default_gateway')->default('stripe');
    $table->string('default_currency', 3)->default('BRL');
    $table->json('accepted_payment_methods'); // ['card', 'pix', 'boleto']

    // Split/Comissão da plataforma (opcional)
    $table->decimal('platform_fee_percentage', 5, 2)->default(0); // Ex: 2.5%
    $table->integer('platform_fee_fixed')->default(0); // Em centavos

    $table->timestamps();
});

// Clientes do tenant (no banco do tenant)
Schema::create('customers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('phone')->nullable();
    $table->json('provider_ids')->nullable(); // IDs em cada gateway
    $table->json('billing_address')->nullable();
    $table->timestamps();
});

// Produtos do tenant
Schema::create('products', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// Preços dos produtos
Schema::create('prices', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
    $table->integer('amount'); // centavos
    $table->string('currency', 3)->default('BRL');
    $table->string('billing_period')->nullable(); // monthly, yearly, null=one-time
    $table->json('provider_price_ids')->nullable(); // IDs em cada gateway
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### TenantPaymentGatewayManager

```php
<?php

namespace App\Services\Tenant\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Models\Tenant\TenantPaymentSettings;

class TenantPaymentGatewayManager
{
    protected TenantPaymentSettings $settings;
    protected array $gateways = [];

    public function __construct()
    {
        $this->settings = TenantPaymentSettings::firstOrFail();
        $this->initializeGateways();
    }

    /**
     * Inicializa gateways com credenciais DO TENANT
     */
    protected function initializeGateways(): void
    {
        foreach ($this->settings->enabled_gateways as $gatewayId) {
            $credentials = $this->settings->getDecryptedCredentials($gatewayId);

            if (empty($credentials)) {
                continue;
            }

            $gatewayClass = config("payment.gateways.{$gatewayId}.class");

            // Injeta credenciais do tenant (não as da plataforma)
            $this->gateways[$gatewayId] = new $gatewayClass($credentials);
        }
    }

    public function gateway(string $identifier): PaymentGatewayInterface
    {
        if (!isset($this->gateways[$identifier])) {
            throw new \RuntimeException("Gateway '{$identifier}' not configured for this tenant");
        }

        return $this->gateways[$identifier];
    }

    public function default(): PaymentGatewayInterface
    {
        return $this->gateway($this->settings->default_gateway);
    }

    public function available(): array
    {
        return array_keys($this->gateways);
    }
}
```

### Gateway com Credenciais Injetáveis

```php
<?php

namespace App\Services\Payment\Gateways;

class StripeGateway implements PaymentGatewayInterface
{
    protected StripeClient $client;
    protected array $credentials;

    /**
     * Aceita credenciais via construtor OU usa config padrão
     */
    public function __construct(?array $credentials = null)
    {
        $this->credentials = $credentials ?? [
            'secret_key' => config('services.stripe.secret'),
            'publishable_key' => config('services.stripe.key'),
            'webhook_secret' => config('services.stripe.webhook_secret'),
        ];

        $this->client = new StripeClient($this->credentials['secret_key']);
    }

    // ... resto da implementação
}
```

### Reuso da Interface

```php
// NÍVEL 1: Platform cobrando Customer (Central)
$manager = app(PaymentGatewayManager::class); // Credenciais da plataforma
$manager->gateway('stripe')->charge($customer, 9900);

// NÍVEL 2: Tenant cobrando End Customer (Tenant DB)
$tenantManager = app(TenantPaymentGatewayManager::class); // Credenciais do tenant
$tenantManager->gateway('stripe')->charge($endCustomer, 4900);
```

### Configuração no Painel do Tenant

```tsx
// resources/js/pages/tenant/admin/settings/payments/index.tsx

export default function PaymentSettings({ settings, availableGateways }: Props) {
    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Configurações de Pagamento</CardTitle>
                    <CardDescription>
                        Configure os gateways para cobrar seus clientes
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {availableGateways.map((gateway) => (
                        <GatewayConfigCard
                            key={gateway.id}
                            gateway={gateway}
                            credentials={settings.gateway_credentials[gateway.id]}
                            enabled={settings.enabled_gateways.includes(gateway.id)}
                            onSave={handleSaveCredentials}
                        />
                    ))}
                </CardContent>
            </Card>
        </div>
    );
}
```

### Vantagens do Reuso

| Componente | Nível 1 (Platform) | Nível 2 (Tenant) |
|------------|-------------------|------------------|
| `PaymentGatewayInterface` | ✅ Mesmo | ✅ Mesmo |
| `StripeGateway` | ✅ Mesmo | ✅ Mesmo |
| `AsaasGateway` | ✅ Mesmo | ✅ Mesmo |
| `ChargeResult` DTO | ✅ Mesmo | ✅ Mesmo |
| Frontend components | ✅ Mesmo | ✅ Mesmo |
| Credenciais | `config/payment.php` | `tenant_payment_settings` |
| Database | Central | Tenant |

### Feature Flag para Habilitar

```php
// PlanFeature enum
case TENANT_BILLING = 'tenantBilling'; // Permite tenant cobrar clientes

// Verificação
if (Feature::for($tenant)->active('tenantBilling')) {
    // Mostra menu "Configurações de Pagamento"
    // Permite configurar credenciais de gateway
}
```

---

## Considerações

### Vantagens da Abstração

1. **Flexibilidade**: Trocar provider sem alterar código de negócio
2. **Testabilidade**: Interfaces permitem mocks fáceis
3. **Manutenibilidade**: Cada gateway isolado em sua classe
4. **Fallback**: Se um provider cair, usar outro automaticamente
5. **A/B Testing**: Testar conversão entre providers

### Por que Asaas como Primeiro Provider Brasileiro?

1. **API moderna**: REST bem documentada
2. **Taxas competitivas**: Menores que Stripe para Brasil
3. **PIX instantâneo**: Webhook em tempo real
4. **Boleto**: Registro automático (não precisa CNAB)
5. **Split de pagamentos**: Para marketplace futuro
6. **Sandbox gratuito**: Testes sem custo

### Quando Considerar PIX/Boleto Próprio?

- Volume > R$500k/mês (economia significativa)
- Equipe para manter integração bancária
- Capacidade de processar arquivos CNAB diariamente
- Infraestrutura para receber webhooks bancários

---

## References

- [Stripe Brazil Payment Methods](https://stripe.com/docs/payments/payment-methods/brazil)
- [Asaas API Documentation](https://docs.asaas.com/)
- [PagSeguro API](https://dev.pagseguro.uol.com.br/)
- [MercadoPago API](https://www.mercadopago.com.br/developers/)
- [PIX EMV Specification](https://www.bcb.gov.br/estabilidadefinanceira/pix)
- [CNAB 240/400 Layout](https://portal.febraban.org.br/pagina/3053/33/pt-br/layout-240)
- [Laravel Cashier](https://laravel.com/docs/billing)
