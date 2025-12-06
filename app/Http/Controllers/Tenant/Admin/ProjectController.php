<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreProjectRequest;
use App\Http\Requests\Tenant\UpdateProjectRequest;
use App\Http\Requests\Tenant\UploadFileRequest;
use App\Http\Resources\Tenant\ProjectDetailResource;
use App\Http\Resources\Tenant\ProjectEditResource;
use App\Http\Resources\Tenant\ProjectResource;
use App\Models\Tenant\Project;
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
            new Middleware('permission:'.TenantPermission::PROJECTS_VIEW->value, only: ['index']),
            new Middleware('permission:'.TenantPermission::PROJECTS_CREATE->value, only: ['create', 'store']),
            new Middleware('permission:'.TenantPermission::PROJECTS_UPLOAD->value, only: ['uploadFile']),
            new Middleware('permission:'.TenantPermission::PROJECTS_DOWNLOAD->value, only: ['downloadFile']),
            new Middleware('permission:'.TenantPermission::PROJECTS_DELETE->value, only: ['deleteFile']),

            // Policies (com model binding - verificam ownership)
            new Middleware('can:view,project', only: ['show']),
            new Middleware('can:update,project', only: ['edit', 'update']),
            new Middleware('can:delete,project', only: ['destroy']),
        ];
    }

    /**
     * Lista de projects do tenant.
     *
     * Uses ProjectResource for consistent data transformation.
     */
    public function index()
    {
        $projects = Project::with(['user', 'media'])
            ->latest()
            ->get();

        return Inertia::render('tenant/admin/projects/index', [
            'projects' => ProjectResource::collection($projects),
        ]);
    }

    /**
     * Formulário de criação
     */
    public function create()
    {

        return Inertia::render('tenant/admin/projects/create');
    }

    /**
     * Armazenar novo project
     */
    public function store(StoreProjectRequest $request)
    {
        $validated = $request->validated();

        $project = Project::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('tenant.admin.projects.show', $project)
            ->with('success', __('flash.project.created'));
    }

    /**
     * Exibir project.
     *
     * Uses ProjectDetailResource for consistent data transformation.
     */
    public function show(Project $project)
    {
        $project->load(['user', 'media']);

        return Inertia::render('tenant/admin/projects/show', [
            'project' => new ProjectDetailResource($project),
        ]);
    }

    /**
     * Formulário de edição
     */
    public function edit(Project $project)
    {
        return Inertia::render('tenant/admin/projects/edit', [
            'project' => new ProjectEditResource($project),
        ]);
    }

    /**
     * Atualizar project
     */
    public function update(UpdateProjectRequest $request, Project $project)
    {
        $project->update($request->validated());

        return redirect()->route('tenant.admin.projects.show', $project)
            ->with('success', __('flash.project.updated'));
    }

    /**
     * Remover project
     */
    public function destroy(Project $project)
    {

        $project->delete();

        return redirect()->route('tenant.admin.projects.index')
            ->with('success', __('flash.project.deleted'));
    }

    /**
     * Upload de arquivo
     */
    public function uploadFile(UploadFileRequest $request, Project $project)
    {
        $project->addMediaFromRequest('file')
            ->toMediaCollection($request->input('collection'));

        return back()->with('success', __('flash.project.file_uploaded'));
    }

    /**
     * Download de arquivo
     *
     * MULTI-DATABASE TENANCY: No need to check tenant_id - isolation is at database level.
     * The project is already guaranteed to belong to the current tenant because it's
     * stored in the tenant's dedicated database.
     */
    public function downloadFile(Project $project, Media $media)
    {
        // Verificar se media pertence ao project
        if ($media->model_id !== $project->id || $media->model_type !== Project::class) {
            abort(404);
        }

        return response()->download($media->getPath(), $media->file_name);
    }

    /**
     * Remover arquivo
     *
     * MULTI-DATABASE TENANCY: No need to check tenant_id - isolation is at database level.
     * The project is already guaranteed to belong to the current tenant because it's
     * stored in the tenant's dedicated database.
     */
    public function deleteFile(Project $project, Media $media)
    {
        // Verificar se media pertence ao project
        if ($media->model_id !== $project->id || $media->model_type !== Project::class) {
            abort(404);
        }

        $media->delete();

        return back()->with('success', __('flash.project.file_deleted'));
    }
}
