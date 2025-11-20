<?php

namespace App\Http\Controllers;

use App\Models\ImpersonationToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ImpersonationController extends Controller
{
    /**
     * Iniciar impersonation de tenant/usuário via token
     */
    public function start(Tenant $tenant, User $user = null)
    {
        // Apenas super admin pode impersonar
        if (!auth()->user()->is_super_admin) {
            abort(403, 'Only super administrators can impersonate tenants.');
        }

        // Se usuário específico foi fornecido, validar que pertence ao tenant
        if ($user) {
            if (!$user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                abort(403, 'User does not belong to this tenant.');
            }
        }

        // Criar token de impersonation com expiração de 5 minutos
        $token = ImpersonationToken::create([
            'token' => Str::random(64),
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        // Redirecionar para URL de consumo do token no domínio do tenant
        return Inertia::location($tenant->url() . '/impersonate/' . $token->token);
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
