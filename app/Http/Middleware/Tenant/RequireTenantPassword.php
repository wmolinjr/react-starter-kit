<?php

namespace App\Http\Middleware\Tenant;

use Closure;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to require password confirmation for Tenant users.
 *
 * This is a custom implementation since we no longer use Fortify for
 * authentication routes. Tenant users need their own password confirmation flow.
 */
class RequireTenantPassword
{
    /**
     * The password timeout in seconds (default: 3 hours).
     */
    protected int $passwordTimeout = 10800;

    public function __construct(
        protected ResponseFactory $responseFactory,
        protected UrlGenerator $urlGenerator,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?int $passwordTimeoutSeconds = null): Response
    {
        if ($this->shouldConfirmPassword($request, $passwordTimeoutSeconds)) {
            if ($request->expectsJson()) {
                return $this->responseFactory->json([
                    'message' => 'Password confirmation required.',
                ], 423);
            }

            return $this->responseFactory->redirectGuest(
                $this->urlGenerator->route('tenant.admin.auth.confirm-password')
            );
        }

        return $next($request);
    }

    /**
     * Determine if the confirmation timeout has expired.
     */
    protected function shouldConfirmPassword(Request $request, ?int $passwordTimeoutSeconds = null): bool
    {
        $confirmedAt = Date::now()->unix() - $request->session()->get('auth.password_confirmed_at', 0);

        return $confirmedAt > ($passwordTimeoutSeconds ?? $this->passwordTimeout);
    }
}
