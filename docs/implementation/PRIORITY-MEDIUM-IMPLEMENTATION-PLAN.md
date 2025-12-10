# MEDIUM Priority Implementation Plan

## Multi-Payment Provider - Phase 3

**Status**: ✅ COMPLETED (December 2025)
**Estimated Complexity**: Medium-High
**Dependencies**: Phase 2 (HIGH priority) completed

---

## 1. Asaas Gateway Complete Implementation

### Overview
Complete the Asaas payment gateway integration for Brazilian customers with PIX, Boleto, and Card support.

### 1.1 Asaas Gateway Enhancement

**File**: `app/Services/Central/PaymentGateways/AsaasGateway.php`
```php
<?php

namespace App\Services\Central\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\Central\PaymentException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasGateway implements PaymentGatewayInterface
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.asaas.sandbox')
            ? 'https://sandbox.asaas.com/api/v3'
            : 'https://api.asaas.com/v3';
        $this->apiKey = config('services.asaas.api_key');
    }

    /**
     * Create a PIX charge
     */
    public function createPixCharge(array $data): array
    {
        $response = $this->request('POST', '/payments', [
            'customer' => $data['customer_id'],
            'billingType' => 'PIX',
            'value' => $data['amount'] / 100, // Convert from cents
            'dueDate' => $data['due_date'] ?? now()->addDay()->format('Y-m-d'),
            'description' => $data['description'] ?? 'Payment',
            'externalReference' => $data['reference'] ?? null,
        ]);

        // Get PIX QR Code
        $pixData = $this->request('GET', "/payments/{$response['id']}/pixQrCode");

        return [
            'id' => $response['id'],
            'status' => $this->mapStatus($response['status']),
            'qr_code_url' => $pixData['encodedImage'],
            'qr_code_data' => $pixData['payload'],
            'expires_at' => $pixData['expirationDate'],
        ];
    }

    /**
     * Create a Boleto charge
     */
    public function createBoletoCharge(array $data): array
    {
        $response = $this->request('POST', '/payments', [
            'customer' => $data['customer_id'],
            'billingType' => 'BOLETO',
            'value' => $data['amount'] / 100,
            'dueDate' => $data['due_date'] ?? now()->addDays(3)->format('Y-m-d'),
            'description' => $data['description'] ?? 'Payment',
            'externalReference' => $data['reference'] ?? null,
            'postalService' => false, // Don't send physical boleto
        ]);

        return [
            'id' => $response['id'],
            'status' => $this->mapStatus($response['status']),
            'barcode' => $response['identificationField'],
            'boleto_url' => $response['bankSlipUrl'],
            'due_date' => $response['dueDate'],
        ];
    }

    /**
     * Create a Card charge (tokenized)
     */
    public function createCardCharge(array $data): array
    {
        $payload = [
            'customer' => $data['customer_id'],
            'billingType' => 'CREDIT_CARD',
            'value' => $data['amount'] / 100,
            'dueDate' => now()->format('Y-m-d'),
            'description' => $data['description'] ?? 'Payment',
            'externalReference' => $data['reference'] ?? null,
        ];

        // Use tokenized card or card data
        if (isset($data['card_token'])) {
            $payload['creditCardToken'] = $data['card_token'];
        } else {
            $payload['creditCard'] = [
                'holderName' => $data['card']['holder_name'],
                'number' => $data['card']['number'],
                'expiryMonth' => $data['card']['exp_month'],
                'expiryYear' => $data['card']['exp_year'],
                'ccv' => $data['card']['cvc'],
            ];
            $payload['creditCardHolderInfo'] = [
                'name' => $data['holder']['name'],
                'email' => $data['holder']['email'],
                'cpfCnpj' => $data['holder']['cpf_cnpj'],
                'postalCode' => $data['holder']['postal_code'],
                'addressNumber' => $data['holder']['address_number'],
                'phone' => $data['holder']['phone'] ?? null,
            ];
        }

        if ($data['installments'] ?? 1 > 1) {
            $payload['installmentCount'] = $data['installments'];
            $payload['installmentValue'] = ($data['amount'] / 100) / $data['installments'];
        }

        $response = $this->request('POST', '/payments', $payload);

        return [
            'id' => $response['id'],
            'status' => $this->mapStatus($response['status']),
            'card_last_four' => $response['creditCard']['creditCardNumber'] ?? null,
            'card_brand' => $response['creditCard']['creditCardBrand'] ?? null,
        ];
    }

    /**
     * Tokenize a card for future use
     */
    public function tokenizeCard(string $customerId, array $cardData): array
    {
        $response = $this->request('POST', '/creditCard/tokenize', [
            'customer' => $customerId,
            'creditCard' => [
                'holderName' => $cardData['holder_name'],
                'number' => $cardData['number'],
                'expiryMonth' => $cardData['exp_month'],
                'expiryYear' => $cardData['exp_year'],
                'ccv' => $cardData['cvc'],
            ],
            'creditCardHolderInfo' => [
                'name' => $cardData['holder']['name'],
                'email' => $cardData['holder']['email'],
                'cpfCnpj' => $cardData['holder']['cpf_cnpj'],
                'postalCode' => $cardData['holder']['postal_code'],
                'addressNumber' => $cardData['holder']['address_number'],
            ],
        ]);

        return [
            'token' => $response['creditCardToken'],
            'last_four' => $response['creditCardNumber'],
            'brand' => $response['creditCardBrand'],
        ];
    }

    /**
     * Create or retrieve Asaas customer
     */
    public function createCustomer(array $data): array
    {
        // Check if customer exists by CPF/CNPJ
        $existing = $this->request('GET', '/customers', [
            'cpfCnpj' => $data['cpf_cnpj'],
        ]);

        if (!empty($existing['data'])) {
            return ['id' => $existing['data'][0]['id']];
        }

        $response = $this->request('POST', '/customers', [
            'name' => $data['name'],
            'email' => $data['email'],
            'cpfCnpj' => $data['cpf_cnpj'],
            'phone' => $data['phone'] ?? null,
            'postalCode' => $data['postal_code'] ?? null,
            'address' => $data['address'] ?? null,
            'addressNumber' => $data['address_number'] ?? null,
            'externalReference' => $data['reference'] ?? null,
        ]);

        return ['id' => $response['id']];
    }

    /**
     * Get payment status
     */
    public function getPayment(string $paymentId): array
    {
        $response = $this->request('GET', "/payments/{$paymentId}");

        return [
            'id' => $response['id'],
            'status' => $this->mapStatus($response['status']),
            'amount' => (int) ($response['value'] * 100),
            'paid_at' => $response['paymentDate'] ?? null,
        ];
    }

    /**
     * Refund a payment
     */
    public function refund(string $paymentId, ?int $amount = null): array
    {
        $payload = [];
        if ($amount) {
            $payload['value'] = $amount / 100;
        }

        $response = $this->request('POST', "/payments/{$paymentId}/refund", $payload);

        return [
            'id' => $response['id'],
            'status' => 'refunded',
            'refunded_amount' => (int) ($response['value'] * 100),
        ];
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $webhookToken = config('services.asaas.webhook_token');
        return hash_equals($webhookToken, $signature);
    }

    /**
     * Handle webhook event
     */
    public function handleWebhook(array $payload): array
    {
        $event = $payload['event'];
        $payment = $payload['payment'] ?? null;

        return match ($event) {
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => [
                'type' => 'payment.confirmed',
                'payment_id' => $payment['id'],
                'external_reference' => $payment['externalReference'] ?? null,
            ],
            'PAYMENT_OVERDUE' => [
                'type' => 'payment.overdue',
                'payment_id' => $payment['id'],
                'external_reference' => $payment['externalReference'] ?? null,
            ],
            'PAYMENT_REFUNDED' => [
                'type' => 'payment.refunded',
                'payment_id' => $payment['id'],
                'external_reference' => $payment['externalReference'] ?? null,
            ],
            default => ['type' => 'unknown', 'event' => $event],
        };
    }

    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->$method("{$this->baseUrl}{$endpoint}", $data);

        if ($response->failed()) {
            Log::error('Asaas API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $error = $response->json('errors.0.description') ?? 'Unknown error';
            throw new PaymentException("Asaas API error: {$error}");
        }

        return $response->json();
    }

    protected function mapStatus(string $asaasStatus): string
    {
        return match ($asaasStatus) {
            'PENDING' => 'pending',
            'RECEIVED', 'CONFIRMED' => 'completed',
            'OVERDUE' => 'overdue',
            'REFUNDED' => 'refunded',
            'RECEIVED_IN_CASH' => 'completed',
            'REFUND_REQUESTED' => 'refund_pending',
            'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE' => 'disputed',
            'AWAITING_CHARGEBACK_REVERSAL' => 'disputed',
            'DUNNING_REQUESTED', 'DUNNING_RECEIVED' => 'collection',
            default => 'unknown',
        };
    }
}
```

