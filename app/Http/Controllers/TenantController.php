<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TenantController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of user's tenants.
     */
    public function index()
    {
        $tenants = auth()->user()
            ->tenants()
            ->withCount('users')
            ->get()
            ->map(fn($tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
                'status' => $tenant->status,
                'role' => $tenant->pivot->role,
                'users_count' => $tenant->users_count,
                'created_at' => $tenant->created_at->toDateTimeString(),
            ]);

        return Inertia::render('tenants/index', [
            'tenants' => $tenants,
        ]);
    }

    /**
     * Show the form for creating a new tenant.
     */
    public function create()
    {
        return Inertia::render('tenants/create');
    }

    /**
     * Store a newly created tenant in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'alpha_dash', 'unique:tenants,slug'],
        ]);

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);

            // Ensure uniqueness
            $originalSlug = $validated['slug'];
            $counter = 1;
            while (Tenant::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        $tenant = Tenant::create($validated);

        // Attach the creator as owner
        $tenant->users()->attach(auth()->id(), ['role' => 'owner']);

        // Update user's current tenant
        auth()->user()->update(['current_tenant_id' => $tenant->id]);

        return redirect()->route('tenants.index')
            ->with('success', 'Workspace created successfully!');
    }

    /**
     * Display the specified tenant (settings page).
     */
    public function show(string $slug)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();

        $this->authorize('view', $tenant);

        $tenant->load('users');

        return Inertia::render('tenants/settings', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
                'settings' => $tenant->settings,
                'status' => $tenant->status,
                'created_at' => $tenant->created_at->toDateTimeString(),
                'users' => $tenant->users->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                    'joined_at' => $user->pivot->created_at->toDateTimeString(),
                ]),
            ],
        ]);
    }

    /**
     * Update the specified tenant in storage.
     */
    public function update(Request $request, string $slug)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('tenants', 'domain')->ignore($tenant->id),
            ],
            'settings' => ['nullable', 'array'],
        ]);

        $tenant->update($validated);

        return back()->with('success', 'Workspace updated successfully!');
    }

    /**
     * Remove the specified tenant from storage.
     */
    public function destroy(string $slug)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();

        $this->authorize('delete', $tenant);

        // Clear current_tenant_id for all users in this tenant
        $tenant->users()->update(['current_tenant_id' => null]);

        $tenant->delete();

        return redirect()->route('tenants.index')
            ->with('success', 'Workspace deleted successfully!');
    }
}
