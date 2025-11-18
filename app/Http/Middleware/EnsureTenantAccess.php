<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $request->input('current_tenant') ?? app('tenant');

        if (!$user || !$tenant) {
            abort(403, 'Access denied');
        }

        // Check if user has access to this tenant
        if (!$user->hasAccessToTenant($tenant)) {
            abort(403, 'You do not have access to this tenant');
        }

        // Update user's current tenant if different
        if ($user->current_tenant_id !== $tenant->id) {
            $user->update(['current_tenant_id' => $tenant->id]);
        }

        return $next($request);
    }
}
