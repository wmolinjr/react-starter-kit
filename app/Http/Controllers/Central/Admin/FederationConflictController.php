<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\CentralPermission;
use App\Enums\FederationConflictStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\ResolveConflictRequest;
use App\Http\Resources\Central\FederationConflictResource;
use App\Http\Resources\Central\FederationGroupResource;
use App\Models\Central\FederationConflict;
use App\Models\Central\FederationGroup;
use App\Services\Central\FederationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class FederationConflictController extends Controller implements HasMiddleware
{
    public function __construct(
        protected FederationService $federationService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:' . CentralPermission::FEDERATION_VIEW->value, only: ['index', 'show']),
            new Middleware('can:' . CentralPermission::FEDERATION_MANAGE_CONFLICTS->value, only: ['resolve', 'dismiss']),
        ];
    }

    /**
     * List all pending conflicts for a group.
     */
    public function index(FederationGroup $group): Response
    {
        $conflicts = FederationConflict::query()
            ->whereHas('federatedUser', fn ($q) => $q->where('federation_group_id', $group->id))
            ->with('federatedUser.masterTenant')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('central/admin/federation/conflicts', [
            'group' => new FederationGroupResource($group),
            'conflicts' => FederationConflictResource::collection($conflicts),
        ]);
    }

    /**
     * Show a specific conflict.
     */
    public function show(FederationGroup $group, FederationConflict $conflict): Response
    {
        $conflict->load('federatedUser.masterTenant', 'federatedUser.links.tenant');

        return Inertia::render('central/admin/federation/conflict', [
            'group' => new FederationGroupResource($group),
            'conflict' => new FederationConflictResource($conflict),
        ]);
    }

    /**
     * Resolve a conflict.
     */
    public function resolve(ResolveConflictRequest $request, FederationGroup $group, FederationConflict $conflict): RedirectResponse
    {
        $validated = $request->validated();

        $this->federationService->resolveConflict(
            conflict: $conflict,
            resolvedValue: $validated['resolved_value'],
            resolverId: auth('central')->id(),
            resolution: $validated['resolution'],
            notes: $validated['notes'] ?? null
        );

        return redirect()->route('central.admin.federation.conflicts.index', $group)
            ->with('success', __('flash.federation.conflict_resolved'));
    }

    /**
     * Dismiss a conflict (mark as ignored).
     */
    public function dismiss(FederationGroup $group, FederationConflict $conflict): RedirectResponse
    {
        $conflict->update([
            'status' => FederationConflictStatus::DISMISSED,
            'resolved_by' => auth('central')->id(),
            'resolved_at' => now(),
        ]);

        return back()->with('success', __('flash.federation.conflict_dismissed'));
    }
}
