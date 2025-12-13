<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\TenantSummaryResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the customer dashboard.
     */
    public function index(Request $request): Response
    {
        $customer = $request->user('customer');

        $customer->load([
            'ownedTenants.domains',
            'ownedTenants.plan',
        ]);

        // Calculate statistics
        $tenants = $customer->ownedTenants;
        $activeSubscriptions = $customer->subscriptions()->active()->count();
        $pendingTransfers = $customer->receivedTransfers()
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->count();

        return Inertia::render('central/customer/dashboard', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'currency' => $customer->currency,
                'has_payment_method' => $customer->hasPaymentMethod(),
            ],
            'tenants' => TenantSummaryResource::collection($tenants),
            'stats' => [
                'tenant_count' => $tenants->count(),
                'active_subscriptions' => $activeSubscriptions,
                'pending_transfers' => $pendingTransfers,
                'total_monthly_billing' => $customer->getTotalMonthlyBilling(),
            ],
        ]);
    }
}
