<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Notifications\TenantInvitationNotification;
use Illuminate\Http\Request;

class TenantInvitationController extends Controller
{
    /**
     * Display a listing of invitations for the tenant.
     */
    public function index(string $slug)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('view', $tenant);

        $invitations = $tenant->invitations()
            ->with('inviter:id,name,email')
            ->latest()
            ->get();

        return response()->json($invitations);
    }

    /**
     * Store a newly created invitation.
     */
    public function store(Request $request, string $slug)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'role' => [
                'required',
                'in:member,admin',
            ],
        ]);

        // Check if user is already a member
        if ($tenant->users()->where('email', $validated['email'])->exists()) {
            return back()->withErrors([
                'email' => 'This user is already a member of this workspace',
            ]);
        }

        // Check if there's already a pending invitation
        $existingInvitation = $tenant->invitations()
            ->where('email', $validated['email'])
            ->pending()
            ->first();

        if ($existingInvitation) {
            return back()->withErrors([
                'email' => 'An invitation has already been sent to this email address',
            ]);
        }

        try {
            $invitation = $tenant->invitations()->create([
                'email' => $validated['email'],
                'role' => $validated['role'],
                'invited_by' => auth()->id(),
            ]);

            // Send invitation email
            // Note: Notification will be created later
            // $invitation->notify(new TenantInvitationNotification($tenant));

            return back()->with([
                'flash' => [
                    'data' => $invitation->load('inviter:id,name,email'),
                    'message' => 'Invitation sent successfully',
                ],
            ]);
        } catch (\Exception $e) {
            return back()->withErrors([
                'email' => 'Failed to send invitation: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Resend an invitation.
     */
    public function resend(Request $request, string $slug, TenantInvitation $invitation)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('update', $tenant);

        if ($invitation->tenant_id !== $tenant->id) {
            abort(403, 'This invitation does not belong to this tenant');
        }

        if ($invitation->isAccepted()) {
            return back()->withErrors([
                'invitation' => 'This invitation has already been accepted',
            ]);
        }

        try {
            $invitation->resend();

            // Resend invitation email
            // $invitation->notify(new TenantInvitationNotification($tenant));

            return back()->with([
                'flash' => [
                    'data' => $invitation->fresh()->load('inviter:id,name,email'),
                    'message' => 'Invitation resent successfully',
                ],
            ]);
        } catch (\Exception $e) {
            return back()->withErrors([
                'invitation' => 'Failed to resend invitation: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified invitation.
     */
    public function destroy(Request $request, string $slug, TenantInvitation $invitation)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('update', $tenant);

        if ($invitation->tenant_id !== $tenant->id) {
            abort(403, 'This invitation does not belong to this tenant');
        }

        $invitation->delete();

        return back()->with([
            'flash' => [
                'message' => 'Invitation revoked successfully',
            ],
        ]);
    }

    /**
     * Accept an invitation (public route).
     */
    public function accept(Request $request, string $token)
    {
        $invitation = TenantInvitation::where('token', $token)->firstOrFail();

        if ($invitation->isExpired()) {
            return redirect()->route('dashboard')->withErrors([
                'invitation' => 'This invitation has expired',
            ]);
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('dashboard')->with([
                'flash' => [
                    'message' => 'This invitation has already been accepted',
                ],
            ]);
        }

        try {
            $user = auth()->user();

            // Check if email matches
            if ($user->email !== $invitation->email) {
                return redirect()->route('dashboard')->withErrors([
                    'invitation' => 'This invitation was sent to a different email address',
                ]);
            }

            $invitation->accept($user);

            return redirect()->route('tenants.show', ['slug' => $invitation->tenant->slug])->with([
                'flash' => [
                    'message' => 'You have successfully joined '.$invitation->tenant->name,
                ],
            ]);
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->withErrors([
                'invitation' => 'Failed to accept invitation: '.$e->getMessage(),
            ]);
        }
    }
}
