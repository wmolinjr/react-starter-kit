<?php

namespace App\Http\Controllers\Central;

use App\Enums\BusinessSector;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Signup\ProcessPaymentRequest;
use App\Http\Requests\Central\Signup\StoreAccountRequest;
use App\Http\Requests\Central\Signup\UpdateWorkspaceRequest;
use App\Http\Resources\Central\CustomerSummaryResource;
use App\Http\Resources\Central\PendingSignupResource;
use App\Http\Resources\Central\PlanResource;
use App\Models\Central\Customer;
use App\Models\Central\PendingSignup;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Central\PaymentSettingsService;
use App\Services\Central\SignupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SignupController
 *
 * Handles the WIX-like signup wizard flow.
 *
 * Customer-First Flow: Customer is created in Step 1 (account creation),
 * logged in immediately, and Tenant is created after payment confirmation.
 */
class SignupController extends Controller
{
    public function __construct(
        protected SignupService $signupService,
        protected PaymentSettingsService $paymentSettingsService
    ) {}

    /**
     * Display the signup wizard.
     *
     * GET /signup/{plan}/{signup?}
     *
     * Customer-First: If customer is logged in without a signup,
     * creates a PendingSignup automatically and redirects with it.
     */
    public function create(Request $request, string $plan, ?PendingSignup $signup = null): Response|RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();

        // Validate signup is still usable
        if ($signup && ($signup->isCompleted() || $signup->isExpired() || $signup->isFailed())) {
            $signup = null;
        }

        // Customer-First: If logged in without a signup, create one and redirect
        if ($customer && ! $signup) {
            $signup = $this->signupService->createPendingSignupForCustomer($customer);

            return redirect()->route('central.signup.index', [
                'plan' => $plan,
                'signup' => $signup->id,
            ]);
        }

        // Security: Verify signup ownership (middleware handles this, but double-check)
        if ($signup && $signup->customer_id && $customer && $signup->customer_id !== $customer->id) {
            abort(403, __('signup.errors.unauthorized'));
        }

