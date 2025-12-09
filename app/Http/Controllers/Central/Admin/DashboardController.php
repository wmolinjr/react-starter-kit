<?php

namespace App\Http\Controllers\Central\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\CentralDashboardStatsResource;
use App\Models\Central\AddonSubscription;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\User as CentralUser;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Central admin dashboard controller.
 *
 * ARCHITECTURE (Option C - Tenant-Only Users):
 * - Central admins authenticate via 'central' guard (Central\User model)
 * - Only admins with proper permissions can access this controller
 */
class DashboardController extends Controller
{
    /**
     * Admin dashboard with platform stats.
     *
     * Requires authenticated central user with at least one role.
     * Users without roles cannot access the admin area.
     */
    public function dashboard()
    {
        $admin = Auth::guard('central')->user();

        if (! $admin instanceof CentralUser) {
            abort(403, 'Authentication required.');
        }

        // Require at least one role to access admin area
        if ($admin->roles()->count() === 0) {
            abort(403, 'Access denied. No admin role assigned.');
        }

        $stats = new CentralDashboardStatsResource([
            'total_tenants' => Tenant::count(),
            'total_admins' => CentralUser::count(),
            'total_addons' => AddonSubscription::active()->count(),
            'total_plans' => Plan::active()->count(),
        ]);

        return Inertia::render('central/admin/dashboard', [
            'stats' => $stats,
        ]);
    }
}
