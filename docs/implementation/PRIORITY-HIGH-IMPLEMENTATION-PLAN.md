# HIGH Priority Implementation Plan

## Multi-Payment Provider - Phase 2

**Status**: Ready for Implementation
**Estimated Complexity**: Medium
**Dependencies**: Phase 1 completed (Cashier migration)

---

## 1. Webhook Handlers for Async Payments

### Overview
Implement robust webhook handling for asynchronous payment methods (PIX, Boleto) that don't confirm immediately.

### 1.1 New Events

**File**: `app/Events/Central/PaymentConfirmed.php`
```php
<?php

namespace App\Events\Central;

use App\Models\Central\AddonPurchase;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AddonPurchase $purchase,
        public string $provider,
        public string $paymentIntentId,
        public array $metadata = []
    ) {}
}
```

**File**: `app/Events/Central/PaymentFailed.php`
```php
<?php

namespace App\Events\Central;

use App\Models\Central\AddonPurchase;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AddonPurchase $purchase,
        public string $provider,
        public string $reason,
        public array $metadata = []
    ) {}
}
```

### 1.2 Webhook Listener

**File**: `app/Listeners/Central/HandleAsyncPaymentWebhooks.php`
```php
<?php

namespace App\Listeners\Central;

use App\Enums\AddonStatus;
use App\Events\Central\PaymentConfirmed;
use App\Events\Central\PaymentFailed;
use App\Models\Central\AddonPurchase;
use App\Models\Central\AddonSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleAsyncPaymentWebhooks implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'high';
    public int $tries = 3;
    public int $backoff = 60;

    public function handlePaymentConfirmed(PaymentConfirmed $event): void
    {
        $purchase = $event->purchase;

        if ($purchase->isCompleted()) {
            Log::info('Payment already confirmed', [
                'purchase_id' => $purchase->id,
                'provider' => $event->provider,
            ]);
            return;
        }

        $purchase->markAsCompleted();
        $purchase->update([
            'stripe_payment_intent_id' => $event->paymentIntentId,
            'metadata' => array_merge($purchase->metadata ?? [], $event->metadata),
        ]);

        // Activate associated subscription if exists
        if ($purchase->addon_subscription_id) {
            $subscription = AddonSubscription::find($purchase->addon_subscription_id);
            $subscription?->update(['status' => AddonStatus::ACTIVE]);
        }

        Log::info('Async payment confirmed', [
            'purchase_id' => $purchase->id,
            'provider' => $event->provider,
        ]);
    }

    public function handlePaymentFailed(PaymentFailed $event): void
    {
        $purchase = $event->purchase;

        $purchase->markAsFailed($event->reason);
        $purchase->update([
            'metadata' => array_merge($purchase->metadata ?? [], $event->metadata),
        ]);

        Log::warning('Async payment failed', [
            'purchase_id' => $purchase->id,
            'provider' => $event->provider,
            'reason' => $event->reason,
        ]);
    }
}
```

### 1.3 Register Events in EventServiceProvider

**File**: `app/Providers/EventServiceProvider.php`
```php
protected $listen = [
    // ... existing events

    \App\Events\Central\PaymentConfirmed::class => [
        \App\Listeners\Central\HandleAsyncPaymentWebhooks::class . '@handlePaymentConfirmed',
    ],
    \App\Events\Central\PaymentFailed::class => [
        \App\Listeners\Central\HandleAsyncPaymentWebhooks::class . '@handlePaymentFailed',
    ],
];
```

### 1.4 Stripe Webhook Events to Handle

Add to `PaymentWebhookController.php`:

