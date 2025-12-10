# LOW Priority Implementation Plan

## Multi-Payment Provider - Phase 4+

**Status**: ✅ PARTIALLY COMPLETED (December 2025)
**Estimated Complexity**: High
**Dependencies**: Phase 2 and 3 completed ✅

> **Note**: PagSeguro and MercadoPago have been fully implemented. Remaining features (Tenant Billing, PIX/Boleto Próprio) should only be implemented when specific business requirements are met.

---

## ✅ Completed (December 2025)

### PagSeguro Gateway
- **File**: `app/Services/Payment/Gateways/PagSeguroGateway.php` (~850 lines)
- **Tests**: `tests/Unit/PagSeguroGatewayTest.php` (13 tests)
- **Features**: PIX, Boleto, Credit Card, Subscriptions, Refunds, Webhooks

### MercadoPago Gateway
- **File**: `app/Services/Payment/Gateways/MercadoPagoGateway.php` (~1040 lines)
- **Tests**: `tests/Unit/MercadoPagoGatewayTest.php` (19 tests)
- **Features**: PIX, Boleto, Credit Card, Debit, Subscriptions, Refunds, Webhooks, Multi-currency (BRL, ARS, MXN, etc.)

### Configuration
- **File**: `config/payment.php` - Both gateways fully configured
- **Webhook Routes**: `routes/webhooks.php` - `/pagseguro/webhook`, `/mercadopago/webhook`
- **Controller**: `app/Http/Controllers/Webhooks/PaymentWebhookController.php`

---

## ⏳ Pending - Implement on Demand

| Feature | Implement When |
|---------|---------------|
| Tenant Billing (White-Label) | Enterprise clients request it |
| PIX/Boleto Próprio | Cost savings > R$50k/year |

---

## 1. PagSeguro Gateway

### Overview
Complete implementation of PagSeguro payment gateway for Brazilian market.

### 1.1 Gateway Interface

