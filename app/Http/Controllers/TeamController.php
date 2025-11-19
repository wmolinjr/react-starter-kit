<?php

namespace App\Http\Controllers;

use App\Mail\TeamInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TeamController extends Controller
{
    /**
     * Display team management page with members list.
     */
    public function index()
    {
        Gate::authorize('manage-team');

        $tenant = tenant();

        $members = $tenant->users()
            ->withPivot('role', 'permissions', 'invited_at', 'joined_at', 'invitation_token')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                    'permissions' => $user->pivot->permissions,
                    'invited_at' => $user->pivot->invited_at,
                    'joined_at' => $user->pivot->joined_at,
                    'is_pending' => is_null($user->pivot->joined_at),
                ];
            });

        return Inertia::render('tenant/team/index', [
            'members' => $members,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'max_users' => $tenant->max_users,
                'current_users' => $members->whereNotNull('joined_at')->count(),
            ],
        ]);
    }

    /**
     * Invite a new member to the team.
     */
    public function invite(Request $request)
    {
        Gate::authorize('manage-team');

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'member', 'guest'])],
        ]);

        $tenant = tenant();

        // Verificar limite de usuários
        if ($tenant->hasReachedUserLimit()) {
            return back()->with('error', 'Limite de usuários atingido para este plano.');
        }

        // Verificar se já é membro
        $existingMember = $tenant->users()
            ->where('email', $validated['email'])
            ->exists();

        if ($existingMember) {
            return back()->with('error', 'Este usuário já é membro do time.');
        }

        DB::beginTransaction();

        try {
            // Buscar ou criar usuário
            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => explode('@', $validated['email'])[0],
                    'password' => bcrypt(Str::random(32)), // Senha temporária
                ]
            );

            // Gerar token de convite
            $invitationToken = Str::random(64);

            // Adicionar ao tenant com status pendente
            $tenant->users()->attach($user->id, [
                'role' => $validated['role'],
                'invited_at' => now(),
                'joined_at' => null,
                'invitation_token' => $invitationToken,
            ]);

            // Enviar email de convite
            Mail::to($user->email)->send(new TeamInvitation(
                tenant: $tenant,
                invitedBy: $request->user(),
                role: $validated['role'],
                token: $invitationToken
            ));

            DB::commit();

            return back()->with('success', 'Convite enviado com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Erro ao enviar convite: '.$e->getMessage());
        }
    }

    /**
     * Accept invitation via token.
     */
    public function acceptInvitation(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')->with('error', 'Você precisa estar autenticado para aceitar convites.');
        }

        // Buscar tenant pelo token de convite
        $tenantUser = DB::table('tenant_user')
            ->where('user_id', $user->id)
            ->where('invitation_token', $validated['token'])
            ->whereNull('joined_at')
            ->first();

        if (! $tenantUser) {
            return redirect()->route('home')->with('error', 'Convite inválido ou já aceito.');
        }

        // Atualizar joined_at e limpar token
        DB::table('tenant_user')
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantUser->tenant_id)
            ->update([
                'joined_at' => now(),
                'invitation_token' => null,
            ]);

        // Buscar tenant para redirecionar
        $tenant = \App\Models\Tenant::find($tenantUser->tenant_id);

        return redirect()
            ->to("http://{$tenant->slug}.".config('tenancy.central_domains')[0]."/dashboard")
            ->with('success', 'Convite aceito! Bem-vindo ao time.');
    }

    /**
     * Update member role.
     */
    public function updateRole(Request $request, User $user)
    {
        Gate::authorize('manage-team');

        $validated = $request->validate([
            'role' => ['required', Rule::in(['owner', 'admin', 'member', 'guest'])],
        ]);

        $tenant = tenant();
        $currentUser = $request->user();

        // Prevenir auto-atualização
        if ($user->id === $currentUser->id) {
            return back()->with('error', 'Você não pode alterar sua própria role.');
        }

        // Apenas owners podem alterar role de outros owners
        $isTargetOwner = $tenant->users()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('role', 'owner')
            ->exists();

        $currentUserRole = $currentUser->roleOn($tenant);

        if ($isTargetOwner && $currentUserRole !== 'owner') {
            abort(403, 'Apenas owners podem alterar a role de outros owners.');
        }

        // Prevenir remoção do último owner
        if ($validated['role'] !== 'owner') {
            $ownerCount = $tenant->users()
                ->wherePivot('role', 'owner')
                ->count();

            $isTargetOwner = $tenant->users()
                ->wherePivot('user_id', $user->id)
                ->wherePivot('role', 'owner')
                ->exists();

            if ($ownerCount === 1 && $isTargetOwner) {
                return back()->with('error', 'Não é possível alterar a role do único owner. Promova outro membro primeiro.');
            }
        }

        // Atualizar role
        $tenant->users()->updateExistingPivot($user->id, [
            'role' => $validated['role'],
        ]);

        return back()->with('success', 'Role atualizada com sucesso!');
    }

    /**
     * Remove member from team.
     */
    public function remove(Request $request, User $user)
    {
        Gate::authorize('manage-team');

        $tenant = tenant();
        $currentUser = $request->user();

        // Prevenir auto-remoção
        if ($user->id === $currentUser->id) {
            return back()->with('error', 'Você não pode remover a si mesmo do time.');
        }

        // Prevenir remoção de qualquer owner (deve rebaixar primeiro)
        $isTargetOwner = $tenant->users()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('role', 'owner')
            ->exists();

        if ($isTargetOwner) {
            abort(403, 'Não é possível remover um owner diretamente. Altere a role primeiro.');
        }

        // Remover do tenant
        $tenant->users()->detach($user->id);

        return back()->with('success', 'Membro removido com sucesso!');
    }
}
