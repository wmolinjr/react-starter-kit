<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    /**
     * Admin dashboard com lista de tenants para impersonation
     */
    public function dashboard()
    {
        // Apenas super admin pode acessar
        // Verifica a role global (sem tenant_id)
        setPermissionsTeamId(null);
        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Only super administrators can access this area.');
        }

        $tenants = Tenant::withCount('users')
            ->with(['users' => function ($query) {
                $query->limit(5); // Primeiros 5 usuários para preview
            }])
            ->latest()
            ->paginate(15);

        return Inertia::render('central/admin/dashboard', [
            'tenants' => $tenants,
            'isImpersonating' => session()->has('impersonating_tenant'),
            'impersonatingTenant' => session()->get('impersonating_tenant'),
            'impersonatingUser' => session()->get('impersonating_user'),
        ]);
    }
}
