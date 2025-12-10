<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\Central\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    /**
     * Display the customer's profile form.
     */
    public function edit(Request $request): Response
    {
        $customer = $request->user('customer');

        return Inertia::render('customer/profile/edit', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'locale' => $customer->locale,
                'currency' => $customer->currency,
                'billing_address' => $customer->billing_address,
                'tax_ids' => $customer->tax_ids,
                'email_verified_at' => $customer->email_verified_at?->toISOString(),
                'two_factor_enabled' => $customer->hasEnabledTwoFactorAuthentication(),
            ],
            'status' => session('status'),
        ]);
    }

    /**
     * Update the customer's profile information.
     */
    public function update(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:customers,email,'.$customer->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $emailChanged = $customer->email !== $validated['email'];

        $this->customerService->updateProfile($customer, $validated);

        if ($emailChanged) {
            $customer->email_verified_at = null;
            $customer->save();
            $customer->sendEmailVerificationNotification();
        }

        return back()->with('status', 'profile-updated');
    }

    /**
     * Update the customer's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:customer'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user('customer')->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }

    /**
     * Update the customer's billing information.
     */
    public function updateBilling(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        $validated = $request->validate([
            'billing_address' => ['nullable', 'array'],
            'billing_address.line1' => ['required_with:billing_address', 'string', 'max:255'],
            'billing_address.line2' => ['nullable', 'string', 'max:255'],
            'billing_address.city' => ['required_with:billing_address', 'string', 'max:255'],
            'billing_address.state' => ['nullable', 'string', 'max:255'],
            'billing_address.postal_code' => ['required_with:billing_address', 'string', 'max:20'],
            'billing_address.country' => ['required_with:billing_address', 'string', 'size:2'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        if (isset($validated['billing_address'])) {
            $this->customerService->updateBillingAddress($customer, $validated['billing_address']);
        }

        if (isset($validated['currency'])) {
            $customer->update(['currency' => $validated['currency']]);
        }

        return back()->with('status', 'billing-updated');
    }

    /**
     * Delete the customer's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password:customer'],
        ]);

        $customer = $request->user('customer');

        // Check if customer has any active subscriptions or tenants
        if ($customer->ownedTenants()->exists()) {
            return back()->withErrors([
                'password' => __('You must transfer or delete all your workspaces before deleting your account.'),
            ]);
        }

        // Cancel all subscriptions
        foreach ($customer->subscriptions()->active()->get() as $subscription) {
            $subscription->cancelNow();
        }

        // Logout and delete
        auth('customer')->logout();
        $customer->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }

    /**
     * Redirect to Stripe Billing Portal.
     */
    public function billingPortal(Request $request): RedirectResponse
    {
        return $request->user('customer')->redirectToBillingPortal(
            route('customer.dashboard')
        );
    }
}
