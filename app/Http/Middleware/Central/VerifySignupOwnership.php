<?php

namespace App\Http\Middleware\Central;

use App\Models\Central\PendingSignup;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify that the logged-in customer owns the PendingSignup.
 *
 * Customer-First Flow: PendingSignup is always linked to a Customer.
 * This middleware ensures users can only access their own signup flows.
 */
class VerifySignupOwnership
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signup = $request->route('signup');

        // If no signup in route, continue
        if (! $signup instanceof PendingSignup) {
            return $next($request);
        }

        // If signup has customer_id, verify ownership
        if ($signup->customer_id) {
            $customer = Auth::guard('customer')->user();

            // Must be logged in as the owner
            if (! $customer || $customer->id !== $signup->customer_id) {
                abort(403, __('signup.errors.unauthorized'));
            }
        }

        return $next($request);
    }
}