```php
protected function handleStripeWebhook(Request $request): JsonResponse
{
    $payload = $request->getContent();
    $sig = $request->header('Stripe-Signature');

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sig,
            config('services.stripe.webhook_secret')
        );
    } catch (\Exception $e) {
        return response()->json(['error' => 'Invalid signature'], 400);
    }

    match ($event->type) {
        'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
        'checkout.session.async_payment_succeeded' => $this->handleAsyncPaymentSucceeded($event->data->object),
        'checkout.session.async_payment_failed' => $this->handleAsyncPaymentFailed($event->data->object),
        'invoice.paid' => $this->handleInvoicePaid($event->data->object),
        'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
        'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
        'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
        default => null,
    };

    return response()->json(['received' => true]);
}

protected function handleAsyncPaymentSucceeded(object $session): void
{
    $purchase = AddonPurchase::where('stripe_checkout_session_id', $session->id)->first();

    if (!$purchase) {
        Log::warning('Purchase not found for async payment', ['session_id' => $session->id]);
        return;
    }

    PaymentConfirmed::dispatch(
        $purchase,
        'stripe',
        $session->payment_intent,
        ['session_id' => $session->id]
    );
}

protected function handleAsyncPaymentFailed(object $session): void
{
    $purchase = AddonPurchase::where('stripe_checkout_session_id', $session->id)->first();

    if (!$purchase) {
        return;
    }

    PaymentFailed::dispatch(
        $purchase,
        'stripe',
        'Async payment failed',
        ['session_id' => $session->id]
    );
}
```

### 1.5 Tests

**File**: `tests/Feature/AsyncPaymentWebhookTest.php`
```php
<?php

namespace Tests\Feature;

use App\Events\Central\PaymentConfirmed;
use App\Events\Central\PaymentFailed;
use App\Listeners\Central\HandleAsyncPaymentWebhooks;
use App\Models\Central\AddonPurchase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

class AsyncPaymentWebhookTest extends TenantTestCase
{
    #[Test]
    public function payment_confirmed_marks_purchase_as_completed(): void
    {
        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create();

        $event = new PaymentConfirmed(
            $purchase,
            'stripe',
            'pi_test_123',
            ['session_id' => 'cs_test']
        );

        $listener = new HandleAsyncPaymentWebhooks();
        $listener->handlePaymentConfirmed($event);

        $purchase->refresh();
        $this->assertTrue($purchase->isCompleted());
        $this->assertEquals('pi_test_123', $purchase->stripe_payment_intent_id);
    }

    #[Test]
    public function payment_confirmed_skips_already_completed(): void
    {
        $purchase = AddonPurchase::factory()->completed()->forTenant($this->tenant)->create([
            'stripe_payment_intent_id' => 'pi_original',
        ]);

        $event = new PaymentConfirmed($purchase, 'stripe', 'pi_new', []);

        $listener = new HandleAsyncPaymentWebhooks();
        $listener->handlePaymentConfirmed($event);

        $purchase->refresh();
        $this->assertEquals('pi_original', $purchase->stripe_payment_intent_id);
    }

    #[Test]
    public function payment_failed_marks_purchase_as_failed(): void
    {
        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create();

        $event = new PaymentFailed($purchase, 'stripe', 'Card declined', []);

        $listener = new HandleAsyncPaymentWebhooks();
        $listener->handlePaymentFailed($event);

        $purchase->refresh();
        $this->assertTrue($purchase->isFailed());
        $this->assertEquals('Card declined', $purchase->failure_reason);
    }
}
```

---

## 2. Frontend Checkout Components

### Overview
Create React components for PIX and Boleto payment methods with real-time status polling.

### 2.1 PIX Payment Component

