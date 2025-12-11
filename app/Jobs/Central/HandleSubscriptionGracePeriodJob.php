<?php

declare(strict_types=1);

namespace App\Jobs\Central;

use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * HandleSubscriptionGracePeriodJob
 *
 * Handles subscriptions that have reached the end of their grace period.
 * - Downgrades tenant to free plan
 * - Updates subscription status to canceled
 * - Removes premium features/limits
 *
 * Should be scheduled to run daily.
 */
class HandleSubscriptionGracePeriodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('HandleSubscriptionGracePeriodJob started');

        $expiredCount = 0;
        $errorCount = 0;

        // Find subscriptions where grace period has ended
        $expiredSubscriptions = Subscription::query()
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->with(['tenant', 'customer'])
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            try {
                $this->processExpiredSubscription($subscription);
                $expiredCount++;
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Failed to process expired subscription', [
                    'subscription_id' => $subscription->id,
                    'tenant_id' => $subscription->tenant_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('HandleSubscriptionGracePeriodJob completed', [
            'processed' => $expiredCount,
            'errors' => $errorCount,
        ]);
    }

    /**
     * Process a single expired subscription.
     */
    protected function processExpiredSubscription(Subscription $subscription): void
    {
        $tenant = $subscription->tenant;

        if (! $tenant) {
            Log::warning('Subscription has no tenant', [
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        Log::info('Processing expired subscription', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'ends_at' => $subscription->ends_at,
        ]);

        // Update subscription status
        $subscription->update([
            'status' => 'canceled',
        ]);

        // Downgrade tenant to free plan if exists
        $freePlan = Plan::where('slug', 'free')
            ->orWhere('price', 0)
            ->first();

        if ($freePlan) {
            $tenant->update([
                'plan_id' => $freePlan->id,
            ]);

            // Update tenant limits to free plan limits
            $tenant->updateSetting('limits', $freePlan->limits ?? []);

            Log::info('Tenant downgraded to free plan', [
                'tenant_id' => $tenant->id,
                'plan_id' => $freePlan->id,
            ]);
        } else {
            // No free plan, just remove plan
            $tenant->update([
                'plan_id' => null,
            ]);

            // Reset limits to defaults
            $tenant->updateSetting('limits', [
                'max_users' => 1,
                'max_projects' => 1,
                'max_storage_mb' => 100,
            ]);

            Log::info('Tenant plan removed (no free plan available)', [
                'tenant_id' => $tenant->id,
            ]);
        }

        // Cancel any active addon subscriptions
        $this->cancelAddonSubscriptions($tenant);

        // Fire event for additional handling
        event(new \App\Events\Central\SubscriptionExpired($subscription, $tenant));
    }

    /**
     * Cancel addon subscriptions for the tenant.
     */
    protected function cancelAddonSubscriptions(Tenant $tenant): void
    {
        $tenant->activeAddons()
            ->where('billing_period', '!=', 'one_time')
            ->update([
                'status' => 'canceled',
                'ends_at' => now(),
            ]);

        Log::info('Addon subscriptions canceled', [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Determine the number of seconds before this job should be retried.
     */
    public function backoff(): array
    {
        return [60, 300, 600];
    }
}
