<?php

declare(strict_types=1);

namespace App\Jobs\Central;

use App\Mail\Central\SignupWelcome;
use App\Models\Central\PendingSignup;
use App\Models\Central\Subscription;
use App\Services\Central\SignupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Complete Signup Job
 *
 * Queued job that completes the signup process after payment confirmation.
 *
 * Customer-First Flow: Customer already exists (created in Step 1).
 * This job only creates Tenant and Subscription, then sends welcome email.
 */
class CompleteSignupJob implements ShouldQueue
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
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $paymentData  Payment provider data (customer_id, subscription_id, etc.)
     */
    public function __construct(
        public PendingSignup $signup,
        public array $paymentData = []
    ) {
        $this->onQueue('high');
    }

    /**
     * Execute the job.
     */
    public function handle(SignupService $signupService): void
    {
        // Check if already completed (idempotency)
        if ($this->signup->isCompleted()) {
            Log::info('CompleteSignupJob: Signup already completed', [
                'signup_id' => $this->signup->id,
            ]);

            return;
        }

        // Check if signup is in valid state
        if ($this->signup->isFailed() || $this->signup->isExpired()) {
            Log::warning('CompleteSignupJob: Signup in invalid state', [
                'signup_id' => $this->signup->id,
                'status' => $this->signup->status,
            ]);

            return;
        }

        try {
            // Customer-First: Complete signup (create Tenant + Subscription - Customer already exists)
            $result = $signupService->completeSignup($this->signup);

            // Update subscription with provider IDs if available
            $this->updateSubscriptionWithProviderData($result['tenant']->id);

            // Send welcome email
            $this->sendWelcomeEmail($result['customer'], $result['tenant']);

            Log::info('CompleteSignupJob: Signup completed successfully', [
                'signup_id' => $this->signup->id,
                'customer_id' => $result['customer']->id,
                'tenant_id' => $result['tenant']->id,
            ]);
        } catch (\Throwable $e) {
            // Mark signup as failed
            $this->signup->markAsFailed($e->getMessage());

            Log::error('CompleteSignupJob: Failed to complete signup', [
                'signup_id' => $this->signup->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Update subscription record with provider-specific IDs.
     */
    protected function updateSubscriptionWithProviderData(string $tenantId): void
    {
        if (empty($this->paymentData)) {
            return;
        }

        $subscription = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (! $subscription) {
            return;
        }

        $updates = [];

        if (isset($this->paymentData['provider_customer_id'])) {
            $updates['provider_customer_id'] = $this->paymentData['provider_customer_id'];
        }

        if (isset($this->paymentData['provider_subscription_id'])) {
            $updates['provider_subscription_id'] = $this->paymentData['provider_subscription_id'];
        }

        if (! empty($updates)) {
            $subscription->update($updates);

            Log::info('CompleteSignupJob: Updated subscription with provider data', [
                'subscription_id' => $subscription->id,
                'updates' => $updates,
            ]);
        }
    }

    /**
     * Send welcome email to the new customer.
     */
    protected function sendWelcomeEmail($customer, $tenant): void
    {
        try {
            Mail::to($customer->email)->queue(new SignupWelcome(
                customerName: $customer->name,
                tenantName: $tenant->name,
                tenantUrl: $tenant->url(),
                planName: $this->signup->plan?->name ?? 'Standard',
            ));

            Log::info('CompleteSignupJob: Welcome email queued', [
                'customer_id' => $customer->id,
                'email' => $customer->email,
            ]);
        } catch (\Throwable $e) {
            // Log but don't fail the job for email errors
            Log::warning('CompleteSignupJob: Failed to queue welcome email', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CompleteSignupJob failed permanently', [
            'signup_id' => $this->signup->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Mark signup as failed if not already
        if (! $this->signup->isFailed()) {
            $this->signup->markAsFailed('Job failed: '.$exception->getMessage());
        }
    }
}
