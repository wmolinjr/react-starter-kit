<?php

namespace App\Http\Middleware\Customer;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure customer email is verified.
 *
 * Redirects to customer-specific verification notice route.
 * This is needed because Laravel's default EnsureEmailIsVerified
 * redirects to 'verification.notice', not 'customer.verification.notice'.
 */
class EnsureCustomerEmailIsVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        if (! $request->user('customer') ||
            ($request->user('customer') instanceof \Illuminate\Contracts\Auth\MustVerifyEmail &&
            ! $request->user('customer')->hasVerifiedEmail())) {
            return $request->expectsJson()
                    ? abort(403, 'Your email address is not verified.')
                    : redirect()->route($redirectToRoute ?: 'customer.verification.notice');
        }

        return $next($request);
    }
}
