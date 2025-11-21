<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

class CheckLimit
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $tenant = tenant();

        if (!$tenant) {
            abort(403, 'Tenant not found.');
        }

        // Get limit from Pennant (rich value)
        $limitFeature = 'max' . ucfirst($resource);
        $limit = Feature::for($tenant)->value($limitFeature);
        $usage = $tenant->getCurrentUsage($resource);

        // Check if reached
        if ($limit !== -1 && $usage >= $limit) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "You have reached the limit for {$resource}.",
                    'limit' => $limit,
                    'current' => $usage,
                    'upgrade_url' => route('tenant.billing.index'),
                ], 403);
            }

            return redirect()
                ->route('tenant.billing.index')
                ->with('error', "You have reached the limit for {$resource}. Please upgrade your plan.");
        }

        return $next($request);
    }
}
