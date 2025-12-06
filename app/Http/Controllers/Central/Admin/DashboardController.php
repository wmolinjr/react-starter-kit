<?php

namespace App\Http\Controllers\Central\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\AddonSubscription;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\User as CentralUser;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Central admin dashboard controller.
 *
 * TENANT-ONLY ARCHITECTURE (Option C):
 * This controller supports both authentication methods during migration:
 * - 'admin' guard: New Admin model (central database)
 * - 'web' guard: Legacy User model with Super Admin role (deprecated)
 *
 * After full migration, only 'admin' guard will be supported.
 */
class DashboardController extends Controller
{
    /**
     * Admin dashboard with platform stats.
     *
     * Access control:
     * - If using 'admin' guard: must be a super admin (Admin model)
     * - If using 'web' guard: must have 'Super Admin' role (legacy, deprecated)
     */
    public function dashboard()
    {
        // Check if authenticated via admin guard (new architecture)
        if (Auth::guard('admin')->check()) {
            $admin = Auth::guard('admin')->user();
            if (! $admin instanceof CentralUser || ! $admin->isSuperAdmin()) {
                abort(403, 'Only super administrators can access this area.');
            }
        }
        // Legacy: Check if authenticated via web guard with Super Admin role
        elseif (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            if (! $user->hasRole('Super Admin')) {
                abort(403, 'Only super administrators can access this area.');
            }
        } else {
            abort(403, 'Authentication required.');
        }

        // Option C: Users only exist in tenant databases
        // Count admins from central DB instead
        $stats = [
            'total_tenants' => Tenant::count(),
            'total_admins' => CentralUser::count(),
            'total_addons' => AddonSubscription::active()->count(),
            'total_plans' => Plan::active()->count(),
        ];

        return Inertia::render('central/admin/dashboard', [
            'stats' => $stats,
        ]);
    }
}