        return Inertia::render('central/signup/index', [
            'plans' => PlanResource::collection($plans),
            'selectedPlan' => $plan,
            'signup' => $signup ? new PendingSignupResource($signup) : null,
            'businessSectors' => BusinessSector::toArray(),
            'paymentConfig' => $this->paymentSettingsService->getPaymentConfig(),
            'customer' => $customer ? new CustomerSummaryResource($customer) : null,
            'skipAccountStep' => (bool) $customer,
        ]);
    }

    /**
     * Store account data (Step 1).
     *
     * POST /signup/account
     *
     * Customer-First: Creates Customer + PendingSignup, logs in immediately.
     * Uses Inertia redirect with flash data for CSRF protection.
     */
    public function storeAccount(StoreAccountRequest $request): RedirectResponse
    {
        $result = $this->signupService->createPendingSignupWithCustomer($request->validated());

        // Login customer immediately after account creation
        Auth::guard('customer')->login($result['customer']);
        $request->session()->regenerate();

        return redirect()
            ->back()
            ->with('pendingSignup', (new PendingSignupResource($result['signup']))->toArray($request));
    }

    /**
     * Update workspace data (Step 2).
     *
     * PATCH /signup/{signup}/workspace
     *
     * Uses Inertia redirect with flash data for CSRF protection.
     */
    public function updateWorkspace(PendingSignup $signup, UpdateWorkspaceRequest $request): RedirectResponse
    {
        try {
            $signup = $this->signupService->updateWorkspace($signup, $request->validated());

            return redirect()
                ->back()
                ->with('pendingSignup', (new PendingSignupResource($signup))->toArray($request));
        } catch (\App\Exceptions\Central\AddonException $e) {
            return redirect()
                ->back()
                ->withErrors(['workspace' => $e->getMessage()]);
        }
    }

    /**
     * Process payment (Step 3).
     *
     * POST /signup/{signup}/payment
     *
     * Uses Inertia redirect with flash data for CSRF protection.
     */
    public function processPayment(PendingSignup $signup, ProcessPaymentRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();

            \Log::info('SignupController.processPayment received', [
                'signup_id' => $signup->id,
                'all_input' => $request->all(),
                'validated' => $validated,
                'payment_method' => $validated['payment_method'] ?? null,
                'cpf_cnpj' => $validated['cpf_cnpj'] ?? 'NOT_SET',
            ]);

            $result = $this->signupService->processPayment(
                $signup,
                $validated['payment_method'],
                $validated['cpf_cnpj'] ?? null
            );

            return redirect()
                ->back()
                ->with('paymentResult', $result);
        } catch (\App\Exceptions\Central\AddonException $e) {
            return redirect()
                ->back()
                ->withErrors(['payment' => $e->getMessage()]);
        }
    }

    /**
     * Check payment status (for polling).
     *
     * GET /signup/{signup}/status
     */
    public function checkStatus(PendingSignup $signup): JsonResponse
    {
        $status = $this->signupService->checkPaymentStatus($signup);

        return response()->json($status);
    }

    /**
     * Refresh PIX QR code.
     *
     * POST /signup/{signup}/refresh-pix
     */
    public function refreshPix(PendingSignup $signup): JsonResponse
    {
        try {
            $result = $this->signupService->refreshPixQrCode($signup);

            return response()->json($result);
        } catch (\App\Exceptions\Central\AddonException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Complete Asaas card payment with card data.
     *
     * POST /signup/{signup}/card-payment
     *
     * Called after frontend collects card data for Asaas gateway.
     */
    public function completeCardPayment(PendingSignup $signup, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card' => ['required', 'array'],
            'card.holder_name' => ['required', 'string', 'max:100'],
            'card.number' => ['required', 'string', 'digits_between:13,19'],
            'card.exp_month' => ['required', 'string', 'digits:2'],
            'card.exp_year' => ['required', 'string', 'digits:4'],
            'card.cvv' => ['required', 'string', 'digits_between:3,4'],
            'holder' => ['required', 'array'],
            'holder.name' => ['required', 'string', 'max:100'],
            'holder.email' => ['required', 'email', 'max:100'],
            'holder.cpf_cnpj' => ['required', 'string', 'max:18'],
            'holder.postal_code' => ['required', 'string', 'max:10'],
            'holder.address_number' => ['required', 'string', 'max:10'],
            'holder.address_complement' => ['nullable', 'string', 'max:100'],
            'holder.phone' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $result = $this->signupService->completeAsaasCardPayment(
                $signup,
                $validated['card'],
                $validated['holder'],
                ['remote_ip' => $request->ip()]
            );

            if ($result['success']) {
                return response()->json($result);
            }

            return response()->json($result, 422);
        } catch (\App\Exceptions\Central\AddonException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Signup success page.
     *
     * GET /signup/success
     */
    public function success(Request $request): Response
    {
        $sessionId = $request->get('session_id');
        $signupId = $request->get('signup_id');

        $signup = null;
        $tenant = null;

        // Find signup by Stripe session or direct ID
        if ($sessionId) {
            $signup = $this->signupService->findByStripeSession($sessionId);
        } elseif ($signupId) {
            $signup = PendingSignup::find($signupId);
        }

        // Get tenant URL for redirect
        if ($signup && $signup->isCompleted()) {
            $tenant = $signup->tenant;
        }

        return Inertia::render('central/signup/success', [
            'signup' => $signup ? new PendingSignupResource($signup) : null,
            'tenantUrl' => $tenant?->url(),
            'tenantName' => $tenant?->name,
        ]);
    }

    /**
     * Validate email availability (AJAX).
     *
     * POST /signup/validate/email
     *
     * Customer-First: Only checks customers table (PendingSignup no longer stores email).
     */
    public function validateEmail(Request $request): JsonResponse
    {
        $email = $request->input('email');
        $exists = Customer::where('email', $email)->exists();

        return response()->json([
            'available' => ! $exists,
            'message' => $exists ? __('signup.errors.email_already_registered') : null,
        ]);
    }

    /**
     * Validate workspace slug availability (AJAX).
     *
     * POST /signup/validate/slug
     */
    public function validateSlug(Request $request): JsonResponse
    {
        $slug = $request->input('slug');
        $signupId = $request->input('signup_id');

        $existsInTenants = Tenant::where('slug', $slug)->exists();
        $existsInPending = PendingSignup::where('workspace_slug', $slug)
            ->whereIn('status', ['pending', 'processing'])
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->when($signupId, fn ($q) => $q->where('id', '!=', $signupId))
            ->exists();

        $available = ! $existsInTenants && ! $existsInPending;

        return response()->json([
            'available' => $available,
            'message' => $available ? null : __('signup.errors.slug_already_taken'),
        ]);
    }
}