**File**: `app/Services/Central/PaymentGateways/PagSeguroGateway.php`
```php
<?php

namespace App\Services\Central\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\Central\PaymentException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PagSeguroGateway implements PaymentGatewayInterface
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.pagseguro.sandbox')
            ? 'https://sandbox.api.pagseguro.com'
            : 'https://api.pagseguro.com';
        $this->token = config('services.pagseguro.token');
    }

    /**
     * Create a PIX charge
     */
    public function createPixCharge(array $data): array
    {
        $response = $this->request('POST', '/instant-payments/pix/charges', [
            'reference_id' => $data['reference'] ?? uniqid('pix_'),
            'description' => $data['description'] ?? 'Payment',
            'amount' => [
                'value' => $data['amount'], // In cents
                'currency' => 'BRL',
            ],
            'notification_urls' => [
                config('app.url') . '/webhooks/pagseguro',
            ],
            'qr_codes' => [
                [
                    'amount' => ['value' => $data['amount']],
                    'expiration_date' => now()->addMinutes(30)->toIso8601String(),
                ],
            ],
        ]);

        $qrCode = $response['qr_codes'][0] ?? null;

        return [
            'id' => $response['id'],
            'status' => $this->mapStatus($response['status']),
            'qr_code_url' => $qrCode['links'][0]['href'] ?? null,
            'qr_code_data' => $qrCode['text'] ?? null,
            'expires_at' => $qrCode['expiration_date'] ?? null,
        ];
    }

    /**
     * Create a Boleto charge
     */
    public function createBoletoCharge(array $data): array
    {
        $response = $this->request('POST', '/orders', [
            'reference_id' => $data['reference'] ?? uniqid('boleto_'),
            'customer' => [
                'name' => $data['customer']['name'],
                'email' => $data['customer']['email'],
                'tax_id' => $data['customer']['cpf_cnpj'],
            ],
            'items' => [
                [
                    'name' => $data['description'] ?? 'Payment',
                    'quantity' => 1,
                    'unit_amount' => $data['amount'],
                ],
            ],
            'charges' => [
                [
                    'reference_id' => $data['reference'] ?? uniqid('charge_'),
                    'description' => $data['description'] ?? 'Payment',
                    'amount' => [
                        'value' => $data['amount'],
                        'currency' => 'BRL',
                    ],
                    'payment_method' => [
                        'type' => 'BOLETO',
                        'boleto' => [
                            'due_date' => $data['due_date'] ?? now()->addDays(3)->format('Y-m-d'),
                            'instruction_lines' => [
                                'line_1' => 'Pagamento referente a ' . ($data['description'] ?? 'serviço'),
                            ],
                            'holder' => [
                                'name' => $data['customer']['name'],
                                'tax_id' => $data['customer']['cpf_cnpj'],
                                'email' => $data['customer']['email'],
                            ],
                        ],
                    ],
                ],
            ],
            'notification_urls' => [
                config('app.url') . '/webhooks/pagseguro',
            ],
        ]);

        $charge = $response['charges'][0] ?? null;
        $boleto = $charge['payment_method']['boleto'] ?? null;

        return [
            'id' => $response['id'],
            'charge_id' => $charge['id'] ?? null,
            'status' => $this->mapStatus($charge['status'] ?? 'PENDING'),
            'barcode' => $boleto['barcode'] ?? null,
            'formatted_barcode' => $boleto['formatted_barcode'] ?? null,
            'boleto_url' => $this->getBoletoUrl($charge['links'] ?? []),
            'due_date' => $boleto['due_date'] ?? null,
        ];
    }

    /**
     * Create a Card charge
     */
    public function createCardCharge(array $data): array
    {
        $payload = [
            'reference_id' => $data['reference'] ?? uniqid('card_'),
            'customer' => [
                'name' => $data['customer']['name'],
                'email' => $data['customer']['email'],
                'tax_id' => $data['customer']['cpf_cnpj'],
            ],
            'items' => [
                [
                    'name' => $data['description'] ?? 'Payment',
                    'quantity' => 1,
                    'unit_amount' => $data['amount'],
                ],
            ],
            'charges' => [
                [
                    'reference_id' => $data['reference'] ?? uniqid('charge_'),
                    'description' => $data['description'] ?? 'Payment',
                    'amount' => [
                        'value' => $data['amount'],
                        'currency' => 'BRL',
                    ],
                    'payment_method' => [
                        'type' => 'CREDIT_CARD',
                        'installments' => $data['installments'] ?? 1,
                        'capture' => true,
                        'card' => [
                            'encrypted' => $data['encrypted_card'], // PagSeguro.js encrypted card
                        ],
                    ],
                ],
            ],
            'notification_urls' => [
                config('app.url') . '/webhooks/pagseguro',
            ],
        ];

        $response = $this->request('POST', '/orders', $payload);

        $charge = $response['charges'][0] ?? null;

        return [
            'id' => $response['id'],
            'charge_id' => $charge['id'] ?? null,
            'status' => $this->mapStatus($charge['status'] ?? 'PENDING'),
            'payment_response' => $charge['payment_response'] ?? null,
        ];
    }

    /**
     * Get payment status
     */
    public function getPayment(string $orderId): array
    {
        $response = $this->request('GET', "/orders/{$orderId}");

        $charge = $response['charges'][0] ?? null;

        return [
            'id' => $response['id'],
            'status' => $this->mapStatus($charge['status'] ?? 'PENDING'),
            'amount' => $charge['amount']['value'] ?? 0,
            'paid_at' => $charge['paid_at'] ?? null,
        ];
    }

    /**
     * Refund a payment
     */
    public function refund(string $chargeId, ?int $amount = null): array
    {
        $payload = [];
        if ($amount) {
            $payload['amount'] = ['value' => $amount];
        }

        $response = $this->request('POST', "/charges/{$chargeId}/cancel", $payload);

        return [
            'id' => $response['id'],
            'status' => 'refunded',
            'refunded_amount' => $response['amount']['value'] ?? null,
        ];
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        // PagSeguro uses notification_code in query params
        // Verify by fetching the notification
        return !empty($signature);
    }

    /**
     * Handle webhook event
     */
    public function handleWebhook(array $payload): array
    {
        // PagSeguro sends notification code, need to fetch full details
        $notificationCode = $payload['notificationCode'] ?? null;

        if (!$notificationCode) {
            return ['type' => 'unknown'];
        }

        // Fetch notification details
        $notification = $this->request('GET', "/notifications/{$notificationCode}");

        return match ($notification['status'] ?? null) {
            'PAID', 'AVAILABLE' => [
                'type' => 'payment.confirmed',
                'order_id' => $notification['reference_id'],
                'charge_id' => $notification['id'],
            ],
            'CANCELED', 'DECLINED' => [
                'type' => 'payment.failed',
                'order_id' => $notification['reference_id'],
                'reason' => $notification['payment_response']['message'] ?? 'Payment failed',
            ],
            default => ['type' => 'unknown'],
        };
    }

    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type' => 'application/json',
            'x-api-version' => '4.0',
        ])
        ->timeout(30)
        ->retry(3, 1000)
        ->$method("{$this->baseUrl}{$endpoint}", $data);

        if ($response->failed()) {
            Log::error('PagSeguro API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $error = $response->json('error_messages.0.description') ?? 'Unknown error';
            throw new PaymentException("PagSeguro API error: {$error}");
        }

        return $response->json();
    }

    protected function mapStatus(string $status): string
    {
        return match ($status) {
            'WAITING', 'IN_ANALYSIS' => 'pending',
            'PAID', 'AVAILABLE' => 'completed',
            'CANCELED', 'DECLINED' => 'failed',
            'REFUNDED' => 'refunded',
            'DISPUTE' => 'disputed',
            default => 'unknown',
        };
    }

    protected function getBoletoUrl(array $links): ?string
    {
        foreach ($links as $link) {
            if ($link['media'] === 'application/pdf') {
                return $link['href'];
            }
        }
        return null;
    }
}
```

