<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProjectController extends Controller
{
    /**
     * Lista de projects do tenant
     */
    public function index()
    {
        Gate::authorize('viewAny', Project::class);

        $projects = Project::with(['user', 'media'])
            ->latest()
            ->get();

        return Inertia::render('tenant/projects/index', [
            'projects' => $projects,
        ]);
    }

    /**
     * Formulário de criação
     */
    public function create()
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('tenant/projects/create');
    }

    /**
     * Armazenar novo project
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,archived'],
        ]);

        $project = Project::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project created successfully!');
    }

    /**
     * Exibir project
     */
    public function show(Project $project)
    {
        Gate::authorize('view', $project);

        $project->load(['user', 'media']);

        return Inertia::render('tenant/projects/show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'user' => [
                    'id' => $project->user->id,
                    'name' => $project->user->name,
                ],
                'created_at' => $project->created_at->toDateString(),
                'attachments' => $project->getMedia('attachments')->map(fn ($media) => [
                    'id' => $media->id,
                    'name' => $media->file_name,
                    'size' => $media->human_readable_size,
                    'mime_type' => $media->mime_type,
                    'url' => route('projects.media.download', [$project, $media]),
                ]),
                'images' => $project->getMedia('images')->map(fn ($media) => [
                    'id' => $media->id,
                    'name' => $media->file_name,
                    'size' => $media->human_readable_size,
                    'url' => route('projects.media.download', [$project, $media]),
                    'thumb_url' => $media->getUrl('thumb'),
                ]),
            ],
        ]);
    }

    /**
     * Formulário de edição
     */
    public function edit(Project $project)
    {
        Gate::authorize('update', $project);

        return Inertia::render('tenant/projects/edit', [
            'project' => $project,
        ]);
    }

    /**
     * Atualizar project
     */
    public function update(Request $request, Project $project)
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,archived'],
        ]);

        $project->update($validated);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project updated successfully!');
    }

    /**
     * Remover project
     */
    public function destroy(Project $project)
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Project deleted successfully!');
    }

    /**
     * Upload de arquivo
     */
    public function uploadFile(Request $request, Project $project)
    {
        Gate::authorize('update', $project);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB max
            'collection' => ['required', 'in:attachments,images'],
        ]);

        $project->addMediaFromRequest('file')
            ->toMediaCollection($request->input('collection'));

        return back()->with('success', 'File uploaded successfully!');
    }

    /**
     * Download de arquivo
     */
    public function downloadFile(Project $project, Media $media)
    {
        Gate::authorize('view', $project);

        // Verificar se media pertence ao project
        if ($media->model_id !== $project->id || $media->model_type !== Project::class) {
            abort(404);
        }

        // Verificar se media pertence ao tenant atual
        if ($project->tenant_id !== current_tenant_id()) {
            abort(404);
        }

        return response()->download($media->getPath(), $media->file_name);
    }

    /**
     * Remover arquivo
     */
    public function deleteFile(Project $project, Media $media)
    {
        Gate::authorize('update', $project);

        // Verificar se media pertence ao project
        if ($media->model_id !== $project->id || $media->model_type !== Project::class) {
            abort(404);
        }

        // Verificar se media pertence ao tenant atual
        if ($project->tenant_id !== current_tenant_id()) {
            abort(404);
        }

        $media->delete();

        return back()->with('success', 'File deleted successfully!');
    }
}
