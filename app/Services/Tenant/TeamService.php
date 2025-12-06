<?php

namespace App\Services\Tenant;

use App\Exceptions\Tenant\TeamAuthorizationException;
use App\Mail\Tenant\TeamInvitation;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Models\Tenant\UserInvitation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * TeamService
 *
 * Handles all business logic for team management in tenant context.
 *
 * MULTI-DATABASE TENANCY (Option C):
 * - Users exist ONLY in tenant databases
 * - UserInvitation lives in tenant database (isolated per tenant)
 * - All queries happen in tenant context (already initialized by middleware)
 */
class TeamService
{
    /**
     * Get all team members with their roles.
     *
     * Returns User models for use with TeamMemberResource.
     *
     * @return Collection<int, User>
     */
    public function getTeamMembers(): Collection
    {
        return User::with('roles')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get pending invitations for the current tenant.
     *
     * Returns UserInvitation models for use with UserInvitationResource.
     *
     * @return Collection<int, UserInvitation>
     */
    public function getPendingInvitations(): Collection
    {
        return UserInvitation::query()
            ->with('invitedBy')
            ->pending()
            ->orderBy('invited_at', 'desc')
            ->get();
    }

    /**
     * Get team statistics.
     *
     * @return array{max_users: int|null, current_users: int}
     */
    public function getTeamStats(Tenant $tenant): array
    {
        return [
            'max_users' => $tenant->getLimit('users'),
            'current_users' => User::count(),
        ];
    }

    /**
     * Invite a new member to the team.
     *
     * @throws \App\Exceptions\Tenant\TeamException
     */
    public function inviteMember(
        Tenant $tenant,
        string $email,
        string $role,
        User $invitedBy
    ): UserInvitation {
        // Check user limit
        if ($tenant->hasReachedUserLimit()) {
            throw new \App\Exceptions\Tenant\TeamException(__('flash.team.user_limit_reached'));
        }

        // Check if already a member
        if (User::where('email', $email)->exists()) {
            throw new \App\Exceptions\Tenant\TeamException(__('flash.team.user_already_member'));
        }

        // Check for pending invitation
        $existingInvitation = UserInvitation::query()
            ->where('email', $email)
            ->pending()
            ->exists();

        if ($existingInvitation) {
            throw new \App\Exceptions\Tenant\TeamException(__('flash.team.invite_already_pending'));
        }

        // Generate invitation token
        $invitationToken = Str::random(64);

        // Create invitation record
        $invitation = UserInvitation::create([
            'email' => $email,
            'invited_by_user_id' => $invitedBy->id,
            'role' => $role,
            'invitation_token' => $invitationToken,
            'invited_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        // Send invitation email
        Mail::to($email)->send(new TeamInvitation(
            tenant: $tenant,
            invitedBy: $invitedBy,
            role: $role,
            token: $invitationToken
        ));

        return $invitation;
    }

    /**
     * Accept an invitation via token.
     *
     * @throws \App\Exceptions\Tenant\TeamException
     */
    public function acceptInvitation(User $user, string $token): void
    {
        // Find pending invitation (already in tenant context)
        $invitation = UserInvitation::findByToken($token);

        if (! $invitation) {
            throw new \App\Exceptions\Tenant\TeamException(__('flash.team.invite_invalid'));
        }

        // Verify the invitation email matches (case-insensitive)
        if (strtolower($invitation->email) !== strtolower($user->email)) {
            throw new \App\Exceptions\Tenant\TeamException(__('flash.team.invite_wrong_email'));
        }

        DB::beginTransaction();

        try {
            // Assign role to user (already exists in tenant DB since they're authenticated)
            $user->assignRole($invitation->role);

            // Mark invitation as accepted
            $invitation->update(['accepted_at' => now()]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\Tenant\TeamException(
                __('flash.team.invite_accept_error', ['error' => $e->getMessage()])
            );
        }
    }

    /**
     * Update a member's role.
     *
     * @throws \App\Exceptions\Tenant\TeamException
     * @throws TeamAuthorizationException
     */
    public function updateMemberRole(User $target, string $newRole, User $currentUser): void
    {
        // Prevent self-update
        if ($target->id === $currentUser->id) {
            throw new \App\Exceptions\Tenant\TeamException(__('flash.team.cannot_change_own_role'));
        }

        // Check if target user is owner
        $isTargetOwner = $target->hasRole('owner');
        $isCurrentUserOwner = $currentUser->hasRole('owner');

        if ($isTargetOwner && ! $isCurrentUserOwner) {
            throw new TeamAuthorizationException(__('flash.team.only_owners_change_owners'));
        }

        // Prevent removing last owner
        if ($newRole !== 'owner' && $isTargetOwner) {
            $ownerCount = User::role('owner')->count();

            if ($ownerCount === 1) {
                throw new \App\Exceptions\Tenant\TeamException(__('flash.team.cannot_change_only_owner'));
            }
        }

        // Update role via Spatie Permission
        DB::transaction(function () use ($target, $newRole) {
            $target->syncRoles([$newRole]);
        });
    }

    /**
     * Remove a member from the team.
     *
     * @throws \App\Exceptions\Tenant\TeamException
     * @throws TeamAuthorizationException
     */
    public function removeMember(User $member, User $currentUser): void
    {
        // Prevent self-removal
        if ($member->id === $currentUser->id) {
            throw new \App\Exceptions\Tenant\TeamException(__('flash.team.cannot_remove_self'));
        }

        // Prevent removal of any owner (must demote first)
        if ($member->hasRole('owner')) {
            throw new TeamAuthorizationException(__('flash.team.cannot_remove_owner'));
        }

        // Soft delete user from tenant database
        $member->delete();
    }

    /**
     * Check if a user can invite new members.
     */
    public function canInviteMembers(Tenant $tenant): bool
    {
        return ! $tenant->hasReachedUserLimit();
    }

    /**
     * Resend an invitation email.
     *
     * @throws \App\Exceptions\Tenant\TeamException
     */
    public function resendInvitation(UserInvitation $invitation, Tenant $tenant, User $invitedBy): void
    {
        if ($invitation->isAccepted()) {
            throw new \App\Exceptions\Tenant\TeamException(__('flash.team.invite_already_accepted'));
        }

        if ($invitation->isExpired()) {
            // Extend expiration
            $invitation->update(['expires_at' => now()->addDays(7)]);
        }

        // Resend email
        Mail::to($invitation->email)->send(new TeamInvitation(
            tenant: $tenant,
            invitedBy: $invitedBy,
            role: $invitation->role,
            token: $invitation->invitation_token
        ));
    }

    /**
     * Cancel a pending invitation.
     */
    public function cancelInvitation(UserInvitation $invitation): void
    {
        $invitation->delete();
    }
}
