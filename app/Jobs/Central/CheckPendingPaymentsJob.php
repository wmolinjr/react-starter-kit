<?php

declare(strict_types=1);

namespace App\Jobs\Central;

use App\Events\Payment\PaymentConfirmed;
use App\Events\Payment\PaymentFailed;
use App\Models\Central\AddonPurchase;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Check Pending Payments Job
 *
 * Polls payment providers for status updates on pending async payments (PIX, Boleto).
 * This is a fallback mechanism in case webhook delivery fails.
 *
 * Schedule: Run every 5 minutes via Laravel Scheduler
 *
 * @example
 * // In Console/Kernel.php schedule method:
 * $schedule->job(new CheckPendingPaymentsJob)->everyFiveMinutes();
 *
 * // Or dispatch manually:
 * CheckPendingPaymentsJob::dispatch();
 */
class CheckPendingPaymentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param  int|null  $olderThanMinutes  Only check payments older than X minutes (default: 5)
     * @param  int|null  $limit  Maximum number of payments to check per run (default: 100)
     */
    public function __construct(
        protected ?int $olderThanMinutes = 5,
        protected ?int $limit = 100
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentGatewayManager $gatewayManager): void
    {
        $pendingPurchases = $this->getPendingPurchases();

        if ($pendingPurchases->isEmpty()) {
            Log::debug('CheckPendingPaymentsJob: No pending payments to check');

            return;
        }

        Log::info('CheckPendingPaymentsJob: Checking pending payments', [
            'count' => $pendingPurchases->count(),
        ]);

        foreach ($pendingPurchases as $purchase) {
            try {
                $this->checkPaymentStatus($purchase, $gatewayManager);
            } catch (\Exception $e) {
                Log::error('CheckPendingPaymentsJob: Error checking payment', [
                    'purchase_id' => $purchase->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get pending purchases that need status check.
     */
    protected function getPendingPurchases()
    {
        return AddonPurchase::query()
            ->where('status', 'pending')
            ->whereNotNull('provider_payment_intent_id') // Has provider payment ID
            ->where('created_at', '<', now()->subMinutes($this->olderThanMinutes))
            ->where('created_at', '>', now()->subDays(7)) // Don't check very old payments
            ->orderBy('created_at', 'asc')
            ->limit($this->limit)
            ->get();
    }

    /**
     * Check payment status with provider.
     */
    protected function checkPaymentStatus(AddonPurchase $purchase, PaymentGatewayManager $gatewayManager): void
    {
        // Determine provider from purchase column or detect from payment data
        $provider = $purchase->provider ?? $purchase->metadata['provider'] ?? $this->detectProvider($purchase);

        if (! $provider) {
            Log::warning('CheckPendingPaymentsJob: Cannot determine provider', [
                'purchase_id' => $purchase->id,
            ]);

            return;
        }

        try {
            $gateway = $gatewayManager->driver($provider);
        } catch (\Exception $e) {
            Log::error('CheckPendingPaymentsJob: Gateway not found', [
                'purchase_id' => $purchase->id,
                'provider' => $provider,
            ]);

            return;
        }

        // Get payment ID from provider-agnostic column
        $paymentId = $purchase->provider_payment_intent_id;

        if (! $paymentId) {
            return;
        }

        // Check status with provider
        if ($provider === 'asaas') {
            $this->checkAsaasPayment($purchase, $gateway, $paymentId);
        } elseif ($provider === 'stripe') {
            $this->checkStripePayment($purchase, $gateway, $paymentId);
        }
    }

    /**
     * Check Asaas payment status.
     */
    protected function checkAsaasPayment(AddonPurchase $purchase, $gateway, string $paymentId): void
    {
        try {
            $paymentData = $gateway->retrievePayment($paymentId);
            $status = $paymentData['status'] ?? 'PENDING';

            Log::debug('CheckPendingPaymentsJob: Asaas payment status', [
                'purchase_id' => $purchase->id,
                'payment_id' => $paymentId,
                'status' => $status,
            ]);

            if (in_array($status, ['CONFIRMED', 'RECEIVED'])) {
                // Payment confirmed - dispatch event
                PaymentConfirmed::dispatch(
                    $purchase,
                    'asaas',
                    $paymentId,
                    [
                        'billing_type' => $paymentData['billingType'] ?? null,
                        'value' => $paymentData['value'] ?? null,
                        'net_value' => $paymentData['netValue'] ?? null,
                        'payment_date' => $paymentData['paymentDate'] ?? null,
                        'confirmed_date' => $paymentData['confirmedDate'] ?? null,
                        'polled' => true,
                    ]
                );

                Log::info('CheckPendingPaymentsJob: Payment confirmed via polling', [
                    'purchase_id' => $purchase->id,
                ]);
            } elseif (in_array($status, ['OVERDUE', 'REFUNDED', 'DELETED'])) {
                // Payment failed/expired
                $reason = match ($status) {
                    'OVERDUE' => 'Pagamento vencido (PIX expirado ou Boleto vencido)',
                    'REFUNDED' => 'Pagamento estornado',
                    'DELETED' => 'Pagamento cancelado',
                    default => 'Falha no pagamento',
                };

                PaymentFailed::dispatch(
                    $purchase,
                    'asaas',
                    $reason,
                    [
                        'status' => $status,
                        'polled' => true,
                    ]
                );

                Log::info('CheckPendingPaymentsJob: Payment failed via polling', [
                    'purchase_id' => $purchase->id,
                    'status' => $status,
                ]);
            }
            // If still PENDING, do nothing - will check again next run
        } catch (\Exception $e) {
            Log::error('CheckPendingPaymentsJob: Failed to check Asaas payment', [
                'purchase_id' => $purchase->id,
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check Stripe payment status.
     */
    protected function checkStripePayment(AddonPurchase $purchase, $gateway, string $paymentId): void
    {
        try {
            // Use Stripe SDK to retrieve payment intent
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentId);
            $status = $paymentIntent->status;

            Log::debug('CheckPendingPaymentsJob: Stripe payment status', [
                'purchase_id' => $purchase->id,
                'payment_id' => $paymentId,
                'status' => $status,
            ]);

            if ($status === 'succeeded') {
                PaymentConfirmed::dispatch(
                    $purchase,
                    'stripe',
                    $paymentId,
                    [
                        'amount' => $paymentIntent->amount,
                        'currency' => $paymentIntent->currency,
                        'polled' => true,
                    ]
                );

                Log::info('CheckPendingPaymentsJob: Stripe payment confirmed via polling', [
                    'purchase_id' => $purchase->id,
                ]);
            } elseif (in_array($status, ['canceled', 'requires_payment_method'])) {
                PaymentFailed::dispatch(
                    $purchase,
                    'stripe',
                    'Payment failed: '.$status,
                    [
                        'status' => $status,
                        'polled' => true,
                    ]
                );

                Log::info('CheckPendingPaymentsJob: Stripe payment failed via polling', [
                    'purchase_id' => $purchase->id,
                    'status' => $status,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('CheckPendingPaymentsJob: Failed to check Stripe payment', [
                'purchase_id' => $purchase->id,
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Detect provider from purchase data.
     */
    protected function detectProvider(AddonPurchase $purchase): ?string
    {
        // First check the provider column directly
        if ($purchase->provider) {
            return $purchase->provider;
        }

        $paymentMethod = $purchase->payment_method ?? '';

        // PIX and Boleto are typically Asaas
        if (in_array($paymentMethod, ['pix', 'boleto'])) {
            return 'asaas';
        }

        // Card could be either, check metadata
        if (isset($purchase->metadata['provider'])) {
            return $purchase->metadata['provider'];
        }

        // Default to Stripe for card payments
        if ($paymentMethod === 'card') {
            return 'stripe';
        }

        // Try to detect from payment ID format
        $paymentId = $purchase->provider_payment_intent_id ?? '';

        if (str_starts_with($paymentId, 'pi_')) {
            return 'stripe';
        }

        if (str_starts_with($paymentId, 'pay_')) {
            return 'asaas';
        }

        return null;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['payment', 'pending-check'];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CheckPendingPaymentsJob failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