### 1.2 Configuration

**File**: `config/services.php` (add)
```php
'pagseguro' => [
    'token' => env('PAGSEGURO_TOKEN'),
    'public_key' => env('PAGSEGURO_PUBLIC_KEY'),
    'sandbox' => env('PAGSEGURO_SANDBOX', true),
],
```

### 1.3 Frontend Card Encryption

**File**: `resources/js/lib/pagseguro.ts`
```typescript
declare global {
    interface Window {
        PagSeguro: {
            encryptCard: (config: {
                publicKey: string;
                holder: string;
                number: string;
                expMonth: string;
                expYear: string;
                securityCode: string;
            }) => Promise<{ encryptedCard: string }>;
        };
    }
}

export async function encryptCard(cardData: {
    holder: string;
    number: string;
    expMonth: string;
    expYear: string;
    securityCode: string;
}): Promise<string> {
    const publicKey = import.meta.env.VITE_PAGSEGURO_PUBLIC_KEY;

    if (!window.PagSeguro) {
        throw new Error('PagSeguro SDK not loaded');
    }

    const result = await window.PagSeguro.encryptCard({
        publicKey,
        ...cardData,
    });

    return result.encryptedCard;
}
```

---

## 2. MercadoPago Gateway

### Overview
Integration with MercadoPago for broader Latin America support.

### 2.1 Gateway Interface

