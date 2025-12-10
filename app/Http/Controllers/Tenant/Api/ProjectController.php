<?php

namespace App\Http\Controllers\Tenant\Api;

use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProjectController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            // Require authentication via Sanctum
            'auth:sanctum',

            // View permissions
            new Middleware('permission:'.TenantPermission::PROJECTS_VIEW->value, only: ['index']),

            // Create permission
            new Middleware('permission:'.TenantPermission::PROJECTS_CREATE->value, only: ['store']),

            // Update and delete use authorize() in methods
        ];
    }

    /**
     * Display a listing of the projects.
     */
    public function index(Request $request)
    {
        // Projects are automatically scoped to current tenant via global scope
        $projects = Project::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($projects);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:active,inactive,completed'],
        ]);

        $project = Project::create(array_merge($validated, [
            'user_id' => auth()->id(),
        ]));

        return response()->json($project, 201);
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project)
    {
        // Authorization is automatically handled via Policy
        $this->authorize('view', $project);

        return response()->json($project);
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:active,inactive,completed'],
        ]);

        $project->update($validated);

        return response()->json($project);
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(null, 204);
    }
}