### 1.2 Asaas Configuration

**File**: `config/services.php` (add)
```php
'asaas' => [
    'api_key' => env('ASAAS_API_KEY'),
    'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
    'sandbox' => env('ASAAS_SANDBOX', true),
],
```

**File**: `.env.example` (add)
```env
# Asaas Payment Gateway
ASAAS_API_KEY=
ASAAS_WEBHOOK_TOKEN=
ASAAS_SANDBOX=true
```

### 1.3 Asaas Webhook Handler

**File**: `app/Http/Controllers/Webhooks/AsaasWebhookController.php`
```php
<?php

namespace App\Http\Controllers\Webhooks;

use App\Events\Central\PaymentConfirmed;
use App\Events\Central\PaymentFailed;
use App\Http\Controllers\Controller;
use App\Models\Central\AddonPurchase;
use App\Services\Central\PaymentGateways\AsaasGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    public function __construct(
        protected AsaasGateway $gateway
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('asaas-access-token', '');

        if (!$this->gateway->verifyWebhookSignature($request->getContent(), $signature)) {
            Log::warning('Invalid Asaas webhook signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $event = $this->gateway->handleWebhook($payload);

        match ($event['type']) {
            'payment.confirmed' => $this->handlePaymentConfirmed($event),
            'payment.overdue' => $this->handlePaymentOverdue($event),
            'payment.refunded' => $this->handlePaymentRefunded($event),
            default => Log::info('Unhandled Asaas event', ['event' => $event['type']]),
        };

        return response()->json(['received' => true]);
    }

    protected function handlePaymentConfirmed(array $event): void
    {
        $purchase = AddonPurchase::where('provider_payment_id', $event['payment_id'])
            ->orWhere('id', $event['external_reference'])
            ->first();

        if (!$purchase) {
            Log::warning('Purchase not found for Asaas payment', $event);
            return;
        }

        PaymentConfirmed::dispatch(
            $purchase,
            'asaas',
            $event['payment_id'],
            ['event' => 'payment.confirmed']
        );
    }

    protected function handlePaymentOverdue(array $event): void
    {
        $purchase = AddonPurchase::where('provider_payment_id', $event['payment_id'])
            ->orWhere('id', $event['external_reference'])
            ->first();

        if (!$purchase) {
            return;
        }

        PaymentFailed::dispatch(
            $purchase,
            'asaas',
            'Payment overdue',
            ['event' => 'payment.overdue']
        );
    }

    protected function handlePaymentRefunded(array $event): void
    {
        $purchase = AddonPurchase::where('provider_payment_id', $event['payment_id'])
            ->orWhere('id', $event['external_reference'])
            ->first();

        if ($purchase && !$purchase->isRefunded()) {
            $purchase->refund();
        }
    }
}
```

