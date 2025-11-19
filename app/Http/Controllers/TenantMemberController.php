<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

class TenantMemberController extends Controller
{
    /**
     * Display a listing of members for the tenant.
     */
    public function index(string $slug)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('view', $tenant);

        $members = $tenant->users()
            ->withPivot('role', 'created_at')
            ->orderByPivot('created_at', 'asc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                    'joined_at' => $user->pivot->created_at,
                ];
            });

        return response()->json($members);
    }

    /**
     * Update the specified member's role.
     */
    public function update(Request $request, string $slug, User $user)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'role' => [
                'required',
                'in:owner,admin,member',
            ],
        ]);

        // Check if user is a member of this tenant
        if (! $tenant->users()->where('users.id', $user->id)->exists()) {
            abort(404, 'User is not a member of this workspace');
        }

        // Prevent changing role of last owner
        if ($validated['role'] !== 'owner') {
            $currentRole = $tenant->users()
                ->where('users.id', $user->id)
                ->first()
                ->pivot
                ->role;

            if ($currentRole === 'owner') {
                $ownerCount = $tenant->users()
                    ->wherePivot('role', 'owner')
                    ->count();

                if ($ownerCount <= 1) {
                    return back()->withErrors([
                        'role' => 'Cannot change the role of the last owner. Please assign another owner first.',
                    ]);
                }
            }
        }

        // Update the role
        $tenant->users()->updateExistingPivot($user->id, [
            'role' => $validated['role'],
        ]);

        $updatedUser = $tenant->users()
            ->where('users.id', $user->id)
            ->withPivot('role', 'created_at')
            ->first();

        return back()->with([
            'flash' => [
                'data' => [
                    'id' => $updatedUser->id,
                    'name' => $updatedUser->name,
                    'email' => $updatedUser->email,
                    'role' => $updatedUser->pivot->role,
                    'joined_at' => $updatedUser->pivot->created_at,
                ],
                'message' => 'Member role updated successfully',
            ],
        ]);
    }

    /**
     * Remove the specified member from the tenant.
     */
    public function destroy(Request $request, string $slug, User $user)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('update', $tenant);

        // Check if user is a member of this tenant
        if (! $tenant->users()->where('users.id', $user->id)->exists()) {
            abort(404, 'User is not a member of this workspace');
        }

        // Prevent removing last owner
        $memberRole = $tenant->users()
            ->where('users.id', $user->id)
            ->first()
            ->pivot
            ->role;

        if ($memberRole === 'owner') {
            $ownerCount = $tenant->users()
                ->wherePivot('role', 'owner')
                ->count();

            if ($ownerCount <= 1) {
                return back()->withErrors([
                    'member' => 'Cannot remove the last owner. Please assign another owner first.',
                ]);
            }
        }

        // Remove the user from the tenant
        $tenant->users()->detach($user->id);

        // If this was the user's current tenant, clear it
        if ($user->current_tenant_id === $tenant->id) {
            $user->update(['current_tenant_id' => null]);
        }

        return back()->with([
            'flash' => [
                'message' => 'Member removed successfully',
            ],
        ]);
    }

    /**
     * Transfer ownership to another member.
     */
    public function transferOwnership(Request $request, string $slug, User $user)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('update', $tenant);

        // Only owners can transfer ownership
        $currentUserRole = $tenant->users()
            ->where('users.id', auth()->id())
            ->first()
            ?->pivot
            ?->role;

        if ($currentUserRole !== 'owner') {
            abort(403, 'Only owners can transfer ownership');
        }

        // Check if target user is a member
        if (! $tenant->users()->where('users.id', $user->id)->exists()) {
            abort(404, 'User is not a member of this workspace');
        }

        \DB::transaction(function () use ($tenant, $user) {
            // Demote current user to admin
            $tenant->users()->updateExistingPivot(auth()->id(), [
                'role' => 'admin',
            ]);

            // Promote target user to owner
            $tenant->users()->updateExistingPivot($user->id, [
                'role' => 'owner',
            ]);
        });

        return back()->with([
            'flash' => [
                'message' => 'Ownership transferred successfully to '.$user->name,
            ],
        ]);
    }
}
