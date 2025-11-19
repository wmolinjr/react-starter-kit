<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    /**
     * Iniciar impersonation de tenant/usuário
     */
    public function start(Tenant $tenant, User $user = null)
    {
        // Apenas super admin pode impersonar
        if (!auth()->user()->is_super_admin) {
            abort(403, 'Only super administrators can impersonate tenants.');
        }

        // Armazenar tenant sendo impersonado
        session()->put('impersonating_tenant', $tenant->id);

        // Se usuário específico foi fornecido, fazer login como ele
        if ($user) {
            // Verificar se usuário pertence ao tenant
            if (!$user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                abort(403, 'User does not belong to this tenant.');
            }

            session()->put('impersonating_user', $user->id);
            auth()->login($user);
        }

        // Redirecionar para dashboard do tenant
        return redirect()->to($tenant->url() . '/dashboard');
    }

    /**
     * Parar impersonation e retornar ao admin dashboard
     */
    public function stop()
    {
        // Verificar se está impersonando
        if (!session()->has('impersonating_tenant')) {
            return redirect()->route('admin.dashboard');
        }

        // Limpar sessão de impersonation
        session()->forget(['impersonating_tenant', 'impersonating_user']);

        // Se estava impersonando usuário, fazer logout
        if (session()->has('impersonating_user')) {
            auth()->logout();
        }

        return redirect()->route('admin.dashboard')
            ->with('success', 'Impersonation stopped successfully.');
    }
}