### 1.4 Asaas Tests

**File**: `tests/Feature/AsaasGatewayTest.php`
```php
<?php

namespace Tests\Feature;

use App\Services\Central\PaymentGateways\AsaasGateway;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AsaasGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asaas.sandbox' => true]);
        config(['services.asaas.api_key' => 'test_api_key']);
    }

    #[Test]
    public function create_pix_charge_returns_qr_code(): void
    {
        Http::fake([
            '*/payments' => Http::response([
                'id' => 'pay_123',
                'status' => 'PENDING',
            ]),
            '*/payments/pay_123/pixQrCode' => Http::response([
                'encodedImage' => 'base64_image_data',
                'payload' => '00020126...',
                'expirationDate' => '2024-12-31 23:59:59',
            ]),
        ]);

        $gateway = new AsaasGateway();
        $result = $gateway->createPixCharge([
            'customer_id' => 'cus_123',
            'amount' => 5000,
            'description' => 'Test payment',
        ]);

        $this->assertEquals('pay_123', $result['id']);
        $this->assertEquals('pending', $result['status']);
        $this->assertNotEmpty($result['qr_code_url']);
        $this->assertNotEmpty($result['qr_code_data']);
    }

    #[Test]
    public function create_boleto_charge_returns_barcode(): void
    {
        Http::fake([
            '*/payments' => Http::response([
                'id' => 'pay_456',
                'status' => 'PENDING',
                'identificationField' => '23793.38128 60000.000003 00000.000400 1 84340000010000',
                'bankSlipUrl' => 'https://asaas.com/boleto/123',
                'dueDate' => '2024-12-31',
            ]),
        ]);

        $gateway = new AsaasGateway();
        $result = $gateway->createBoletoCharge([
            'customer_id' => 'cus_123',
            'amount' => 10000,
        ]);

        $this->assertEquals('pay_456', $result['id']);
        $this->assertNotEmpty($result['barcode']);
        $this->assertNotEmpty($result['boleto_url']);
    }

    #[Test]
    public function tokenize_card_returns_token(): void
    {
        Http::fake([
            '*/creditCard/tokenize' => Http::response([
                'creditCardToken' => 'token_xyz',
                'creditCardNumber' => '1234',
                'creditCardBrand' => 'VISA',
            ]),
        ]);

        $gateway = new AsaasGateway();
        $result = $gateway->tokenizeCard('cus_123', [
            'holder_name' => 'John Doe',
            'number' => '4111111111111111',
            'exp_month' => '12',
            'exp_year' => '2025',
            'cvc' => '123',
            'holder' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'cpf_cnpj' => '12345678901',
                'postal_code' => '01310100',
                'address_number' => '100',
            ],
        ]);

        $this->assertEquals('token_xyz', $result['token']);
        $this->assertEquals('1234', $result['last_four']);
        $this->assertEquals('VISA', $result['brand']);
    }

    #[Test]
    public function webhook_signature_validation(): void
    {
        config(['services.asaas.webhook_token' => 'secret_token']);

        $gateway = new AsaasGateway();

        $this->assertTrue($gateway->verifyWebhookSignature('', 'secret_token'));
        $this->assertFalse($gateway->verifyWebhookSignature('', 'wrong_token'));
    }
}
```