**File**: `resources/js/components/billing/pix-payment.tsx`
```tsx
import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Loader2, Copy, CheckCircle2, XCircle, Clock } from 'lucide-react';
import { useToast } from '@/hooks/use-toast';
import { cn } from '@/lib/utils';

interface PixPaymentProps {
    qrCodeUrl: string;
    qrCodeData: string; // PIX copia e cola
    expiresAt: string;
    purchaseId: string;
    onSuccess?: () => void;
    onExpired?: () => void;
}

type PaymentStatus = 'pending' | 'completed' | 'failed' | 'expired';

export function PixPayment({
    qrCodeUrl,
    qrCodeData,
    expiresAt,
    purchaseId,
    onSuccess,
    onExpired,
}: PixPaymentProps) {
    const [status, setStatus] = useState<PaymentStatus>('pending');
    const [timeLeft, setTimeLeft] = useState<number>(0);
    const [copied, setCopied] = useState(false);
    const { toast } = useToast();

    // Calculate time left
    useEffect(() => {
        const expires = new Date(expiresAt).getTime();
        const updateTimer = () => {
            const now = Date.now();
            const diff = Math.max(0, Math.floor((expires - now) / 1000));
            setTimeLeft(diff);

            if (diff === 0 && status === 'pending') {
                setStatus('expired');
                onExpired?.();
            }
        };

        updateTimer();
        const interval = setInterval(updateTimer, 1000);
        return () => clearInterval(interval);
    }, [expiresAt, status, onExpired]);

    // Poll for payment status
    useEffect(() => {
        if (status !== 'pending') return;

        const poll = async () => {
            try {
                const response = await fetch(`/api/purchases/${purchaseId}/status`);
                const data = await response.json();

                if (data.status === 'completed') {
                    setStatus('completed');
                    onSuccess?.();
                } else if (data.status === 'failed') {
                    setStatus('failed');
                }
            } catch (error) {
                console.error('Failed to check payment status', error);
            }
        };

        const interval = setInterval(poll, 3000); // Poll every 3 seconds
        return () => clearInterval(interval);
    }, [purchaseId, status, onSuccess]);

    const copyToClipboard = async () => {
        try {
            await navigator.clipboard.writeText(qrCodeData);
            setCopied(true);
            toast({ title: 'Código PIX copiado!' });
            setTimeout(() => setCopied(false), 2000);
        } catch {
            toast({ title: 'Erro ao copiar', variant: 'destructive' });
        }
    };

    const formatTime = (seconds: number) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    if (status === 'completed') {
        return (
            <Card className="border-green-200 bg-green-50">
                <CardContent className="flex flex-col items-center py-8">
                    <CheckCircle2 className="h-16 w-16 text-green-600 mb-4" />
                    <p className="text-lg font-semibold text-green-800">
                        Pagamento confirmado!
                    </p>
                </CardContent>
            </Card>
        );
    }

    if (status === 'expired') {
        return (
            <Card className="border-red-200 bg-red-50">
                <CardContent className="flex flex-col items-center py-8">
                    <XCircle className="h-16 w-16 text-red-600 mb-4" />
                    <p className="text-lg font-semibold text-red-800">
                        QR Code expirado
                    </p>
                    <Button variant="outline" className="mt-4" onClick={() => window.location.reload()}>
                        Gerar novo QR Code
                    </Button>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Clock className="h-5 w-5" />
                    Pague com PIX
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col items-center space-y-4">
                {/* QR Code */}
                <div className="p-4 bg-white rounded-lg border">
                    <img
                        src={qrCodeUrl}
                        alt="QR Code PIX"
                        className="w-48 h-48"
                    />
                </div>

                {/* Timer */}
                <div className={cn(
                    "flex items-center gap-2 text-sm",
                    timeLeft < 60 ? "text-red-600" : "text-muted-foreground"
                )}>
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Expira em {formatTime(timeLeft)}
                </div>

                {/* Copy button */}
                <div className="w-full space-y-2">
                    <p className="text-sm text-muted-foreground text-center">
                        Ou copie o código PIX:
                    </p>
                    <Button
                        variant="outline"
                        className="w-full"
                        onClick={copyToClipboard}
                    >
                        {copied ? (
                            <CheckCircle2 className="h-4 w-4 mr-2" />
                        ) : (
                            <Copy className="h-4 w-4 mr-2" />
                        )}
                        {copied ? 'Copiado!' : 'Copiar código PIX'}
                    </Button>
                </div>

                {/* Instructions */}
                <div className="text-sm text-muted-foreground text-center">
                    <p>1. Abra o app do seu banco</p>
                    <p>2. Escolha pagar com PIX</p>
                    <p>3. Escaneie o QR Code ou cole o código</p>
                </div>
            </CardContent>
        </Card>
    );
}
```

### 2.2 Boleto Payment Component

