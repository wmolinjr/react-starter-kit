<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProjectController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            // Permissions diretas (sem model binding)
            new Middleware('permission:tenant.projects:view', only: ['index']),
            new Middleware('permission:tenant.projects:create', only: ['create', 'store']),
            new Middleware('permission:tenant.projects:upload', only: ['uploadFile']),
            new Middleware('permission:tenant.projects:download', only: ['downloadFile']),
            new Middleware('permission:tenant.projects:delete', only: ['deleteFile']),

            // Policies (com model binding - verificam ownership)
            new Middleware('can:view,project', only: ['show']),
            new Middleware('can:update,project', only: ['edit', 'update']),
            new Middleware('can:delete,project', only: ['destroy']),
        ];
    }

    /**
     * Lista de projects do tenant
     */
    public function index()
    {

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

        return Inertia::render('tenant/projects/create');
    }

    /**
     * Armazenar novo project
     */
    public function store(Request $request)
    {

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

        return Inertia::render('tenant/projects/edit', [
            'project' => $project,
        ]);
    }

    /**
     * Atualizar project
     */
    public function update(Request $request, Project $project)
    {

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

        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Project deleted successfully!');
    }

    /**
     * Upload de arquivo
     */
    public function uploadFile(Request $request, Project $project)
    {

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
