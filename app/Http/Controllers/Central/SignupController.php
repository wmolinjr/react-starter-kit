<?php

namespace App\Http\Controllers\Central;

use App\Enums\BusinessSector;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Signup\ProcessPaymentRequest;
use App\Http\Requests\Central\Signup\StoreAccountRequest;
use App\Http\Requests\Central\Signup\UpdateWorkspaceRequest;
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
     */
    public function create(Request $request, string $plan, ?PendingSignup $signup = null): Response
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();

        // Validate signup is still usable
        if ($signup && ($signup->isCompleted() || $signup->isExpired() || $signup->isFailed())) {
            $signup = null;
        }

        return Inertia::render('central/signup/index', [
            'plans' => PlanResource::collection($plans),
            'selectedPlan' => $plan,
            'signup' => $signup ? new PendingSignupResource($signup) : null,
            'businessSectors' => BusinessSector::toArray(),
            'paymentConfig' => $this->paymentSettingsService->getPaymentConfig(),
        ]);
    }

    /**
     * Store account data (Step 1).
     *
     * POST /signup/account
     *
     * Uses Inertia redirect with flash data for CSRF protection.
     */
    public function storeAccount(StoreAccountRequest $request): RedirectResponse
    {
        $signup = $this->signupService->createPendingSignup($request->validated());

        return redirect()
            ->back()
            ->with('pendingSignup', (new PendingSignupResource($signup))->toArray($request));
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
            $result = $this->signupService->processPayment(
                $signup,
                $request->validated()['payment_method']
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
     */
    public function validateEmail(Request $request): JsonResponse
    {
        $email = $request->input('email');

        $existsInCustomers = Customer::where('email', $email)->exists();
        $existsInPending = PendingSignup::where('email', $email)
            ->whereIn('status', ['pending', 'processing'])
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        $available = ! $existsInCustomers && ! $existsInPending;

        return response()->json([
            'available' => $available,
            'message' => $available ? null : __('signup.errors.email_already_registered'),
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