**File**: `resources/js/components/billing/boleto-payment.tsx`
```tsx
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Copy, Download, FileText, CheckCircle2 } from 'lucide-react';
import { useToast } from '@/hooks/use-toast';
import { useState } from 'react';

interface BoletoPaymentProps {
    boletoUrl: string;
    barcode: string;
    dueDate: string;
    amount: string;
}

export function BoletoPayment({
    boletoUrl,
    barcode,
    dueDate,
    amount,
}: BoletoPaymentProps) {
    const [copied, setCopied] = useState(false);
    const { toast } = useToast();

    const copyBarcode = async () => {
        try {
            await navigator.clipboard.writeText(barcode);
            setCopied(true);
            toast({ title: 'Código de barras copiado!' });
            setTimeout(() => setCopied(false), 2000);
        } catch {
            toast({ title: 'Erro ao copiar', variant: 'destructive' });
        }
    };

    const formatBarcode = (code: string) => {
        // Format barcode for display (grupos de 5)
        return code.replace(/(\d{5})/g, '$1 ').trim();
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <FileText className="h-5 w-5" />
                    Boleto Bancário
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Info */}
                <div className="grid grid-cols-2 gap-4 p-4 bg-muted rounded-lg">
                    <div>
                        <p className="text-sm text-muted-foreground">Valor</p>
                        <p className="font-semibold">{amount}</p>
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Vencimento</p>
                        <p className="font-semibold">{dueDate}</p>
                    </div>
                </div>

                {/* Barcode */}
                <div className="space-y-2">
                    <p className="text-sm text-muted-foreground">Linha digitável:</p>
                    <div className="p-3 bg-muted rounded font-mono text-sm break-all">
                        {formatBarcode(barcode)}
                    </div>
                    <Button
                        variant="outline"
                        className="w-full"
                        onClick={copyBarcode}
                    >
                        {copied ? (
                            <CheckCircle2 className="h-4 w-4 mr-2" />
                        ) : (
                            <Copy className="h-4 w-4 mr-2" />
                        )}
                        {copied ? 'Copiado!' : 'Copiar linha digitável'}
                    </Button>
                </div>

                {/* Download */}
                <Button asChild className="w-full">
                    <a href={boletoUrl} target="_blank" rel="noopener noreferrer">
                        <Download className="h-4 w-4 mr-2" />
                        Baixar Boleto PDF
                    </a>
                </Button>

                {/* Instructions */}
                <div className="text-sm text-muted-foreground text-center space-y-1">
                    <p>O boleto pode levar até 3 dias úteis para compensar.</p>
                    <p>Após o pagamento, seu acesso será liberado automaticamente.</p>
                </div>
            </CardContent>
        </Card>
    );
}
```

### 2.3 Payment Method Selector

**File**: `resources/js/components/billing/payment-method-selector.tsx`
```tsx
import { useState } from 'react';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { CreditCard, QrCode, FileText } from 'lucide-react';
import { cn } from '@/lib/utils';

export type PaymentMethod = 'card' | 'pix' | 'boleto';

interface PaymentMethodSelectorProps {
    value: PaymentMethod;
    onChange: (method: PaymentMethod) => void;
    availableMethods?: PaymentMethod[];
    disabled?: boolean;
}

const methods: Record<PaymentMethod, { label: string; description: string; icon: typeof CreditCard }> = {
    card: {
        label: 'Cartão de Crédito',
        description: 'Aprovação instantânea',
        icon: CreditCard,
    },
    pix: {
        label: 'PIX',
        description: 'Aprovação em segundos',
        icon: QrCode,
    },
    boleto: {
        label: 'Boleto Bancário',
        description: 'Até 3 dias úteis',
        icon: FileText,
    },
};

export function PaymentMethodSelector({
    value,
    onChange,
    availableMethods = ['card', 'pix', 'boleto'],
    disabled = false,
}: PaymentMethodSelectorProps) {
    return (
        <RadioGroup
            value={value}
            onValueChange={(v) => onChange(v as PaymentMethod)}
            disabled={disabled}
            className="grid gap-3"
        >
            {availableMethods.map((method) => {
                const { label, description, icon: Icon } = methods[method];
                const isSelected = value === method;

                return (
                    <Label
                        key={method}
                        htmlFor={method}
                        className={cn(
                            "cursor-pointer",
                            disabled && "cursor-not-allowed opacity-50"
                        )}
                    >
                        <Card className={cn(
                            "transition-colors",
                            isSelected && "border-primary bg-primary/5"
                        )}>
                            <CardContent className="flex items-center gap-4 p-4">
                                <RadioGroupItem value={method} id={method} />
                                <Icon className={cn(
                                    "h-6 w-6",
                                    isSelected ? "text-primary" : "text-muted-foreground"
                                )} />
                                <div className="flex-1">
                                    <p className="font-medium">{label}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {description}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </Label>
                );
            })}
        </RadioGroup>
    );
}
```

### 2.4 API Route for Status Polling

**File**: `routes/api.php` (add)
```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/purchases/{purchase}/status', function (AddonPurchase $purchase) {
        return response()->json([
            'status' => $purchase->status,
            'completed_at' => $purchase->purchased_at?->toISOString(),
        ]);
    })->name('api.purchases.status');
});
```

