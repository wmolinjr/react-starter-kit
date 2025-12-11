<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\AddonResource;
use App\Http\Resources\Central\BundleResource;
use App\Http\Resources\Central\PaymentConfigResource;
use App\Services\Central\AddonService;
use App\Services\Central\CheckoutService;
use App\Services\Central\PaymentSettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddonController extends Controller
{
    public function __construct(
        protected AddonService $addonService,
        protected CheckoutService $checkoutService,
        protected PaymentSettingsService $paymentSettingsService
    ) {}

    public function index(): Response
    {
        $tenant = tenant();

        // Get available addons for tenant's plan
        $availableAddons = $this->addonService->getAvailableAddons($tenant);

        // Get active addons
        $activeAddons = $tenant->activeAddons()
            ->whereNull('metadata->bundle_slug')
            ->get();

        // Get active bundles grouped
        $activeBundles = $this->addonService->getActiveBundles($tenant);

        // Get available bundles
        $availableBundles = $this->addonService->getAvailableBundles($tenant);

        // Calculate total monthly cost
        $monthlyCost = $this->addonService->calculateTotalMonthlyCost($tenant);

        return Inertia::render('tenant/admin/addons/index', [
            'availableAddons' => AddonResource::collection($availableAddons),
            'activeAddons' => $activeAddons->map(fn ($addon) => [
                'id' => $addon->id,
                'slug' => $addon->addon_slug,
                'name' => $addon->name,
                'description' => $addon->description,
                'type' => $addon->addon_type->value,
                'quantity' => $addon->quantity,
                'price' => $addon->price,
                'formattedPrice' => '$'.number_format($addon->price / 100, 2),
                'totalPrice' => $addon->total_price,
                'formattedTotalPrice' => '$'.number_format($addon->total_price / 100, 2),
                'billingPeriod' => $addon->billing_period->value,
                'status' => $addon->status->value,
                'startedAt' => $addon->started_at?->toISOString(),
                'expiresAt' => $addon->expires_at?->toISOString(),
            ]),
            'availableBundles' => BundleResource::collection($availableBundles),
            'activeBundles' => $activeBundles,
            'monthlyCost' => $monthlyCost,
            'formattedMonthlyCost' => '$'.number_format($monthlyCost / 100, 2),
            'activeAddonSlugs' => $tenant->activeAddons()->pluck('addon_slug')->toArray(),
            'paymentConfig' => new PaymentConfigResource(
                $this->paymentSettingsService->getAvailablePaymentMethods()
            ),
        ]);
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