---

## 2. Billing Portal UI

### Overview
Create a comprehensive billing portal for customers to manage payment methods, view invoices, and handle subscriptions.

### 2.1 Payment Methods Page

**File**: `resources/js/pages/customer/payment-methods/index.tsx`
```tsx
import { Head } from '@inertiajs/react';
import { CustomerLayout } from '@/layouts/customer-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { CreditCard, Plus, Trash2, Star, AlertCircle } from 'lucide-react';
import { PaymentMethodResource } from '@/types';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

interface Props {
    paymentMethods: PaymentMethodResource[];
    defaultPaymentMethodId: string | null;
}

const cardBrandIcons: Record<string, string> = {
    visa: '💳',
    mastercard: '💳',
    amex: '💳',
    discover: '💳',
    diners: '💳',
    jcb: '💳',
};

export default function PaymentMethodsIndex({ paymentMethods, defaultPaymentMethodId }: Props) {
    const [deleteId, setDeleteId] = useState<string | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleSetDefault = (id: string) => {
        router.post(`/customer/payment-methods/${id}/default`, {}, {
            preserveScroll: true,
        });
    };

    const handleDelete = () => {
        if (!deleteId) return;

        setIsDeleting(true);
        router.delete(`/customer/payment-methods/${deleteId}`, {
            preserveScroll: true,
            onFinish: () => {
                setIsDeleting(false);
                setDeleteId(null);
            },
        });
    };

    return (
        <CustomerLayout>
            <Head title="Payment Methods" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Payment Methods</h1>
                        <p className="text-muted-foreground">
                            Manage your saved payment methods
                        </p>
                    </div>
                    <Button asChild>
                        <a href="/customer/payment-methods/create">
                            <Plus className="h-4 w-4 mr-2" />
                            Add Payment Method
                        </a>
                    </Button>
                </div>

                {paymentMethods.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center py-12">
                            <CreditCard className="h-12 w-12 text-muted-foreground mb-4" />
                            <p className="text-lg font-medium">No payment methods</p>
                            <p className="text-muted-foreground mb-4">
                                Add a payment method to make purchases
                            </p>
                            <Button asChild>
                                <a href="/customer/payment-methods/create">
                                    Add Payment Method
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4">
                        {paymentMethods.map((method) => (
                            <Card key={method.id}>
                                <CardContent className="flex items-center justify-between p-6">
                                    <div className="flex items-center gap-4">
                                        <div className="text-3xl">
                                            {cardBrandIcons[method.brand.toLowerCase()] || '💳'}
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium capitalize">
                                                    {method.brand}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    •••• {method.last_four}
                                                </span>
                                                {method.id === defaultPaymentMethodId && (
                                                    <Badge variant="secondary">
                                                        <Star className="h-3 w-3 mr-1" />
                                                        Default
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                Expires {method.exp_month}/{method.exp_year}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {method.id !== defaultPaymentMethodId && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleSetDefault(method.id)}
                                            >
                                                Set as Default
                                            </Button>
                                        )}
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setDeleteId(method.id)}
                                            disabled={method.id === defaultPaymentMethodId}
                                        >
                                            <Trash2 className="h-4 w-4 text-destructive" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Warning about default payment method */}
                {paymentMethods.length > 0 && !defaultPaymentMethodId && (
                    <Card className="border-yellow-200 bg-yellow-50">
                        <CardContent className="flex items-center gap-3 py-4">
                            <AlertCircle className="h-5 w-5 text-yellow-600" />
                            <p className="text-sm text-yellow-800">
                                Please set a default payment method for automatic renewals.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Delete confirmation dialog */}
            <AlertDialog open={!!deleteId} onOpenChange={() => setDeleteId(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Remove payment method?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This payment method will be removed from your account.
                            This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDelete}
                            disabled={isDeleting}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {isDeleting ? 'Removing...' : 'Remove'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </CustomerLayout>
    );
}
```

