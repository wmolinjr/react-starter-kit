<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CheckoutRequest;
use App\Http\Resources\Tenant\BillingPlanResource;
use App\Http\Resources\Tenant\InvoiceDetailResource;
use App\Http\Resources\Tenant\InvoiceResource;
use App\Http\Resources\Tenant\SubscriptionResource;
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
        protected BillingService $billingService
    ) {}

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:'.TenantPermission::BILLING_VIEW->value, only: ['index', 'success']),
            new Middleware('permission:'.TenantPermission::BILLING_MANAGE->value, only: ['checkout', 'portal']),
            new Middleware('permission:'.TenantPermission::BILLING_INVOICES->value, only: ['invoices', 'invoice']),
        ];
    }

    /**
     * Display billing page.
     */
    public function index(): Response
    {
        $tenant = tenant();
        $overview = $this->billingService->getBillingOverview($tenant);

        return Inertia::render('tenant/admin/billing/index', [
            'plans' => BillingPlanResource::collection(collect($overview['plans'])),
            'subscription' => $overview['subscription'] ? new SubscriptionResource($overview['subscription']) : null,
            'invoices' => InvoiceResource::collection($overview['invoices']),
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
}
