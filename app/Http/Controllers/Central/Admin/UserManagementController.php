<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\CentralPermission;
use App\Http\Controllers\Controller;
use App\Models\Central\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:'.CentralPermission::USERS_VIEW->value, only: ['index']),
            new Middleware('can:'.CentralPermission::USERS_SHOW->value, only: ['show']),
            new Middleware('can:'.CentralPermission::USERS_DELETE->value, only: ['destroy']),
        ];
    }

    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('roles')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'role' => $user->getRoleName(),
                'role_display_name' => $user->getRoleDisplayName(),
            ]);

        return Inertia::render('central/admin/users/index', [
            'users' => $users,
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(User $user): Response
    {
        $user->load('roles');

        return Inertia::render('central/admin/users/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'role' => $user->getRoleName(),
                'role_display_name' => $user->getRoleDisplayName(),
                'isSuperAdmin' => $user->isSuperAdmin(),
            ],
        ]);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return back()->with('success', __('flash.user.deleted'));
    }
}