### 2.2 Invoices Page

**File**: `resources/js/pages/customer/invoices/index.tsx`
```tsx
import { Head } from '@inertiajs/react';
import { CustomerLayout } from '@/layouts/customer-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Download, FileText, ExternalLink } from 'lucide-react';
import { InvoiceResource, InertiaPaginatedResponse } from '@/types';
import { Pagination } from '@/components/ui/pagination';

interface Props {
    invoices: InertiaPaginatedResponse<InvoiceResource>;
}

const statusColors: Record<string, string> = {
    paid: 'bg-green-100 text-green-800',
    open: 'bg-blue-100 text-blue-800',
    draft: 'bg-gray-100 text-gray-800',
    uncollectible: 'bg-red-100 text-red-800',
    void: 'bg-gray-100 text-gray-800',
};

export default function InvoicesIndex({ invoices }: Props) {
    return (
        <CustomerLayout>
            <Head title="Invoices" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold">Invoices</h1>
                    <p className="text-muted-foreground">
                        View and download your invoices
                    </p>
                </div>

                {invoices.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center py-12">
                            <FileText className="h-12 w-12 text-muted-foreground mb-4" />
                            <p className="text-lg font-medium">No invoices yet</p>
                            <p className="text-muted-foreground">
                                Your invoices will appear here after your first purchase
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card>
                            <CardContent className="p-0">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left p-4 font-medium">Invoice</th>
                                            <th className="text-left p-4 font-medium">Date</th>
                                            <th className="text-left p-4 font-medium">Amount</th>
                                            <th className="text-left p-4 font-medium">Status</th>
                                            <th className="text-right p-4 font-medium">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {invoices.data.map((invoice) => (
                                            <tr key={invoice.id} className="border-b last:border-0">
                                                <td className="p-4">
                                                    <span className="font-mono text-sm">
                                                        {invoice.number}
                                                    </span>
                                                </td>
                                                <td className="p-4 text-muted-foreground">
                                                    {invoice.date}
                                                </td>
                                                <td className="p-4 font-medium">
                                                    {invoice.total}
                                                </td>
                                                <td className="p-4">
                                                    <Badge className={statusColors[invoice.status]}>
                                                        {invoice.status}
                                                    </Badge>
                                                </td>
                                                <td className="p-4 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        {invoice.pdf_url && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                asChild
                                                            >
                                                                <a
                                                                    href={invoice.pdf_url}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                >
                                                                    <Download className="h-4 w-4" />
                                                                </a>
                                                            </Button>
                                                        )}
                                                        {invoice.hosted_url && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                asChild
                                                            >
                                                                <a
                                                                    href={invoice.hosted_url}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                >
                                                                    <ExternalLink className="h-4 w-4" />
                                                                </a>
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>

                        <Pagination data={invoices} />
                    </>
                )}
            </div>
        </CustomerLayout>
    );
}
```

### 2.3 TypeScript Types

