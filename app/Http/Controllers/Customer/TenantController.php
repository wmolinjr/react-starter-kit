<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\PlanResource;
use App\Http\Resources\Central\TenantDetailResource;
use App\Http\Resources\Central\TenantSummaryResource;
use App\Models\Central\AddonPurchase;
use App\Models\Central\PendingSignup;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Central\CustomerService;
use App\Services\Central\PaymentSettingsService;
use App\Services\Central\SignupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function __construct(
        protected CustomerService $customerService,
        protected SignupService $signupService,
        protected PaymentSettingsService $paymentSettingsService
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
     * Step 1: Workspace details + Plan selection
     */
    public function create(Request $request): Response
    {
        $customer = $request->user('customer');

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('central/customer/tenants/create', [
            'hasPaymentMethod' => $customer->hasDefaultPaymentMethod(),
            'plans' => PlanResource::collection($plans),
        ]);
    }

    /**
     * Store a newly created tenant (creates PendingSignup and redirects to checkout).
     */
    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:63', 'alpha_dash', 'unique:tenants,slug'],
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'billing_period' => ['required', 'in:monthly,yearly'],
        ]);

        // Create PendingSignup for existing customer
        $signup = $this->signupService->createPendingSignupForCustomer($customer);

        // Update with workspace data
        $this->signupService->updateWorkspace($signup, [
            'workspace_name' => $validated['name'],
            'workspace_slug' => $validated['slug'],
            'business_sector' => 'other',
            'plan_id' => $validated['plan_id'],
            'billing_period' => $validated['billing_period'],
        ]);

        return redirect()->route('central.account.tenants.checkout', $signup);
    }

    /**
     * Show checkout page for tenant creation.
     */
    public function checkout(Request $request, PendingSignup $signup): Response
    {
        $customer = $request->user('customer');

        // Verify ownership
        if ($signup->customer_id !== $customer->id) {
            abort(403, 'You do not have access to this signup.');
        }

        if ($signup->isExpired()) {
            return Inertia::render('central/customer/tenants/checkout-expired');
        }

        $signup->load('plan');

        return Inertia::render('central/customer/tenants/checkout', [
            'signup' => [
                'id' => $signup->id,
                'workspace_name' => $signup->workspace_name,
                'workspace_slug' => $signup->workspace_slug,
                'billing_period' => $signup->billing_period,
                'plan' => $signup->plan ? new PlanResource($signup->plan) : null,
            ],
            'paymentConfig' => $this->paymentSettingsService->getPaymentConfig(),
        ]);
    }

    /**
     * Process checkout payment.
     */
    public function processCheckout(Request $request, PendingSignup $signup): RedirectResponse|JsonResponse
    {
        $customer = $request->user('customer');

        // Verify ownership
        if ($signup->customer_id !== $customer->id) {
            abort(403, 'You do not have access to this signup.');
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'in:card,pix,boleto'],
        ]);

        try {
            $result = $this->signupService->processPayment($signup, $validated['payment_method']);

            // Card payment - redirect to Stripe
            if ($result['type'] === 'redirect') {
                return redirect()->away($result['url']);
            }

            // Async payment (PIX/Boleto) - return JSON for frontend handling
            return response()->json($result);
        } catch (\Exception $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }
    }

    /**
     * Show checkout success page.
     */
    public function checkoutSuccess(Request $request, PendingSignup $signup): Response|RedirectResponse
    {
        $customer = $request->user('customer');

        // Verify ownership
        if ($signup->customer_id !== $customer->id) {
            abort(403, 'You do not have access to this signup.');
        }

        // If signup is completed, find the tenant and redirect
        if ($signup->isCompleted()) {
            $tenant = Tenant::where('slug', $signup->workspace_slug)->first();
            if ($tenant) {
                return redirect()->route('central.account.tenants.show', $tenant)
                    ->with('status', 'tenant-created');
            }
        }

        return Inertia::render('central/customer/tenants/checkout-success', [
            'signup' => [
                'id' => $signup->id,
                'workspace_name' => $signup->workspace_name,
                'status' => $signup->status,
            ],
        ]);
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
