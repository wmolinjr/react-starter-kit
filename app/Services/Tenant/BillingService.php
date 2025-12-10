<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Central\Customer;
use App\Models\Central\Payment;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

/**
 * BillingService
 *
 * Handles all business logic for billing operations in tenant context.
 * Integrates with the multi-provider payment gateway system.
 */
class BillingService
{
    protected ?StripeClient $stripe = null;

    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {
        $secret = config('payment.drivers.stripe.secret');
        if ($secret) {
            $this->stripe = new StripeClient($secret);
        }
    }

    /**
     * Get billing overview for tenant.
     *
     * @return array{plans: array, subscription: array|null, invoices: Collection}
     */
    public function getBillingOverview(Tenant $tenant): array
    {
        return [
            'plans' => $this->getPlansForDisplay(),
            'subscription' => $this->formatSubscription($this->getTenantSubscription($tenant)),
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
                'name' => $plan->name,
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
            'name' => $subscription->provider_price_id,
            'status' => $subscription->status,
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
        $customer = $tenant->customer;

        if (! $customer) {
            return collect();
        }

        return Payment::where('customer_id', $customer->id)
            ->where('status', 'succeeded')
            ->orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'date' => $payment->created_at->toFormattedDateString(),
                'total' => '$'.number_format($payment->amount / 100, 2),
                'download_url' => $payment->provider_invoice_id
                    ? route('tenant.admin.billing.invoice', $payment->id)
                    : null,
            ]);
    }

    /**
     * Get detailed invoices for invoices page.
     *
     * @return Collection<int, array>
     */
    public function getDetailedInvoices(Tenant $tenant): Collection
    {
        $customer = $tenant->customer;

        if (! $customer) {
            return collect();
        }

        return Payment::where('customer_id', $customer->id)
            ->whereIn('status', ['succeeded', 'pending'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'number' => $payment->provider_payment_id,
                'date' => $payment->created_at->toISOString(),
                'date_formatted' => $payment->created_at->toFormattedDateString(),
                'due_date' => null,
                'total' => '$'.number_format($payment->amount / 100, 2),
                'status' => $payment->status,
                'paid' => $payment->status === 'succeeded',
                'download_url' => $payment->provider_invoice_id
                    ? route('tenant.admin.billing.invoice', $payment->id)
                    : null,
                'lines' => [],
            ]);
    }

    /**
     * Create checkout session for plan subscription.
     */
    public function createCheckout(Tenant $tenant, string $planSlug): array
    {
        $plan = Plan::where('slug', $planSlug)->firstOrFail();
        $priceId = $plan->stripe_price_id;

        $customer = $tenant->customer;
        if (! $customer) {
            throw new \RuntimeException('Tenant has no associated customer for billing');
        }

        $stripeCustomerId = $this->ensureStripeCustomer($customer);

        $sessionParams = [
            'customer' => $stripeCustomerId,
            'mode' => 'subscription',
            'locale' => stripe_locale(),
            'success_url' => route('tenant.admin.billing.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('tenant.admin.billing.index'),
            'metadata' => [
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'plan_slug' => $planSlug,
            ],
            'subscription_data' => [
                'trial_period_days' => 14,
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'plan_slug' => $planSlug,
                ],
            ],
            'line_items' => [
                ['price' => $priceId, 'quantity' => 1],
            ],
        ];

        try {
            $session = $this->stripe->checkout->sessions->create($sessionParams);

            return [
                'session_id' => $session->id,
                'url' => $session->url,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create checkout session', [
                'tenant_id' => $tenant->id,
                'plan_slug' => $planSlug,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to create checkout session: '.$e->getMessage());
        }
    }

    /**
     * Handle successful checkout callback.
     */
    public function handleSuccessfulCheckout(Tenant $tenant): void
    {
        $subscription = $this->getTenantSubscription($tenant);

        if (! $subscription) {
            return;
        }

        $priceId = $subscription->provider_price_id;
        $plan = Plan::where('stripe_price_id', $priceId)->first();

        if ($plan) {
            $tenant->update(['max_users' => $plan->limits['max_users'] ?? null]);
            $tenant->updateSetting('limits', $plan->limits);
        }
    }

    /**
     * Get URL to Stripe billing portal.
     */
    public function getPortalUrl(Tenant $tenant): ?string
    {
        $customer = $tenant->customer;
        if (! $customer) {
            return null;
        }

        $stripeId = $customer->getProviderId('stripe');
        if (! $stripeId) {
            return null;
        }

        try {
            $session = $this->stripe->billingPortal->sessions->create([
                'customer' => $stripeId,
                'return_url' => route('tenant.admin.billing.index'),
                'locale' => stripe_locale(),
            ]);

            return $session->url;
        } catch (\Exception $e) {
            Log::error('Failed to create billing portal session', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Redirect to billing portal.
     */
    public function redirectToPortal(Tenant $tenant): \Illuminate\Http\RedirectResponse
    {
        $url = $this->getPortalUrl($tenant);

        if (! $url) {
            return redirect()->route('tenant.admin.billing.index')
                ->with('error', __('billing.portal_unavailable'));
        }

        return redirect($url);
    }

    /**
     * Download invoice PDF.
     */
    public function downloadInvoice(Tenant $tenant, string $paymentId): mixed
    {
        $payment = Payment::findOrFail($paymentId);

        if (! $payment->provider_invoice_id) {
            abort(404, 'Invoice not found');
        }

        try {
            $invoice = $this->stripe->invoices->retrieve($payment->provider_invoice_id);

            if ($invoice->invoice_pdf) {
                return redirect($invoice->invoice_pdf);
            }

            abort(404, 'Invoice PDF not available');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve invoice', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            abort(500, 'Failed to retrieve invoice');
        }
    }

    /**
     * Get plan by slug.
     */
    public function getPlanBySlug(string $slug): ?Plan
    {
        return Plan::where('slug', $slug)->first();
    }

    /**
     * Get comprehensive billing dashboard data.
     */
    public function getDashboardData(Tenant $tenant): array
    {
        $plan = $tenant->plan;
        $subscription = $this->getTenantSubscription($tenant);

        return [
            'plan' => $plan,
            'subscription' => $this->formatSubscriptionInfo($subscription),
            'usage' => $this->calculateUsageMetrics($tenant),
            'costs' => $this->calculateCostBreakdown($tenant),
            'nextInvoice' => $this->getNextInvoicePreview($tenant),
            'activeAddons' => $this->getActiveAddonsInfo($tenant),
            'activeBundles' => $this->getActiveBundlesInfo($tenant),
            'recentInvoices' => $this->getRecentInvoices($tenant),
            'trialEndsAt' => $subscription?->trial_ends_at?->toISOString(),
        ];
    }

    /**
     * Format subscription info for frontend.
     */
    protected function formatSubscriptionInfo(?Subscription $subscription): ?array
    {
        if (! $subscription) {
            return null;
        }

        return [
            'id' => $subscription->id,
            'name' => $subscription->type,
            'stripePrice' => $subscription->provider_price_id,
            'stripeStatus' => $subscription->status,
            'status' => $subscription->status,
            'quantity' => $subscription->quantity,
            'trialEndsAt' => $subscription->trial_ends_at?->toISOString(),
            'endsAt' => $subscription->ends_at?->toISOString(),
            'startedAt' => $subscription->created_at?->toISOString(),
            'currentPeriodStart' => $subscription->current_period_start?->toISOString(),
            'currentPeriodEnd' => $subscription->current_period_end?->toISOString(),
            'cancelAtPeriodEnd' => $subscription->ends_at !== null,
            'onTrial' => $subscription->onTrial(),
            'onGracePeriod' => $subscription->onGracePeriod(),
            'canceled' => $subscription->canceled(),
            'active' => $subscription->isActive(),
        ];
    }

    /**
     * Calculate usage metrics for all limits.
     */
    protected function calculateUsageMetrics(Tenant $tenant): array
    {
        $limits = $tenant->getEffectiveLimits();
        $usage = [];

        // Users
        $currentUsers = \App\Models\Tenant\User::count();
        $maxUsers = $limits['max_users'] ?? null;
        $usage['users'] = $this->buildUsageMetric(
            'users',
            __('billing.limits.users'),
            $currentUsers,
            $maxUsers
        );

        // Projects
        $currentProjects = \App\Models\Tenant\Project::count();
        $maxProjects = $limits['max_projects'] ?? null;
        $usage['projects'] = $this->buildUsageMetric(
            'projects',
            __('billing.limits.projects'),
            $currentProjects,
            $maxProjects
        );

        // Storage (in MB)
        $currentStorageMb = $this->calculateStorageUsage($tenant);
        $maxStorageMb = $limits['max_storage_mb'] ?? null;
        $usage['storage'] = $this->buildUsageMetric(
            'storage',
            __('billing.limits.storage'),
            $currentStorageMb,
            $maxStorageMb,
            'MB'
        );

        return $usage;
    }

    /**
     * Build a usage metric array.
     */
    protected function buildUsageMetric(
        string $key,
        string $label,
        int $used,
        ?int $limit,
        string $unit = ''
    ): array {
        $isUnlimited = $limit === null || $limit === -1;
        $percentage = $isUnlimited ? 0 : ($limit > 0 ? round(($used / $limit) * 100, 1) : 0);

        return [
            'key' => $key,
            'label' => $label,
            'used' => $used,
            'limit' => $limit,
            'percentage' => $percentage,
            'formattedUsed' => $unit ? "{$used} {$unit}" : (string) $used,
            'formattedLimit' => $isUnlimited ? '∞' : ($unit ? "{$limit} {$unit}" : (string) $limit),
            'isUnlimited' => $isUnlimited,
            'isNearLimit' => ! $isUnlimited && $percentage >= 80 && $percentage < 100,
            'isOverLimit' => ! $isUnlimited && $percentage >= 100,
        ];
    }

    /**
     * Calculate storage usage in MB.
     */
    protected function calculateStorageUsage(Tenant $tenant): int
    {
        if (! class_exists(\Spatie\MediaLibrary\MediaCollections\Models\Media::class)) {
            return 0;
        }

        $totalBytes = \App\Models\Tenant\Media::sum('size') ?? 0;

        return (int) round($totalBytes / (1024 * 1024));
    }

    /**
     * Calculate cost breakdown.
     */
    protected function calculateCostBreakdown(Tenant $tenant): ?array
    {
        $plan = $tenant->plan;

        if (! $plan) {
            return null;
        }

        $planCost = $plan->price ?? 0;
        $addonsCost = $tenant->activeAddons->sum('price') ?? 0;
        $bundlesCost = 0;
        $totalCost = $planCost + $addonsCost;

        $currency = $plan->currency ?? 'USD';
        $formatter = fn ($amount) => '$'.number_format($amount / 100, 2);

        return [
            'planCost' => $planCost,
            'addonsCost' => $addonsCost,
            'bundlesCost' => $bundlesCost,
            'totalMonthlyCost' => $totalCost,
            'formattedPlanCost' => $formatter($planCost),
            'formattedAddonsCost' => $formatter($addonsCost),
            'formattedBundlesCost' => $formatter($bundlesCost),
            'formattedTotal' => $formatter($totalCost),
            'currency' => $currency,
        ];
    }

    /**
     * Get next invoice preview.
     */
    protected function getNextInvoicePreview(Tenant $tenant): ?array
    {
        $customer = $tenant->customer;
        if (! $customer) {
            return null;
        }

        $stripeId = $customer->getProviderId('stripe');
        if (! $stripeId) {
            return null;
        }

        try {
            $upcomingInvoice = $this->stripe->invoices->upcoming([
                'customer' => $stripeId,
            ]);

            return [
                'total' => '$'.number_format($upcomingInvoice->total / 100, 2),
                'formattedTotal' => '$'.number_format($upcomingInvoice->total / 100, 2),
                'dueDate' => $upcomingInvoice->due_date
                    ? \Carbon\Carbon::createFromTimestamp($upcomingInvoice->due_date)->toISOString()
                    : null,
                'formattedDueDate' => $upcomingInvoice->due_date
                    ? \Carbon\Carbon::createFromTimestamp($upcomingInvoice->due_date)->toFormattedDateString()
                    : null,
                'items' => collect($upcomingInvoice->lines->data)->map(fn ($line) => [
                    'description' => $line->description,
                    'amount' => '$'.number_format($line->amount / 100, 2),
                ])->toArray(),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get active addons info.
     */
    protected function getActiveAddonsInfo(Tenant $tenant): array
    {
        return $tenant->activeAddons()
            ->whereNull('metadata->bundle_slug')
            ->get()
            ->map(fn ($addon) => [
                'id' => $addon->id,
                'slug' => $addon->addon_slug,
                'name' => $addon->name,
                'description' => $addon->description,
                'price' => $addon->price,
                'formattedPrice' => '$'.number_format($addon->price / 100, 2),
                'quantity' => $addon->quantity,
                'billingPeriod' => $addon->billing_period->value,
                'status' => $addon->status->value,
                'startedAt' => $addon->started_at?->toISOString(),
            ])
            ->toArray();
    }

    /**
     * Get active bundles info.
     */
    protected function getActiveBundlesInfo(Tenant $tenant): array
    {
        return $tenant->activeAddons()
            ->whereNotNull('metadata->bundle_slug')
            ->get()
            ->groupBy(fn ($addon) => $addon->metadata['bundle_purchase_id'] ?? 'unknown')
            ->map(function ($addons, $purchaseId) {
                $first = $addons->first();
                $totalPrice = $addons->sum('price');

                return [
                    'id' => $purchaseId,
                    'slug' => $first->metadata['bundle_slug'] ?? null,
                    'name' => $first->metadata['bundle_name'] ?? 'Bundle',
                    'price' => $totalPrice,
                    'formattedPrice' => '$'.number_format($totalPrice / 100, 2),
                    'addonCount' => $addons->count(),
                    'addons' => $addons->map(fn ($addon) => [
                        'name' => $addon->name,
                        'quantity' => $addon->quantity,
                    ])->toArray(),
                    'startedAt' => $first->started_at?->toISOString(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Check if tenant has active subscription.
     */
    public function hasActiveSubscription(Tenant $tenant): bool
    {
        $subscription = $this->getTenantSubscription($tenant);

        return $subscription && $subscription->isActive();
    }

    /**
     * Check if tenant is on trial.
     */
    public function isOnTrial(Tenant $tenant): bool
    {
        $subscription = $this->getTenantSubscription($tenant);

        return $subscription && $subscription->onTrial();
    }

    /**
     * Get tenant's active subscription.
     */
    protected function getTenantSubscription(Tenant $tenant): ?Subscription
    {
        $customer = $tenant->customer;
        if (! $customer) {
            return null;
        }

        return Subscription::where('customer_id', $customer->id)
            ->where('tenant_id', $tenant->id)
            ->where('type', 'default')
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->first();
    }

    /**
     * Ensure customer has a Stripe customer ID.
     */
    protected function ensureStripeCustomer(Customer $customer): string
    {
        $stripeId = $customer->getProviderId('stripe');

        if ($stripeId) {
            return $stripeId;
        }

        try {
            $stripeCustomer = $this->stripe->customers->create([
                'email' => $customer->email,
                'name' => $customer->name ?? $customer->email,
                'metadata' => [
                    'customer_id' => $customer->id,
                ],
            ]);

            $customer->setProviderId('stripe', $stripeCustomer->id);

            return $stripeCustomer->id;
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe customer', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to create payment customer: '.$e->getMessage());
        }
    }
}
