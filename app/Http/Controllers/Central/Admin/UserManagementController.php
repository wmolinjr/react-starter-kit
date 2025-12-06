<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\CentralPermission;
use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
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
            ->with('tenants')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('central/admin/users/index', [
            'users' => $users,
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(User $user): Response
    {
        $user->load('tenants.domains');

        return Inertia::render('central/admin/users/show', [
            'user' => $user,
        ]);
    }

    public function destroy(User $user)
    {
        $user->tenants()->detach();
        $user->delete();

        return back()->with('success', __('flash.user.deleted'));
    }
}
