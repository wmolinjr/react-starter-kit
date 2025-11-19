<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class BillingController extends Controller
{
    /**
     * Página de billing
     */
    public function index()
    {
        Gate::authorize('manage-billing');

        $tenant = current_tenant();
        $subscription = $tenant->subscription('default');

        return Inertia::render('tenant/billing/index', [
            'plans' => billing_plans(),
            'subscription' => $subscription ? [
                'name' => $subscription->stripe_price,
                'status' => $subscription->stripe_status,
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'ends_at' => $subscription->ends_at?->toDateString(),
                'on_trial' => $subscription->onTrial(),
                'on_grace_period' => $subscription->onGracePeriod(),
                'canceled' => $subscription->canceled(),
            ] : null,
            'invoices' => $tenant->invoices()->map(fn ($invoice) => [
                'id' => $invoice->id,
                'date' => $invoice->date()->toFormattedDateString(),
                'total' => $invoice->total(),
                'download_url' => route('billing.invoice', $invoice->id),
            ]),
        ]);
    }

    /**
     * Iniciar checkout
     */
    public function checkout(Request $request)
    {
        Gate::authorize('manage-billing');

        $request->validate([
            'plan' => 'required|in:starter,professional,enterprise',
        ]);

        $tenant = current_tenant();
        $plans = billing_plans();
        $priceId = $plans[$request->plan]['price_id'];

        // Criar ou atualizar subscription
        $checkout = $tenant->newSubscription('default', $priceId)
            ->trialDays(14)
            ->checkout([
                'success_url' => route('billing.success'),
                'cancel_url' => route('billing.index'),
            ]);

        return Inertia::location($checkout->url());
    }

    /**
     * Checkout success
     */
    public function success()
    {
        Gate::authorize('manage-billing');

        $tenant = current_tenant();

        // Atualizar limits baseado no plano
        $subscription = $tenant->subscription('default');

        if ($subscription) {
            $priceId = $subscription->stripe_price;
            $plan = collect(billing_plans())->first(fn ($p) => $p['price_id'] === $priceId);

            if ($plan) {
                $tenant->update(['max_users' => $plan['limits']['max_users']]);
                $tenant->updateSetting('limits', $plan['limits']);
            }
        }

        return redirect()->route('billing.index')
            ->with('success', 'Assinatura ativada com sucesso!');
    }

    /**
     * Customer portal (gerenciar cartões, invoices, cancel)
     */
    public function portal()
    {
        Gate::authorize('manage-billing');

        $tenant = current_tenant();

        return $tenant->redirectToBillingPortal(route('billing.index'));
    }

    /**
     * Download invoice
     */
    public function invoice(string $invoiceId)
    {
        Gate::authorize('manage-billing');

        $tenant = current_tenant();

        return $tenant->downloadInvoice($invoiceId, [
            'vendor' => config('app.name'),
            'product' => 'Subscription',
        ]);
    }
}
