<?php

namespace App\Http\Middleware\Shared;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Universal authentication middleware for shared routes.
 *
 * This middleware detects the current context (central or tenant) and
 * authenticates using the appropriate guard. It's designed for universal
 * routes that work in both central and tenant contexts.
 *
 * Context Detection:
 * - If tenancy is initialized → use 'tenant' guard
 * - If tenancy is NOT initialized → use 'central' guard
 *
 * @see routes/shared.php
 */
class AuthenticateUniversal
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->detectGuard();

        if (! auth($guard)->check()) {
            throw new AuthenticationException(
                'Unauthenticated.',
                [$guard],
                $this->redirectTo($guard)
            );
        }

        // Set the default guard for the request so $request->user() works correctly
        auth()->shouldUse($guard);

        return $next($request);
    }

    /**
     * Detect which guard to use based on tenancy context.
     */
    protected function detectGuard(): string
    {
        return tenancy()->initialized ? 'tenant' : 'central';
    }

    /**
     * Get the redirect path based on guard.
     */
    protected function redirectTo(string $guard): string
    {
        return $guard === 'central'
            ? route('central.admin.auth.login')
            : route('login');
    }
}
