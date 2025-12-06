<?php

namespace App\Services\Tenant;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Support\Collection;
use Laravel\Cashier\Subscription;
use Symfony\Component\HttpFoundation\Response;

/**
 * BillingService
 *
 * Handles all business logic for billing operations in tenant context.
 * Integrates with Laravel Cashier for Stripe billing.
 */
class BillingService
{
    /**
     * Get billing overview for tenant.
     *
     * @return array{plans: array, subscription: array|null, invoices: Collection}
     */
    public function getBillingOverview(Tenant $tenant): array
    {
        return [
            'plans' => $this->getPlansForDisplay(),
            'subscription' => $this->formatSubscription($tenant->subscription('default')),
            'invoices' => $this->getRecentInvoices($tenant),
        ];
    }

    /**
     * Get all active plans formatted for display.
     *
     * @return array<string, array>
     */
    public function getPlansForDisplay(): array
    {
        return Plan::active()
            ->ordered()
            ->get()
            ->map(fn (Plan $plan) => [
                'slug' => $plan->slug,
                'name' => $plan->trans('name'),
                'price' => $plan->formatted_price,
                'price_id' => $plan->stripe_price_id,
                'interval' => $plan->billing_period,
                'features' => collect($plan->features)->filter()->keys()->toArray(),
                'limits' => $plan->limits,
            ])
            ->keyBy('slug')
            ->toArray();
    }

    /**
     * Format subscription data for frontend.
     *
     * @return array<string, mixed>|null
     */
    public function formatSubscription(?Subscription $subscription): ?array
    {
        if (! $subscription) {
            return null;
        }

        return [
            'name' => $subscription->stripe_price,
            'status' => $subscription->stripe_status,
            'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
            'ends_at' => $subscription->ends_at?->toDateString(),
            'on_trial' => $subscription->onTrial(),
            'on_grace_period' => $subscription->onGracePeriod(),
            'canceled' => $subscription->canceled(),
        ];
    }

    /**
     * Get recent invoices for tenant.
     *
     * @return Collection<int, array>
     */
    public function getRecentInvoices(Tenant $tenant): Collection
    {
        return $tenant->invoices()->map(fn ($invoice) => [
            'id' => $invoice->id,
            'date' => $invoice->date()->toFormattedDateString(),
            'total' => $invoice->total(),
            'download_url' => route('tenant.admin.billing.invoice', $invoice->id),
        ]);
    }

    /**
     * Get detailed invoices for invoices page.
     *
     * @return Collection<int, array>
     */
    public function getDetailedInvoices(Tenant $tenant): Collection
    {
        return $tenant->invoices()->map(fn ($invoice) => [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'date' => $invoice->date()->toISOString(),
            'date_formatted' => $invoice->date()->toFormattedDateString(),
            'due_date' => $invoice->dueDate()?->toISOString(),
            'total' => $invoice->total(),
            'status' => $invoice->status,
            'paid' => $invoice->status === 'paid',
            'download_url' => route('tenant.admin.billing.invoice', $invoice->id),
            'lines' => collect($invoice->invoiceLineItems())->map(fn ($line) => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'amount' => '$'.number_format($line->amount / 100, 2),
            ])->toArray(),
        ]);
    }

    /**
     * Create checkout session for plan subscription.
     *
     * @return \Laravel\Cashier\Checkout
     */
    public function createCheckout(Tenant $tenant, string $planSlug): mixed
    {
        $plan = Plan::where('slug', $planSlug)->firstOrFail();
        $priceId = $plan->stripe_price_id;

        return $tenant->newSubscription('default', $priceId)
            ->trialDays(14)
            ->checkout([
                'locale' => stripe_locale(),
                'success_url' => route('tenant.admin.billing.success'),
                'cancel_url' => route('tenant.admin.billing.index'),
            ]);
    }

    /**
     * Handle successful checkout callback.
     */
    public function handleSuccessfulCheckout(Tenant $tenant): void
    {
        $subscription = $tenant->subscription('default');

        if (! $subscription) {
            return;
        }

        $priceId = $subscription->stripe_price;
        $plan = Plan::where('stripe_price_id', $priceId)->first();

        if ($plan) {
            $tenant->update(['max_users' => $plan->limits['max_users'] ?? null]);
            $tenant->updateSetting('limits', $plan->limits);
        }
    }

    /**
     * Get URL to Stripe billing portal.
     */
    public function getPortalUrl(Tenant $tenant): string
    {
        return $tenant->billingPortalUrl(route('tenant.admin.billing.index'));
    }

    /**
     * Redirect to billing portal.
     */
    public function redirectToPortal(Tenant $tenant): Response
    {
        return $tenant->redirectToBillingPortal(
            route('tenant.admin.billing.index'),
            ['locale' => stripe_locale()]
        );
    }

    /**
     * Download invoice PDF.
     */
    public function downloadInvoice(Tenant $tenant, string $invoiceId): Response
    {
        return $tenant->downloadInvoice($invoiceId, [
            'vendor' => config('app.name'),
            'product' => 'Subscription',
        ]);
    }

    /**
     * Get plan by slug.
     */
    public function getPlanBySlug(string $slug): ?Plan
    {
        return Plan::where('slug', $slug)->first();
    }

    /**
     * Check if tenant has active subscription.
     */
    public function hasActiveSubscription(Tenant $tenant): bool
    {
        return $tenant->subscribed('default');
    }

    /**
     * Check if tenant is on trial.
     */
    public function isOnTrial(Tenant $tenant): bool
    {
        $subscription = $tenant->subscription('default');

        return $subscription && $subscription->onTrial();
    }
}
