<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CartCheckoutRequest;
use App\Http\Requests\Tenant\CheckoutRequest;
use App\Http\Resources\Central\BundleResource;
use App\Http\Resources\Central\PlanResource;
use App\Http\Resources\Tenant\InvoiceDetailResource;
use App\Http\Resources\Tenant\InvoiceResource;
use App\Models\Central\Plan;
use App\Services\Central\AddonService;
use App\Services\Central\CartCheckoutService;
use App\Services\Tenant\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BillingController extends Controller implements HasMiddleware
{
    public function __construct(
        protected BillingService $billingService,
        protected AddonService $addonService,
        protected CartCheckoutService $cartCheckoutService
    ) {}

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:'.TenantPermission::BILLING_VIEW->value, only: ['index', 'success', 'cartSuccess', 'plans', 'bundles', 'subscription']),
            new Middleware('permission:'.TenantPermission::BILLING_MANAGE->value, only: ['checkout', 'portal', 'cartCheckout', 'checkCartPaymentStatus', 'refreshPixQrCode', 'cancelSubscription', 'resumeSubscription', 'pauseSubscription', 'unpauseSubscription', 'changePlan']),
            new Middleware('permission:'.TenantPermission::BILLING_INVOICES->value, only: ['invoices', 'invoice']),
        ];
    }

    /**
     * Display billing dashboard.
     */
    public function index(): Response
    {
        $tenant = tenant();
        $data = $this->billingService->getDashboardData($tenant);

        return Inertia::render('tenant/admin/billing/index', [
            'plan' => $data['plan'] ? new PlanResource($data['plan']) : null,
            'subscription' => $data['subscription'],
            'usage' => $data['usage'],
            'costs' => $data['costs'],
            'nextInvoice' => $data['nextInvoice'],
            'activeAddons' => $data['activeAddons'],
            'activeBundles' => $data['activeBundles'],
            'recentInvoices' => InvoiceResource::collection($data['recentInvoices']),
            'trialEndsAt' => $data['trialEndsAt'],
        ]);
    }

    /**
     * Display subscription management page.
     */
    public function subscription(): Response
    {
        $tenant = tenant();
        $subscription = $this->billingService->formatSubscription($tenant->subscription('default'));
        $currentPlan = $tenant->plan;

        $plans = Plan::active()
            ->ordered()
            ->get();

        // Determine available actions
        $hasActiveSubscription = $subscription && in_array($subscription['status'], ['active', 'trialing']);
        $isPaused = $subscription && $subscription['status'] === 'paused';
        $isOnGracePeriod = $subscription && $subscription['ends_at'] !== null;

        return Inertia::render('tenant/admin/billing/subscription', [
            'subscription' => $subscription,
            'plan' => $currentPlan ? new PlanResource($currentPlan) : null,
            'plans' => PlanResource::collection($plans),
            'canPause' => $hasActiveSubscription && ! $isPaused,
            'canResume' => $isOnGracePeriod,
            'canCancel' => $hasActiveSubscription && ! $isOnGracePeriod,
            'canChangePlan' => $hasActiveSubscription,
        ]);
    }

    /**
     * Display plans comparison page.
     */
    public function plans(): Response
    {
        $tenant = tenant();
        $currentPlan = $tenant->plan;

        $plans = Plan::active()
            ->ordered()
            ->get();

        return Inertia::render('tenant/admin/billing/plans', [
            'plans' => PlanResource::collection($plans),
            'currentPlan' => $currentPlan ? new PlanResource($currentPlan) : null,
            'subscription' => $this->billingService->formatSubscription($tenant->subscription('default')),
        ]);
    }

    /**
     * Display bundles catalog page.
     */
    public function bundles(): Response
    {
        $tenant = tenant();
        $bundles = $this->addonService->getAvailableBundles($tenant);
        $activeBundles = $this->addonService->getActiveBundles($tenant);

        // Get tenant's active addon slugs to detect conflicts
        $activeAddonSlugs = $tenant->activeAddons()->pluck('addon_slug')->toArray();

        return Inertia::render('tenant/admin/billing/bundles', [
            'bundles' => BundleResource::collection($bundles),
            'activeBundles' => $activeBundles,
            'activeAddonSlugs' => $activeAddonSlugs,
            'currentPlan' => $tenant->plan ? new PlanResource($tenant->plan) : null,
        ]);
    }

    /**
     * Start checkout process.
     */
    public function checkout(CheckoutRequest $request): HttpResponse
    {
        $checkout = $this->billingService->createCheckout(tenant(), $request->validated()['plan']);

        return Inertia::location($checkout->url());
    }

    /**
     * Checkout success callback.
     */
    public function success(): RedirectResponse
    {
        $this->billingService->handleSuccessfulCheckout(tenant());

        return redirect()->route('tenant.admin.billing.index')
            ->with('success', __('flash.billing.subscription_activated'));
    }

    /**
     * Redirect to customer portal.
     */
    public function portal(): HttpResponse
    {
        return $this->billingService->redirectToPortal(tenant());
    }

    /**
     * List all invoices.
     */
    public function invoices(): Response
    {
        return Inertia::render('tenant/admin/billing/invoices', [
            'invoices' => InvoiceDetailResource::collection($this->billingService->getDetailedInvoices(tenant())),
        ]);
    }

    /**
     * Download invoice.
     */
    public function invoice(string $invoiceId): HttpResponse
    {
        return $this->billingService->downloadInvoice(tenant(), $invoiceId);
    }

    /**
     * Process cart checkout with multi-payment support.
     *
     * For card: Returns redirect to Stripe Checkout
     * For pix/boleto: Returns JSON with payment data
     */
    public function cartCheckout(CartCheckoutRequest $request): HttpResponse|JsonResponse
    {
        $validated = $request->validated();
        $paymentMethod = $validated['payment_method'] ?? 'card';

        $result = $this->cartCheckoutService->processCartCheckout(
            tenant(),
            $validated['items'],
            $paymentMethod
        );

        // For card payments, redirect to Stripe Checkout
        if ($result['type'] === 'redirect') {
            return Inertia::location($result['url']);
        }

        // For PIX/Boleto, return JSON response for frontend handling
        return response()->json($result);
    }

    /**
     * Check payment status for async payments (PIX/Boleto).
     */
    public function checkCartPaymentStatus(Request $request): JsonResponse
    {
        $request->validate([
            'payment_id' => ['required', 'string'],
        ]);

        $status = $this->cartCheckoutService->checkPaymentStatus($request->payment_id);

        return response()->json($status);
    }

    /**
     * Refresh PIX QR code for an existing payment.
     */
    public function refreshPixQrCode(Request $request): JsonResponse
    {
        $request->validate([
            'payment_id' => ['required', 'string'],
        ]);

        $pixData = $this->cartCheckoutService->refreshPixQrCode($request->payment_id);

        if (! $pixData) {
            return response()->json([
                'error' => __('billing.errors.pix_qr_code_not_found'),
            ], 404);
        }

        return response()->json(['pix' => $pixData]);
    }

    /**
     * Cart checkout success callback.
     */
    public function cartSuccess(): RedirectResponse
    {
        return redirect()->route('tenant.admin.billing.index')
            ->with('success', __('flash.billing.cart_checkout_success'));
    }

    /**
     * Cancel subscription.
     */
    public function cancelSubscription(Request $request): RedirectResponse
    {
        $request->validate([
            'immediately' => ['sometimes', 'boolean'],
        ]);

        try {
            $this->billingService->cancelSubscription(
                tenant(),
                $request->boolean('immediately', false)
            );

            return back()->with('success', __('flash.billing.subscription_canceled'));
        } catch (\Exception $e) {
            return back()->withErrors(['subscription' => $e->getMessage()]);
        }
    }

    /**
     * Resume a canceled subscription.
     */
    public function resumeSubscription(): RedirectResponse
    {
        try {
            $this->billingService->resumeSubscription(tenant());

            return back()->with('success', __('flash.billing.subscription_resumed'));
        } catch (\Exception $e) {
            return back()->withErrors(['subscription' => $e->getMessage()]);
        }
    }

    /**
     * Pause subscription.
     */
    public function pauseSubscription(): RedirectResponse
    {
        try {
            $this->billingService->pauseSubscription(tenant());

            return back()->with('success', __('flash.billing.subscription_paused'));
        } catch (\Exception $e) {
            return back()->withErrors(['subscription' => $e->getMessage()]);
        }
    }

    /**
     * Unpause a paused subscription.
     */
    public function unpauseSubscription(): RedirectResponse
    {
        try {
            $this->billingService->unpauseSubscription(tenant());

            return back()->with('success', __('flash.billing.subscription_resumed'));
        } catch (\Exception $e) {
            return back()->withErrors(['subscription' => $e->getMessage()]);
        }
    }

    /**
     * Change subscription plan.
     */
    public function changePlan(Request $request): RedirectResponse
    {
        $request->validate([
            'plan' => ['required', 'string', 'exists:plans,slug'],
        ]);

        try {
            $this->billingService->changePlan(tenant(), $request->input('plan'));

            return back()->with('success', __('flash.billing.plan_changed'));
        } catch (\Exception $e) {
            return back()->withErrors(['plan' => $e->getMessage()]);
        }
    }
}