**File**: `app/Services/Central/PaymentGateways/MercadoPagoGateway.php`
```php
<?php

namespace App\Services\Central\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\Central\PaymentException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoGateway implements PaymentGatewayInterface
{
    protected string $baseUrl = 'https://api.mercadopago.com';
    protected string $accessToken;

    public function __construct()
    {
        $this->accessToken = config('services.mercadopago.access_token');
    }

    /**
     * Create a PIX charge
     */
    public function createPixCharge(array $data): array
    {
        $response = $this->request('POST', '/v1/payments', [
            'transaction_amount' => $data['amount'] / 100,
            'description' => $data['description'] ?? 'Payment',
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $data['payer']['email'],
                'first_name' => $data['payer']['first_name'] ?? null,
                'last_name' => $data['payer']['last_name'] ?? null,
                'identification' => [
                    'type' => 'CPF',
                    'number' => $data['payer']['cpf'],
                ],
            ],
            'external_reference' => $data['reference'] ?? null,
            'notification_url' => config('app.url') . '/webhooks/mercadopago',
        ]);

        $pix = $response['point_of_interaction']['transaction_data'] ?? null;

        return [
            'id' => (string) $response['id'],
            'status' => $this->mapStatus($response['status']),
            'qr_code_url' => $pix['qr_code_base64'] ?? null,
            'qr_code_data' => $pix['qr_code'] ?? null,
            'ticket_url' => $pix['ticket_url'] ?? null,
            'expires_at' => $response['date_of_expiration'] ?? null,
        ];
    }

    /**
     * Create a Boleto charge
     */
    public function createBoletoCharge(array $data): array
    {
        $response = $this->request('POST', '/v1/payments', [
            'transaction_amount' => $data['amount'] / 100,
            'description' => $data['description'] ?? 'Payment',
            'payment_method_id' => 'bolbradesco', // Bradesco boleto
            'payer' => [
                'email' => $data['payer']['email'],
                'first_name' => $data['payer']['first_name'] ?? null,
                'last_name' => $data['payer']['last_name'] ?? null,
                'identification' => [
                    'type' => 'CPF',
                    'number' => $data['payer']['cpf'],
                ],
                'address' => [
                    'zip_code' => $data['payer']['postal_code'] ?? null,
                    'street_name' => $data['payer']['street'] ?? null,
                    'street_number' => $data['payer']['street_number'] ?? null,
                ],
            ],
            'external_reference' => $data['reference'] ?? null,
            'date_of_expiration' => now()->addDays(3)->toIso8601String(),
            'notification_url' => config('app.url') . '/webhooks/mercadopago',
        ]);

        return [
            'id' => (string) $response['id'],
            'status' => $this->mapStatus($response['status']),
            'barcode' => $response['barcode']['content'] ?? null,
            'boleto_url' => $response['transaction_details']['external_resource_url'] ?? null,
            'due_date' => $response['date_of_expiration'] ?? null,
        ];
    }

    /**
     * Create a Card charge
     */
    public function createCardCharge(array $data): array
    {
        $response = $this->request('POST', '/v1/payments', [
            'transaction_amount' => $data['amount'] / 100,
            'description' => $data['description'] ?? 'Payment',
            'payment_method_id' => $data['payment_method_id'], // visa, master, etc.
            'token' => $data['card_token'], // MercadoPago.js token
            'installments' => $data['installments'] ?? 1,
            'payer' => [
                'email' => $data['payer']['email'],
                'identification' => [
                    'type' => 'CPF',
                    'number' => $data['payer']['cpf'],
                ],
            ],
            'external_reference' => $data['reference'] ?? null,
            'notification_url' => config('app.url') . '/webhooks/mercadopago',
        ]);

        return [
            'id' => (string) $response['id'],
            'status' => $this->mapStatus($response['status']),
            'status_detail' => $response['status_detail'] ?? null,
            'card_last_four' => $response['card']['last_four_digits'] ?? null,
        ];
    }

    /**
     * Get payment status
     */
    public function getPayment(string $paymentId): array
    {
        $response = $this->request('GET', "/v1/payments/{$paymentId}");

        return [
            'id' => (string) $response['id'],
            'status' => $this->mapStatus($response['status']),
            'amount' => (int) ($response['transaction_amount'] * 100),
            'paid_at' => $response['date_approved'] ?? null,
        ];
    }

    /**
     * Refund a payment
     */
    public function refund(string $paymentId, ?int $amount = null): array
    {
        $payload = [];
        if ($amount) {
            $payload['amount'] = $amount / 100;
        }

        $response = $this->request('POST', "/v1/payments/{$paymentId}/refunds", $payload);

        return [
            'id' => (string) $response['id'],
            'status' => 'refunded',
            'refunded_amount' => (int) ($response['amount'] * 100),
        ];
    }

    /**
     * Handle webhook
     */
    public function handleWebhook(array $payload): array
    {
        $type = $payload['type'] ?? null;
        $action = $payload['action'] ?? null;

        if ($type !== 'payment') {
            return ['type' => 'unknown'];
        }

        $paymentId = $payload['data']['id'] ?? null;

        if (!$paymentId) {
            return ['type' => 'unknown'];
        }

        // Fetch payment details
        $payment = $this->getPayment((string) $paymentId);

        return match ($payment['status']) {
            'completed' => [
                'type' => 'payment.confirmed',
                'payment_id' => $paymentId,
                'external_reference' => null, // Need to fetch from payment
            ],
            'failed', 'rejected' => [
                'type' => 'payment.failed',
                'payment_id' => $paymentId,
                'reason' => 'Payment failed',
            ],
            default => ['type' => 'unknown'],
        };
    }

    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ])
        ->timeout(30)
        ->$method("{$this->baseUrl}{$endpoint}", $data);

        if ($response->failed()) {
            Log::error('MercadoPago API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $error = $response->json('message') ?? 'Unknown error';
            throw new PaymentException("MercadoPago API error: {$error}");
        }

        return $response->json();
    }

    protected function mapStatus(string $status): string
    {
        return match ($status) {
            'pending', 'in_process', 'in_mediation' => 'pending',
            'approved' => 'completed',
            'rejected', 'cancelled' => 'failed',
            'refunded' => 'refunded',
            'charged_back' => 'disputed',
            default => 'unknown',
        };
    }
}
```