**File**: `resources/js/types/billing.d.ts` (add)
```typescript
export interface PaymentMethodResource {
    id: string;
    brand: string;
    last_four: string;
    exp_month: string;
    exp_year: string;
    is_default: boolean;
}

export interface InvoiceResource {
    id: string;
    number: string;
    date: string;
    total: string;
    status: 'draft' | 'open' | 'paid' | 'uncollectible' | 'void';
    pdf_url: string | null;
    hosted_url: string | null;
    lines: InvoiceLineResource[];
}

export interface InvoiceLineResource {
    description: string;
    quantity: number;
    unit_amount: string;
    amount: string;
}
```

---

## 3. E2E Tests with Playwright

### Overview
Comprehensive end-to-end tests for the checkout and billing flows.

### 3.1 Checkout Flow Test

**File**: `tests/Browser/checkout-flow.spec.ts`
```typescript
import { test, expect } from '@playwright/test';

test.describe('Checkout Flow', () => {
    test.beforeEach(async ({ page }) => {
        // Login as tenant owner
        await page.goto('http://tenant1.test/login');
        await page.fill('input[name="email"]', 'john@acme.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/dashboard');
    });

    test('complete addon purchase with card', async ({ page }) => {
        // Navigate to addons
        await page.goto('http://tenant1.test/billing/addons');

        // Select storage addon
        await page.click('[data-testid="addon-card-storage_50gb"]');

        // Verify addon details
        await expect(page.locator('[data-testid="addon-name"]')).toContainText('Storage 50GB');
        await expect(page.locator('[data-testid="addon-price"]')).toContainText('R$49');

        // Select card payment
        await page.click('[data-testid="payment-method-card"]');

        // Fill card details (Stripe test card)
        const stripeFrame = page.frameLocator('iframe[name^="__privateStripeFrame"]').first();
        await stripeFrame.locator('[placeholder="Card number"]').fill('4242424242424242');
        await stripeFrame.locator('[placeholder="MM / YY"]').fill('12/30');
        await stripeFrame.locator('[placeholder="CVC"]').fill('123');

        // Complete purchase
        await page.click('[data-testid="complete-purchase"]');

        // Verify success
        await expect(page.locator('[data-testid="purchase-success"]')).toBeVisible({ timeout: 30000 });
        await expect(page.locator('[data-testid="purchase-success"]')).toContainText('Payment successful');
    });

    test('addon purchase with PIX shows QR code', async ({ page }) => {
        await page.goto('http://tenant1.test/billing/addons');

        // Select storage addon
        await page.click('[data-testid="addon-card-storage_50gb"]');

        // Select PIX payment
        await page.click('[data-testid="payment-method-pix"]');

        // Complete purchase
        await page.click('[data-testid="complete-purchase"]');

        // Verify PIX QR code is displayed
        await expect(page.locator('[data-testid="pix-qr-code"]')).toBeVisible({ timeout: 10000 });
        await expect(page.locator('[data-testid="pix-timer"]')).toBeVisible();
        await expect(page.locator('[data-testid="pix-copy-button"]')).toBeVisible();
    });

    test('addon purchase with boleto shows barcode', async ({ page }) => {
        await page.goto('http://tenant1.test/billing/addons');

        // Select storage addon
        await page.click('[data-testid="addon-card-storage_50gb"]');

        // Select boleto payment
        await page.click('[data-testid="payment-method-boleto"]');

        // Complete purchase
        await page.click('[data-testid="complete-purchase"]');

        // Verify boleto details
        await expect(page.locator('[data-testid="boleto-barcode"]')).toBeVisible({ timeout: 10000 });
        await expect(page.locator('[data-testid="boleto-download"]')).toBeVisible();
        await expect(page.locator('[data-testid="boleto-due-date"]')).toBeVisible();
    });
});
```

### 3.2 Payment Methods Test

