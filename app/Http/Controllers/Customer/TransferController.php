<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Models\Central\TenantTransfer;
use App\Services\Central\TenantTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function __construct(
        protected TenantTransferService $transferService
    ) {}

    /**
     * Show the form for initiating a transfer.
     */
    public function create(Request $request, Tenant $tenant): Response
    {
        $this->authorizeCustomerOwnsTenant($request, $tenant);

        $tenant->load(['plan', 'domains']);

        return Inertia::render('customer/transfers/create', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $tenant->domains->first()?->domain,
                'plan' => $tenant->plan?->name,
            ],
        ]);
    }

    /**
     * Store a newly created transfer request.
     */
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorizeCustomerOwnsTenant($request, $tenant);

        $validated = $request->validate([
            'to_email' => ['required', 'email'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = $request->user('customer');

        // Prevent self-transfer
        if ($validated['to_email'] === $customer->email) {
            return back()->withErrors(['to_email' => __('You cannot transfer a workspace to yourself.')]);
        }

        try {
            $this->transferService->initiate(
                $tenant,
                $customer,
                $validated['to_email'],
                ['notes' => $validated['notes'] ?? null]
            );

            return redirect()->route('customer.tenants.show', $tenant)
                ->with('status', 'transfer-initiated');
        } catch (\App\Exceptions\Central\TransferException $e) {
            return back()->withErrors(['to_email' => $e->getMessage()]);
        }
    }

    /**
     * Show the transfer acceptance page (for guests).
     */
    public function showAccept(string $token): Response
    {
        $transfer = TenantTransfer::where('token', $token)
            ->with(['tenant.domains', 'tenant.plan', 'fromCustomer'])
            ->firstOrFail();

        if ($transfer->isExpired()) {
            return Inertia::render('customer/transfers/expired');
        }

        if (! $transfer->canBeAccepted()) {
            return Inertia::render('customer/transfers/invalid', [
                'status' => $transfer->status,
            ]);
        }

        return Inertia::render('customer/transfers/accept', [
            'transfer' => [
                'token' => $transfer->token,
                'to_email' => $transfer->to_email,
                'transfer_fee' => $transfer->transfer_fee,
                'expires_at' => $transfer->expires_at->toISOString(),
                'notes' => $transfer->notes,
            ],
            'tenant' => [
                'name' => $transfer->tenant->name,
                'domain' => $transfer->tenant->domains->first()?->domain,
                'plan' => $transfer->tenant->plan?->name,
            ],
            'from_customer' => [
                'name' => $transfer->fromCustomer->name,
            ],
        ]);
    }

    /**
     * Confirm and accept the transfer.
     */
    public function confirm(Request $request, string $token): RedirectResponse
    {
        $transfer = TenantTransfer::where('token', $token)->firstOrFail();

        $customer = $request->user('customer');

        if (! $customer) {
            // Redirect to login with return URL
            return redirect()->route('customer.login')
                ->with('intended_url', route('customer.transfers.accept.show', $token));
        }

        try {
            // Accept the transfer
            $this->transferService->accept($transfer, $customer);

            // Complete the transfer (move ownership)
            $this->transferService->complete($transfer);

            return redirect()->route('customer.tenants.show', $transfer->tenant)
                ->with('status', 'transfer-accepted');
        } catch (\App\Exceptions\Central\TransferException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }
    }

    /**
     * Cancel a pending transfer.
     */
    public function cancel(Request $request, TenantTransfer $transfer): RedirectResponse
    {
        $customer = $request->user('customer');

        try {
            $this->transferService->cancel($transfer, $customer);

            return redirect()->route('customer.tenants.show', $transfer->tenant)
                ->with('status', 'transfer-cancelled');
        } catch (\App\Exceptions\Central\TransferException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }
    }

    /**
     * Reject a transfer invitation.
     */
    public function reject(Request $request, TenantTransfer $transfer): RedirectResponse
    {
        $customer = $request->user('customer');

        try {
            $this->transferService->reject($transfer, $customer);

            return redirect()->route('customer.dashboard')
                ->with('status', 'transfer-rejected');
        } catch (\App\Exceptions\Central\TransferException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }
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
}