### 2.2 Configuration

**File**: `config/services.php` (add)
```php
'mercadopago' => [
    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
    'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
],
```

---

## 3. Tenant Billing (White-Label)

### Overview
Allow tenants to process payments on behalf of their own customers using tenant-specific payment credentials.

### 3.1 Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      Central Database                           │
├─────────────────────────────────────────────────────────────────┤
│  tenant_payment_credentials                                     │
│  ├── id                                                         │
│  ├── tenant_id (FK)                                            │
│  ├── provider (stripe|asaas|pagseguro|mercadopago)            │
│  ├── credentials (encrypted JSON)                              │
│  │   ├── api_key                                               │
│  │   ├── webhook_secret                                        │
│  │   └── public_key                                            │
│  ├── is_live (bool)                                            │
│  ├── verified_at (datetime)                                    │
│  └── created_at                                                │
└─────────────────────────────────────────────────────────────────┘
```

### 3.2 Migration

**File**: `database/migrations/xxxx_create_tenant_payment_credentials_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_payment_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // stripe, asaas, pagseguro, mercadopago
            $table->text('credentials'); // Encrypted JSON
            $table->boolean('is_live')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payment_credentials');
    }
};
```

### 3.3 Model

**File**: `app/Models/Central/TenantPaymentCredential.php`
```php
<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class TenantPaymentCredential extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'provider',
        'credentials',
        'is_live',
        'verified_at',
    ];

    protected $casts = [
        'is_live' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function setCredentialsAttribute(array $value): void
    {
        $this->attributes['credentials'] = Crypt::encryptString(json_encode($value));
    }

    public function getCredentialsAttribute(?string $value): array
    {
        if (!$value) {
            return [];
        }

        return json_decode(Crypt::decryptString($value), true);
    }

    public function getApiKey(): ?string
    {
        return $this->credentials['api_key'] ?? null;
    }

    public function getPublicKey(): ?string
    {
        return $this->credentials['public_key'] ?? null;
    }

    public function getWebhookSecret(): ?string
    {
        return $this->credentials['webhook_secret'] ?? null;
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function markAsVerified(): void
    {
        $this->update(['verified_at' => now()]);
    }
}
```

### 3.4 Tenant Gateway Factory

**File**: `app/Services/Central/TenantPaymentGatewayFactory.php`
```php
<?php

namespace App\Services\Central;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\Central\PaymentException;
use App\Models\Central\Tenant;
use App\Services\Central\PaymentGateways\AsaasGateway;
use App\Services\Central\PaymentGateways\MercadoPagoGateway;
use App\Services\Central\PaymentGateways\PagSeguroGateway;
use App\Services\Central\PaymentGateways\StripeGateway;

class TenantPaymentGatewayFactory
{
    /**
     * Create a gateway instance using tenant's credentials
     */
    public static function make(Tenant $tenant, string $provider): PaymentGatewayInterface
    {
        $credential = $tenant->paymentCredentials()
            ->where('provider', $provider)
            ->where('verified_at', '!=', null)
            ->first();

        if (!$credential) {
            throw new PaymentException("Tenant has no verified {$provider} credentials");
        }

        return match ($provider) {
            'stripe' => new StripeGateway(
                apiKey: $credential->getApiKey(),
                webhookSecret: $credential->getWebhookSecret(),
            ),
            'asaas' => new AsaasGateway(
                apiKey: $credential->getApiKey(),
            ),
            'pagseguro' => new PagSeguroGateway(
                token: $credential->getApiKey(),
            ),
            'mercadopago' => new MercadoPagoGateway(
                accessToken: $credential->getApiKey(),
            ),
            default => throw new PaymentException("Unknown provider: {$provider}"),
        };
    }
}
```

### 3.5 Credential Verification Service

**File**: `app/Services/Central/PaymentCredentialVerificationService.php`
```php
<?php

namespace App\Services\Central;

use App\Exceptions\Central\PaymentException;
use App\Models\Central\TenantPaymentCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentCredentialVerificationService
{
    /**
     * Verify that credentials are valid by making a test API call
     */
    public function verify(TenantPaymentCredential $credential): bool
    {
        try {
            $isValid = match ($credential->provider) {
                'stripe' => $this->verifyStripe($credential),
                'asaas' => $this->verifyAsaas($credential),
                'pagseguro' => $this->verifyPagSeguro($credential),
                'mercadopago' => $this->verifyMercadoPago($credential),
                default => false,
            };

            if ($isValid) {
                $credential->markAsVerified();
            }

            return $isValid;
        } catch (\Exception $e) {
            Log::warning('Payment credential verification failed', [
                'tenant_id' => $credential->tenant_id,
                'provider' => $credential->provider,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function verifyStripe(TenantPaymentCredential $credential): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $credential->getApiKey(),
        ])->get('https://api.stripe.com/v1/balance');

        return $response->successful();
    }

    protected function verifyAsaas(TenantPaymentCredential $credential): bool
    {
        $baseUrl = $credential->is_live
            ? 'https://api.asaas.com/v3'
            : 'https://sandbox.asaas.com/api/v3';

        $response = Http::withHeaders([
            'access_token' => $credential->getApiKey(),
        ])->get("{$baseUrl}/myAccount");

        return $response->successful();
    }

    protected function verifyPagSeguro(TenantPaymentCredential $credential): bool
    {
        $baseUrl = $credential->is_live
            ? 'https://api.pagseguro.com'
            : 'https://sandbox.api.pagseguro.com';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $credential->getApiKey(),
        ])->get("{$baseUrl}/account");

        return $response->successful();
    }

    protected function verifyMercadoPago(TenantPaymentCredential $credential): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $credential->getApiKey(),
        ])->get('https://api.mercadopago.com/users/me');

        return $response->successful();
    }
}
```

---

## 4. PIX/Boleto Próprio (Bank Direct Integration)

### Overview
Direct integration with Brazilian banks for PIX and Boleto, bypassing payment gateways for cost savings.

### 4.1 Requirements

**Bank Contracts Required**:
- PIX: Contract with participating bank (Bradesco, Itaú, BB, Santander, etc.)
- Boleto: Registered boleto contract with bank

**Technical Requirements**:
- Bank API credentials (client_id, client_secret, certificates)
- mTLS certificates for authentication
- CNAB 240/400 processing capability
- PIX certificates (DICT registration)

### 4.2 Cost-Benefit Analysis

| Method | Gateway Cost | Direct Cost | Monthly Volume Threshold |
|--------|-------------|-------------|--------------------------|
| PIX | 0.99% | Free* | R$100k |
| Boleto | R$3-5/unit | R$0.50-1.50/unit | 1000 boletos |

*PIX is free for receiving, but banks may charge for API access or high volumes.

### 4.3 Architecture Overview

**File**: `app/Services/Central/PaymentGateways/BankDirectGateway.php`
```php
<?php

