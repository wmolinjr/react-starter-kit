<?php

namespace App\Http\Controllers;

use App\Mail\TeamInvitation;
use App\Models\TenantInvitation;
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
        Gate::authorize('tenant.team:view');

        $tenant = tenant();

        $members = $tenant->users()
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles()->first()?->name, // Spatie Permission
                    'permissions' => $user->getAllPermissions()->pluck('name'), // Spatie Permission
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
        Gate::authorize('tenant.team:invite');

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'member'])],
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

            // Adicionar ao tenant com status pendente (sem role)
            $tenant->users()->attach($user->id, [
                'invited_at' => now(),
                'joined_at' => null,
                'invitation_token' => $invitationToken,
            ]);

            // Criar registro de convite com role
            TenantInvitation::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'invited_by_user_id' => $request->user()->id,
                'role' => $validated['role'],
                'invitation_token' => $invitationToken,
                'invited_at' => now(),
                'expires_at' => now()->addDays(7), // Convite expira em 7 dias
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

        // Buscar convite pendente
        $invitation = TenantInvitation::findByToken($validated['token']);

        if (! $invitation) {
            return redirect()->route('home')->with('error', 'Convite inválido, expirado ou já aceito.');
        }

        // Verificar se o convite é para o usuário autenticado
        if ($invitation->user_id !== $user->id) {
            return redirect()->route('home')->with('error', 'Este convite não é para você.');
        }

        DB::beginTransaction();

        try {
            $tenant = $invitation->tenant;

            // Atualizar joined_at e limpar token na pivot table
            DB::table('tenant_user')
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenant->id)
                ->update([
                    'joined_at' => now(),
                    'invitation_token' => null,
                ]);

            // Inicializar tenant context para atribuir role via Spatie Permission
            tenancy()->initialize($tenant);
            setPermissionsTeamId($tenant->id);

            // Atribuir role ao usuário
            $user->assignRole($invitation->role);

            // Finalizar tenant context
            tenancy()->end();

            // Marcar convite como aceito
            $invitation->update(['accepted_at' => now()]);

            DB::commit();

            return redirect()
                ->to("http://{$tenant->slug}.".config('tenancy.central_domains')[0]."/dashboard")
                ->with('success', 'Convite aceito! Bem-vindo ao time.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->route('home')->with('error', 'Erro ao aceitar convite: '.$e->getMessage());
        }
    }

    /**
     * Update member role.
     */
    public function updateRole(Request $request, User $user)
    {
        Gate::authorize('tenant.team:manage-roles');

        $validated = $request->validate([
            'role' => ['required', Rule::in(['owner', 'admin', 'member'])],
        ]);

        $tenant = tenant();
        $currentUser = $request->user();

        // Prevenir auto-atualização
        if ($user->id === $currentUser->id) {
            return back()->with('error', 'Você não pode alterar sua própria role.');
        }

        // Verificar se target user é owner (via Spatie Permission)
        $isTargetOwner = $user->hasRole('owner');

        $currentUserRole = $currentUser->roleOn($tenant);

        if ($isTargetOwner && $currentUserRole !== 'owner') {
            abort(403, 'Apenas owners podem alterar a role de outros owners.');
        }

        // Prevenir remoção do último owner
        if ($validated['role'] !== 'owner' && $isTargetOwner) {
            // Contar owners usando Spatie Permission
            $ownerCount = \Spatie\Permission\Models\Role::findByName('owner')->users()->count();

            if ($ownerCount === 1) {
                return back()->with('error', 'Não é possível alterar a role do único owner. Promova outro membro primeiro.');
            }
        }

        // Atualizar role via Spatie Permission
        DB::transaction(function () use ($user, $validated) {
            // Remove todas as roles anteriores
            $user->syncRoles([]);
            // Atribui nova role
            $user->assignRole($validated['role']);
        });

        return back()->with('success', 'Role atualizada com sucesso!');
    }

    /**
     * Remove member from team.
     */
    public function remove(Request $request, User $user)
    {
        Gate::authorize('tenant.team:remove');

        $tenant = tenant();
        $currentUser = $request->user();

        // Prevenir auto-remoção
        if ($user->id === $currentUser->id) {
            return back()->with('error', 'Você não pode remover a si mesmo do time.');
        }

        // Prevenir remoção de qualquer owner (deve rebaixar primeiro)
        // Verifica via Spatie Permission
        $isTargetOwner = $user->hasRole('owner');

        if ($isTargetOwner) {
            abort(403, 'Não é possível remover um owner diretamente. Altere a role primeiro.');
        }

        // Remover do tenant
        $tenant->users()->detach($user->id);

        return back()->with('success', 'Membro removido com sucesso!');
    }
}
