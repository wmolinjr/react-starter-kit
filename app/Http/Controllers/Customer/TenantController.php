<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\TenantDetailResource;
use App\Http\Resources\Central\TenantSummaryResource;
use App\Models\Central\AddonPurchase;
use App\Models\Central\Tenant;
use App\Services\Central\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    /**
     * Display a list of customer's tenants.
     */
    public function index(Request $request): Response
    {
        $customer = $request->user('customer');

        $tenants = $customer->ownedTenants()
            ->with(['domains', 'plan'])
            ->orderBy('name')
            ->get();

        return Inertia::render('central/customer/tenants/index', [
            'tenants' => TenantSummaryResource::collection($tenants),
        ]);
    }

    /**
     * Show the form for creating a new tenant.
     */
    public function create(Request $request): Response
    {
        $customer = $request->user('customer');

        return Inertia::render('central/customer/tenants/create', [
            'hasPaymentMethod' => $customer->hasDefaultPaymentMethod(),
        ]);
    }

    /**
     * Store a newly created tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:63', 'alpha_dash', 'unique:tenants,slug'],
        ]);

        // Build domain from slug
        $domain = $validated['slug'].'.'.config('tenancy.central_domains')[0];

        $tenant = $this->customerService->createTenant($customer, [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'domain' => $domain,
        ]);

        return redirect()->route('central.account.tenants.show', $tenant)
            ->with('status', 'tenant-created');
    }

    /**
     * Display the specified tenant.
     */
    public function show(Request $request, Tenant $tenant): Response
    {
        $this->authorizeCustomerOwnsTenant($request, $tenant);

        $tenant->load(['domains', 'plan', 'customer']);

        // Get subscription info
        $subscription = $tenant->customer?->subscriptionForTenant($tenant);

        return Inertia::render('central/customer/tenants/show', [
            'tenant' => new TenantDetailResource($tenant),
            'subscription' => $subscription ? [
                'status' => $subscription->stripe_status,
                'plan' => $tenant->plan?->name,
                'current_period_end' => $subscription->current_period_end?->toISOString(),
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
            ] : null,
            'users_count' => $tenant->getUserCount(),
        ]);
    }

    /**
     * Display billing information for the tenant.
     */
    public function billing(Request $request, Tenant $tenant): Response
    {
        $this->authorizeCustomerOwnsTenant($request, $tenant);

        $customer = $request->user('customer');
        $tenant->load(['plan']);

        $subscription = $customer->subscriptionForTenant($tenant);
        $paymentMethod = $customer->paymentMethodForTenant($tenant);

        // Get recent invoices for this tenant
        $invoices = collect($customer->invoices())
            ->filter(fn ($invoice) => str_contains($invoice->description ?? '', $tenant->name))
            ->take(5)
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'date' => $invoice->date()->toISOString(),
                'total' => $invoice->total(),
                'status' => $invoice->status,
            ]);

        return Inertia::render('central/customer/tenants/billing', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'plan' => $tenant->plan?->name,
                'payment_method_id' => $tenant->payment_method_id,
            ],
            'subscription' => $subscription ? [
                'status' => $subscription->stripe_status,
                'current_period_end' => $subscription->current_period_end?->toISOString(),
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
            ] : null,
            'payment_method' => $paymentMethod ? [
                'id' => $paymentMethod->id,
                'brand' => $paymentMethod->card->brand,
                'last4' => $paymentMethod->card->last4,
                'exp_month' => $paymentMethod->card->exp_month,
                'exp_year' => $paymentMethod->card->exp_year,
            ] : null,
            'invoices' => $invoices,
            'available_payment_methods' => $customer->paymentMethods()->map(fn ($pm) => [
                'id' => $pm->id,
                'brand' => $pm->card->brand,
                'last4' => $pm->card->last4,
            ]),
        ]);
    }

    /**
     * Update the payment method for a tenant.
     */
    public function updatePaymentMethod(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorizeCustomerOwnsTenant($request, $tenant);

        $validated = $request->validate([
            'payment_method_id' => ['nullable', 'string'],
        ]);

        // If null, use customer default
        $tenant->update([
            'payment_method_id' => $validated['payment_method_id'],
        ]);

        return back()->with('status', 'payment-method-updated');
    }

    /**
     * Authorize that the customer owns the tenant.
     */
    protected function authorizeCustomerOwnsTenant(Request $request, Tenant $tenant): void
    {
        if ($tenant->customer_id !== $request->user('customer')->id) {
            abort(403, 'You do not own this workspace.');
        }
    }

    /**
     * Get the status of a purchase for async payment polling.
     *
     * Used by PIX/Boleto payment components to check if payment was confirmed.
     */
    public function purchaseStatus(Request $request, AddonPurchase $purchase): JsonResponse
    {
        $customer = $request->user('customer');

        // Verify customer owns the tenant associated with this purchase
        $tenant = $purchase->tenant;

        if (! $tenant || $tenant->customer_id !== $customer->id) {
            abort(403, 'You do not have access to this purchase.');
        }

        return response()->json([
            'status' => $purchase->status,
            'completed_at' => $purchase->purchased_at?->toISOString(),
            'failed_at' => $purchase->isFailed() ? $purchase->updated_at->toISOString() : null,
            'failure_reason' => $purchase->failure_reason,
        ]);
    }
}