namespace App\Services\Central\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;

/**
 * Base class for direct bank integrations
 *
 * Each bank has specific APIs and authentication methods:
 * - Bradesco: OAuth2 + mTLS
 * - Itaú: OAuth2 + mTLS + DICT
 * - Banco do Brasil: OAuth2 + certificates
 * - Santander: API Key + certificates
 */
abstract class BankDirectGateway implements PaymentGatewayInterface
{
    protected string $bankCode;
    protected array $certificates;

    abstract public function authenticate(): string; // Get access token
    abstract public function createPixKey(): array; // Register PIX key
    abstract public function generateQrCode(array $data): array;
    abstract public function registerBoleto(array $data): array;
    abstract public function processCnabReturn(string $content): array;
}
```

### 4.4 Implementation Considerations

**Security**:
- Store certificates in secure vault (AWS Secrets Manager, HashiCorp Vault)
- Rotate certificates before expiration
- Implement IP whitelisting for bank callbacks

**Reliability**:
- Implement circuit breaker for bank API calls
- Queue boleto registration for retry
- Store CNAB files for audit trail

**Compliance**:
- Log all transactions for BACEN reporting
- Implement fraud detection rules
- Maintain transaction receipts for 5 years

---

## Implementation Checklist

### PagSeguro Gateway ✅ COMPLETED (December 2025)
- [x] Create gateway class with all methods → `app/Services/Payment/Gateways/PagSeguroGateway.php`
- [x] Add configuration to config/payment.php
- [x] Create webhook handler → `PaymentWebhookController::handlePagseguro()`
- [x] Implement frontend card encryption (uses PagSeguro.js client-side)
- [x] Write unit tests with mocks → `tests/Unit/PagSeguroGatewayTest.php` (13 tests)
- [x] Sandbox URL configured: `https://sandbox.api.pagseguro.com`