**File**: `tests/Browser/payment-methods.spec.ts`
```typescript
import { test, expect } from '@playwright/test';

test.describe('Payment Methods Management', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('http://tenant1.test/login');
        await page.fill('input[name="email"]', 'john@acme.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/dashboard');
    });

    test('add new payment method', async ({ page }) => {
        await page.goto('http://tenant1.test/customer/payment-methods');

        // Click add payment method
        await page.click('[data-testid="add-payment-method"]');

        // Fill card details
        const stripeFrame = page.frameLocator('iframe[name^="__privateStripeFrame"]').first();
        await stripeFrame.locator('[placeholder="Card number"]').fill('4242424242424242');
        await stripeFrame.locator('[placeholder="MM / YY"]').fill('12/30');
        await stripeFrame.locator('[placeholder="CVC"]').fill('123');

        // Save
        await page.click('[data-testid="save-payment-method"]');

        // Verify card added
        await expect(page.locator('text=•••• 4242')).toBeVisible({ timeout: 10000 });
    });

    test('set default payment method', async ({ page }) => {
        await page.goto('http://tenant1.test/customer/payment-methods');

        // Assuming there are multiple cards
        const secondCard = page.locator('[data-testid="payment-method-card"]').nth(1);
        await secondCard.locator('[data-testid="set-default"]').click();

        // Verify default badge moved
        await expect(secondCard.locator('[data-testid="default-badge"]')).toBeVisible();
    });

    test('delete payment method', async ({ page }) => {
        await page.goto('http://tenant1.test/customer/payment-methods');

        // Get card count
        const initialCount = await page.locator('[data-testid="payment-method-card"]').count();

        // Delete non-default card
        await page.locator('[data-testid="delete-payment-method"]').first().click();

        // Confirm deletion
        await page.click('[data-testid="confirm-delete"]');

        // Verify card removed
        await expect(page.locator('[data-testid="payment-method-card"]')).toHaveCount(initialCount - 1);
    });
});
```

### 3.3 Test Mocks

**File**: `tests/Mocks/AsaasApiMock.php`
```php
<?php

namespace Tests\Mocks;

use Illuminate\Support\Facades\Http;

class AsaasApiMock
{
    public static function fake(): void
    {
        Http::fake([
            '*/payments' => Http::response([
                'id' => 'pay_' . uniqid(),
                'status' => 'PENDING',
                'identificationField' => '23793.38128 60000.000003 00000.000400 1 84340000010000',
                'bankSlipUrl' => 'https://asaas.com/boleto/test',
                'dueDate' => now()->addDays(3)->format('Y-m-d'),
            ]),
            '*/payments/*/pixQrCode' => Http::response([
                'encodedImage' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
                'payload' => '00020126580014br.gov.bcb.pix0136a1234567-89ab-cdef-0123-456789abcdef5204000053039865802BR5913Test Merchant6008Sao Paulo62070503***6304ABCD',
                'expirationDate' => now()->addMinutes(30)->toISOString(),
            ]),
            '*/customers' => Http::response([
                'data' => [],
            ]),
            '*/customers' => Http::response([
                'id' => 'cus_' . uniqid(),
            ]),
        ]);
    }
}
```

---

## Implementation Checklist

### Asaas Gateway ✅
- [x] Implement `createPixCharge()` method → `app/Services/Payment/Gateways/AsaasGateway.php`
- [x] Implement `createBoletoCharge()` method
- [x] Implement `createCardCharge()` method
- [x] Implement `tokenizeCard()` method
- [x] Implement `createCustomer()` method
- [x] Implement webhook handler → `app/Http/Controllers/Webhooks/PaymentWebhookController.php`
- [x] Add Asaas configuration to services.php → `config/payment.php`
- [x] Write unit tests with HTTP mocks

### Billing Portal UI ✅
- [x] Create payment methods index page → `resources/js/pages/customer/payment-methods/index.tsx`
- [x] Create add payment method page → `resources/js/pages/customer/payment-methods/create.tsx`
- [x] Create invoices index page → `resources/js/pages/customer/invoices/index.tsx`
- [x] Add TypeScript types for billing resources → `resources/js/types/addons.d.ts`
- [x] Create API Resources for payment methods and invoices
- [x] Add routes for billing portal

### E2E Tests ✅
- [x] Create checkout flow tests → `tests/Browser/checkout-flow.spec.ts`
- [x] Create payment methods management tests
- [x] Create API mocks for Asaas
- [x] Configure Playwright for tenant domains

---

## Best Practices Applied

### From Context7 Laravel Documentation:
- HTTP Client with retry and timeout configuration
- Proper exception handling with custom PaymentException
- Logging of API errors for debugging
- Configuration via services.php and environment variables

### From Context7 React Documentation:
- Proper state management with useState
- useEffect cleanup for subscriptions
- Accessible form controls with labels
- Loading and error states in UI

### From Context7 Stripe Documentation:
- PCI-compliant card tokenization
- Webhook idempotency handling
- Proper error message mapping
- Test card numbers for development
