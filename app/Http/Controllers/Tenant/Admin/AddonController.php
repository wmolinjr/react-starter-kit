<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Services\Central\AddonService;
use App\Services\Central\CheckoutService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddonController extends Controller
{
    public function __construct(
        protected AddonService $addonService,
        protected CheckoutService $checkoutService
    ) {}

    public function index(): Response
    {
        return Inertia::render('tenant/admin/addons/index');
    }

    public function usage(): Response
    {
        return Inertia::render('tenant/admin/addons/usage');
    }

    public function success(Request $request): Response
    {
        return Inertia::render('tenant/admin/addons/success', [
            'addon_name' => $request->query('addon_name'),
            'quantity' => $request->query('quantity'),
            'amount' => $request->query('amount'),
        ]);
    }

    public function purchase(Request $request)
    {
        $validated = $request->validate([
            'addon_slug' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'billing_period' => 'required|in:monthly,yearly,one_time',
        ]);

        $tenant = tenant();
        $billingPeriod = $validated['billing_period'];

        try {
            // Use Stripe Checkout for payment
            if ($billingPeriod === 'one_time') {
                $result = $this->checkoutService->createCheckoutSession(
                    $tenant,
                    $validated['addon_slug'],
                    $validated['quantity']
                );
            } else {
                // Subscription (monthly/yearly)
                $result = $this->checkoutService->createSubscriptionCheckout(
                    $tenant,
                    $validated['addon_slug'],
                    $billingPeriod,
                    $validated['quantity']
                );
            }

            // Redirect to Stripe Checkout
            return Inertia::location($result['url']);
        } catch (\Exception $e) {
            return back()->withErrors(['addon' => $e->getMessage()]);
        }
    }

    public function cancel(Request $request, string $addon)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $tenantAddon = tenant()->addons()->findOrFail($addon);
            $this->addonService->cancel($tenantAddon, $request->reason);

            return back()->with('success', __('flash.addon.cancelled'));
        } catch (\Exception $e) {
            return back()->withErrors(['addon' => $e->getMessage()]);
        }
    }

    public function update(Request $request, string $addon)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $tenantAddon = tenant()->addons()->findOrFail($addon);
            $this->addonService->updateQuantity($tenantAddon, $validated['quantity']);

            return back()->with('success', __('flash.addon.updated'));
        } catch (\Exception $e) {
            return back()->withErrors(['addon' => $e->getMessage()]);
        }
    }
}