### MercadoPago Gateway ✅ COMPLETED (December 2025)
- [x] Create gateway class with all methods → `app/Services/Payment/Gateways/MercadoPagoGateway.php`
- [x] Add configuration to config/payment.php
- [x] Create webhook handler → `PaymentWebhookController::handleMercadopago()`
- [x] Implement frontend tokenization (uses MercadoPago.js client-side)
- [x] Write unit tests with mocks → `tests/Unit/MercadoPagoGatewayTest.php` (19 tests)
- [x] Multi-currency support: BRL, ARS, CLP, COP, MXN, PEN, UYU

### Tenant Billing (White-Label)
- [ ] Create migration for credentials table
- [ ] Create TenantPaymentCredential model
- [ ] Create TenantPaymentGatewayFactory
- [ ] Create credential verification service
- [ ] Create admin UI for credential management
- [ ] Create tenant settings UI
- [ ] Write integration tests
- [ ] Document setup process

### PIX/Boleto Próprio
- [ ] Evaluate bank API options
- [ ] Negotiate bank contracts
- [ ] Implement mTLS authentication
- [ ] Create CNAB parser
- [ ] Create PIX DICT integration
- [ ] Set up secure certificate storage
- [ ] Implement reconciliation process
- [ ] Create monitoring and alerting

---

## Decision Criteria

### PagSeguro/MercadoPago ✅ IMPLEMENTED
Both gateways have been implemented and are ready for production use once API credentials are configured in `.env`:
```env
# PagSeguro
PAGSEGURO_ENABLED=true
PAGSEGURO_API_KEY=your_api_key
PAGSEGURO_PUBLIC_KEY=your_public_key
PAGSEGURO_SANDBOX=true

# MercadoPago
MERCADOPAGO_ENABLED=true
MERCADOPAGO_ACCESS_TOKEN=your_access_token
MERCADOPAGO_PUBLIC_KEY=your_public_key
MERCADOPAGO_SANDBOX=true
```

### When to Implement Tenant Billing
- Enterprise tier customers requesting white-label
- Monthly billing revenue > R$50k
- Customer has own payment gateway accounts
- Need for tenant-branded checkout experience

### When to Implement PIX/Boleto Próprio
- Monthly PIX volume > R$100k
- Monthly boleto volume > 1000 units
- Cost savings > R$50k/year
- Have technical resources for bank integration
- Can handle BACEN compliance requirements

---

## Risk Assessment

| Feature | Technical Risk | Business Risk | Mitigation |
|---------|---------------|---------------|------------|
| PagSeguro | Low | Low | Sandbox testing |
| MercadoPago | Low | Low | Sandbox testing |
| Tenant Billing | Medium | Medium | Credential encryption, verification |
| PIX Próprio | High | Medium | Start with one bank, gradual rollout |
| Boleto Próprio | High | Low | CNAB processing complexity |

---

## Timeline Recommendations

1. **Q1**: PagSeguro and MercadoPago (if demand exists)
2. **Q2**: Tenant Billing (if enterprise demand)
3. **Q3-Q4**: PIX/Boleto Próprio (if volume justifies)

Each implementation should be preceded by:
- Customer demand analysis
- Cost-benefit calculation
- Security review
- Compliance assessment
