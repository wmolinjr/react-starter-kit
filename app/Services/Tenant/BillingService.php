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
     * Get comprehensive billing dashboard data.
     *
     * @return array{
     *     plan: Plan|null,
     *     subscription: array|null,
     *     usage: array,
     *     costs: array|null,
     *     nextInvoice: array|null,
     *     activeAddons: array,
     *     activeBundles: array,
     *     recentInvoices: \Illuminate\Support\Collection,
     *     trialEndsAt: string|null
     * }
     */
    public function getDashboardData(Tenant $tenant): array
    {
        $plan = $tenant->plan;
        $subscription = $tenant->subscription('default');

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
            'name' => $subscription->name,
            'stripePrice' => $subscription->stripe_price,
            'stripeStatus' => $subscription->stripe_status,
            'status' => $subscription->stripe_status,
            'quantity' => $subscription->quantity ?? 1,
            'trialEndsAt' => $subscription->trial_ends_at?->toISOString(),
            'endsAt' => $subscription->ends_at?->toISOString(),
            'startedAt' => $subscription->created_at?->toISOString(),
            'currentPeriodStart' => null,
            'currentPeriodEnd' => null,
            'cancelAtPeriodEnd' => $subscription->ends_at !== null,
            'onTrial' => $subscription->onTrial(),
            'onGracePeriod' => $subscription->onGracePeriod(),
            'canceled' => $subscription->canceled(),
            'active' => $subscription->active(),
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
        $bundlesCost = 0; // Bundles are tracked as addons with discounted prices
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
        try {
            $upcomingInvoice = $tenant->upcomingInvoice();

            if (! $upcomingInvoice) {
                return null;
            }

            return [
                'total' => $upcomingInvoice->total(),
                'formattedTotal' => $upcomingInvoice->total(),
                'dueDate' => $upcomingInvoice->dueDate()?->toISOString(),
                'formattedDueDate' => $upcomingInvoice->dueDate()?->toFormattedDateString(),
                'items' => collect($upcomingInvoice->invoiceLineItems())->map(fn ($line) => [
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
            ->whereNull('metadata->bundle_slug') // Exclude bundle addons
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
