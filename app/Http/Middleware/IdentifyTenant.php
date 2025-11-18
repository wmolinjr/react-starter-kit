<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get tenant from route parameter
        $tenantSlug = $request->route('tenant');

        if (!$tenantSlug) {
            abort(404, 'Tenant not specified');
        }

        // Find tenant by slug
        $tenant = Tenant::where('slug', $tenantSlug)
            ->where('status', 'active')
            ->first();

        if (!$tenant) {
            abort(404, 'Tenant not found or inactive');
        }

        // Store tenant in request for later use
        $request->merge(['current_tenant' => $tenant]);

        // Share tenant instance
        app()->instance('tenant', $tenant);

        return $next($request);
    }
}
