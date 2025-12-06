<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\TenantPermission;
use App\Exceptions\TeamAuthorizationException;
use App\Exceptions\TeamException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AcceptInvitationRequest;
use App\Http\Requests\Tenant\InviteMemberRequest;
use App\Http\Requests\Tenant\UpdateMemberRoleRequest;
use App\Http\Resources\Tenant\TeamMemberResource;
use App\Http\Resources\Tenant\TenantInvitationResource;
use App\Models\Tenant\User;
use App\Services\Tenant\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TeamController
 *
 * OPTION C: TENANT-ONLY USERS
 * - Users exist ONLY in tenant databases
 * - No pivot table (tenant_user) - users are directly in tenant DB
 * - All user queries happen in tenant context (already initialized by middleware)
 */
class TeamController extends Controller implements HasMiddleware
{
    public function __construct(
        protected TeamService $teamService
    ) {}

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:'.TenantPermission::TEAM_VIEW->value, only: ['index']),
            new Middleware('permission:'.TenantPermission::TEAM_INVITE->value, only: ['invite']),
            new Middleware('permission:'.TenantPermission::TEAM_MANAGE_ROLES->value, only: ['updateRole']),
            new Middleware('permission:'.TenantPermission::TEAM_REMOVE->value, only: ['remove']),
        ];
    }

    /**
     * Display team management page with members list.
     */
    public function index(): Response
    {
        $tenant = tenant();

        return Inertia::render('tenant/admin/team/index', [
            'members' => TeamMemberResource::collection($this->teamService->getTeamMembers()),
            'pendingInvitations' => TenantInvitationResource::collection($this->teamService->getPendingInvitations($tenant)),
            'teamStats' => $this->teamService->getTeamStats($tenant),
        ]);
    }

    /**
     * Invite a new member to the team.
     */
    public function invite(InviteMemberRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->teamService->inviteMember(
                tenant: tenant(),
                email: $validated['email'],
                role: $validated['role'],
                invitedBy: $request->user()
            );

            return back()->with('success', __('flash.team.invite_sent'));
        } catch (TeamException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', __('flash.team.invite_error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Accept invitation via token.
     */
    public function acceptInvitation(AcceptInvitationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')->with('error', __('flash.team.auth_required'));
        }

        try {
            $this->teamService->acceptInvitation(
                user: $user,
                token: $validated['token'],
                tenantId: tenant('id')
            );

            return redirect()
                ->route('tenant.admin.dashboard')
                ->with('success', __('flash.team.invite_accepted'));
        } catch (TeamException $e) {
            return redirect()->route('tenant.admin.dashboard')->with('error', $e->getMessage());
        }
    }

    /**
     * Update member role.
     */
    public function updateRole(UpdateMemberRoleRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->teamService->updateMemberRole(
                target: $user,
                newRole: $validated['role'],
                currentUser: $request->user()
            );

            return back()->with('success', __('flash.team.role_updated'));
        } catch (TeamAuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (TeamException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove member from team.
     */
    public function remove(Request $request, User $user): RedirectResponse
    {
        try {
            $this->teamService->removeMember(
                member: $user,
                currentUser: $request->user()
            );

            return back()->with('success', __('flash.team.member_removed'));
        } catch (TeamAuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (TeamException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
