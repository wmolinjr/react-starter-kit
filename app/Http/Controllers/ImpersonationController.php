<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImpersonationController extends Controller
{
    /**
     * Iniciar impersonation de tenant/usuário via token
     *
     * Usa a API oficial do Stancl/Tenancy: tenancy()->impersonate()
     */
    public function start(Tenant $tenant, User $user = null)
    {
        // Apenas super admin pode impersonar
        // Verifica a role global (sem tenant_id)
        setPermissionsTeamId(null);
        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Only super administrators can impersonate tenants.');
        }

        // Se usuário específico NÃO foi fornecido, selecionar primeiro usuário do tenant
        if (!$user) {
            $user = $tenant->users()->firstOrFail();
        }

        // Validar que o usuário pertence ao tenant
        if (!$user->tenants()->where('tenant_id', $tenant->id)->exists()) {
            abort(403, 'User does not belong to this tenant.');
        }

        // Usa a API oficial do pacote Stancl/Tenancy para criar o token
        // Redireciona para o dashboard do tenant após impersonation
        $redirectUrl = '/dashboard';

        // UserImpersonation requer $userId como string
        $token = tenancy()->impersonate($tenant, (string) $user->id, $redirectUrl);

        // Redirecionar para URL de consumo do token no domínio do tenant
        // Usa Inertia::location() para external redirect (evita CORS issue com router.post)
        $domain = $tenant->primaryDomain()->domain;
        $protocol = request()->secure() ? 'https' : 'http';

        return Inertia::location("{$protocol}://{$domain}/impersonate/{$token->token}");
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
