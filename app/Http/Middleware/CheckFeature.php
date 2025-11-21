<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

class CheckFeature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = tenant();

        if (!$tenant) {
            abort(403, 'Tenant not found.');
        }

        // Use Pennant to check feature
        if (!Feature::for($tenant)->active($feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This feature is not available on your current plan.',
                    'feature' => $feature,
                    'upgrade_url' => route('tenant.billing.index'),
                ], 403);
            }

            return redirect()
                ->route('tenant.billing.index')
                ->with('error', 'This feature is not available on your current plan. Please upgrade.');
        }

        return $next($request);
    }
}
