<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    /**
     * Display a list of payment methods.
     */
    public function index(Request $request): Response
    {
        $customer = $request->user('customer');

        $paymentMethods = $customer->paymentMethods()->map(fn ($pm) => [
            'id' => $pm->id,
            'brand' => $pm->card->brand,
            'last4' => $pm->card->last4,
            'exp_month' => $pm->card->exp_month,
            'exp_year' => $pm->card->exp_year,
            'is_default' => $pm->id === $customer->defaultPaymentMethod()?->id,
        ]);

        return Inertia::render('customer/payment-methods/index', [
            'paymentMethods' => $paymentMethods,
            'status' => session('status'),
        ]);
    }

    /**
     * Show the form for adding a new payment method.
     */
    public function create(Request $request): Response
    {
        $customer = $request->user('customer');

        return Inertia::render('customer/payment-methods/create', [
            'intent' => $customer->createSetupIntent(),
            'stripeKey' => config('cashier.key'),
        ]);
    }

    /**
     * Store a newly created payment method.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'string'],
        ]);

        $customer = $request->user('customer');

        $customer->addPaymentMethod($validated['payment_method']);

        // Set as default if first payment method
        if ($customer->paymentMethods()->count() === 1) {
            $customer->updateDefaultPaymentMethod($validated['payment_method']);
        }

        return redirect()->route('customer.payment-methods.index')
            ->with('status', 'payment-method-added');
    }

    /**
     * Remove the specified payment method.
     */
    public function destroy(Request $request, string $paymentMethod): RedirectResponse
    {
        $customer = $request->user('customer');

        // Check if this is the only payment method and there are active subscriptions
        if ($customer->paymentMethods()->count() === 1 && $customer->subscriptions()->active()->exists()) {
            return back()->withErrors([
                'payment_method' => __('You cannot remove your only payment method while having active subscriptions.'),
            ]);
        }

        $customer->deletePaymentMethod($paymentMethod);

        return back()->with('status', 'payment-method-removed');
    }

    /**
     * Set the specified payment method as default.
     */
    public function setDefault(Request $request, string $paymentMethod): RedirectResponse
    {
        $customer = $request->user('customer');

        $customer->updateDefaultPaymentMethod($paymentMethod);

        // Also update Stripe customer
        $customer->updateDefaultPaymentMethodFromStripe();

        return back()->with('status', 'default-payment-method-updated');
    }
}
