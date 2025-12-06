<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Panel;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Handles seamless login from central domain to tenant domain.
 *
 * This is different from impersonation:
 * - Impersonation: Admin accesses tenant AS a different user (marked with flag)
 * - Seamless Login: User accesses their OWN tenant (no flag, they ARE the user)
 */
class TenantAccessController extends Controller
{
    /**
     * Generate access token and redirect user to their tenant.
     */
    public function redirect(Request $request, Tenant $tenant)
    {
        $user = $request->user();

        // Verify user is a member of this tenant
        if (!$user->tenants()->where('tenants.id', $tenant->id)->exists()) {
            abort(403, __('You do not have access to this tenant.'));
        }

        // Generate impersonation token (we'll consume it differently on tenant side)
        $token = tenancy()->impersonate(
            tenant: $tenant,
            userId: (string) $user->id,
            redirectUrl: '/admin/dashboard',
        );

        // Redirect to tenant domain with seamless login route (not impersonation route)
        $url = $tenant->url() . '/auth/seamless/' . $token->token;

        return Inertia::location($url);
    }
}