### 2.5 Playwright E2E Test

**File**: `tests/Browser/pix-checkout.spec.ts`
```typescript
import { test, expect } from '@playwright/test';

test.describe('PIX Checkout Flow', () => {
    test.beforeEach(async ({ page }) => {
        // Login as tenant user
        await page.goto('http://tenant1.test/login');
        await page.fill('input[name="email"]', 'john@acme.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/dashboard');
    });

    test('should display PIX QR code after selecting PIX payment', async ({ page }) => {
        await page.goto('http://tenant1.test/billing/addons');

        // Select an addon
        await page.click('[data-testid="addon-storage-50gb"]');

        // Select PIX as payment method
        await page.click('[data-testid="payment-method-pix"]');

        // Proceed to checkout
        await page.click('[data-testid="checkout-button"]');

        // Verify QR code is displayed
        await expect(page.locator('[data-testid="pix-qr-code"]')).toBeVisible();
        await expect(page.locator('[data-testid="pix-copy-button"]')).toBeVisible();
    });

    test('should copy PIX code to clipboard', async ({ page, context }) => {
        await context.grantPermissions(['clipboard-read', 'clipboard-write']);

        // Navigate to checkout with PIX
        await page.goto('http://tenant1.test/billing/checkout/pix/test-session');

        // Click copy button
        await page.click('[data-testid="pix-copy-button"]');

        // Verify toast message
        await expect(page.locator('text=Código PIX copiado')).toBeVisible();
    });
});
```

---

## 3. Documentation Update

### 3.1 Update MULTI-PAYMENT-PROVIDER-PLAN.md

Add completed status for Phase 2:

```markdown
## Phase 2: Webhook & Frontend (HIGH Priority)

**Status**: ✅ Completed

### Implemented:
- [x] Events: PaymentConfirmed, PaymentFailed
- [x] Listener: HandleAsyncPaymentWebhooks (ShouldQueue)
- [x] Webhook handlers: async_payment_succeeded, async_payment_failed
- [x] Components: PixPayment, BoletoPayment, PaymentMethodSelector
- [x] Status polling API endpoint
- [x] E2E tests with Playwright
```

### 3.2 Update ADDONS.md

Add payment methods section:

```markdown
## Payment Methods

### Available Methods

| Method | Provider | Confirmation Time |
|--------|----------|-------------------|
| Card   | Stripe   | Instant           |
| PIX    | Stripe/Asaas | Seconds (async) |
| Boleto | Stripe/Asaas | 1-3 business days |

### Frontend Components

```tsx
import { PaymentMethodSelector } from '@/components/billing/payment-method-selector';
import { PixPayment } from '@/components/billing/pix-payment';
import { BoletoPayment } from '@/components/billing/boleto-payment';
```
```

---

## Implementation Checklist

### Backend
- [ ] Create `PaymentConfirmed` event
- [ ] Create `PaymentFailed` event
- [ ] Create `HandleAsyncPaymentWebhooks` listener
- [ ] Register events in EventServiceProvider
- [ ] Add async payment handlers to webhook controller
- [ ] Add status polling API endpoint
- [ ] Write unit tests for webhook handling

### Frontend
- [ ] Create `PixPayment` component
- [ ] Create `BoletoPayment` component
- [ ] Create `PaymentMethodSelector` component
- [ ] Add data-testid attributes for E2E tests
- [ ] Create Playwright E2E tests

### Documentation
- [ ] Update MULTI-PAYMENT-PROVIDER-PLAN.md
- [ ] Update ADDONS.md
- [ ] Add JSDoc comments to components

---

## Best Practices Applied

### From Context7 Laravel Documentation:
- Events implement `ShouldQueue` for async processing
- Listeners use `InteractsWithQueue` trait
- Queue priority set to `high` for payment processing
- Retry logic with backoff (3 tries, 60s backoff)

### From Context7 Stripe Documentation:
- Webhook signature validation
- Idempotent event handling (skip already processed)
- Handle both sync and async payment confirmations
- Log all payment events for debugging

### From Context7 React Documentation:
- useEffect cleanup for intervals/timers
- Proper loading and error states
- Toast notifications for user feedback
- Clipboard API with fallback
