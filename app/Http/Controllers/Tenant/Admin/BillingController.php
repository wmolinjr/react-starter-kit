<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CartCheckoutRequest;
use App\Http\Requests\Tenant\CheckoutRequest;
use App\Http\Resources\Central\BundleResource;
use App\Http\Resources\Central\AddonResource;
use App\Http\Resources\Central\PlanResource;
use App\Http\Resources\Tenant\InvoiceDetailResource;
use App\Http\Resources\Tenant\InvoiceResource;
use App\Models\Central\Plan;
use App\Services\Central\AddonService;
use App\Services\Central\CartCheckoutService;
use App\Services\Tenant\BillingService;
use Illuminate\Http\RedirectResponse;
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
            new Middleware('permission:'.TenantPermission::BILLING_VIEW->value, only: ['index', 'success', 'cartSuccess', 'plans', 'bundles']),
            new Middleware('permission:'.TenantPermission::BILLING_MANAGE->value, only: ['checkout', 'portal', 'cartCheckout']),
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
     * Create Stripe checkout session for cart items.
     */
    public function cartCheckout(CartCheckoutRequest $request): HttpResponse
    {
        $result = $this->cartCheckoutService->createCartCheckoutSession(
            tenant(),
            $request->validated()['items']
        );

        return Inertia::location($result['url']);
    }

    /**
     * Cart checkout success callback.
     */
    public function cartSuccess(): RedirectResponse
    {
        return redirect()->route('tenant.admin.billing.index')
            ->with('success', __('flash.billing.cart_checkout_success'));
    }
}
