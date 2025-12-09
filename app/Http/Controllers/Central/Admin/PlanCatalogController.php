<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\CentralPermission;
use App\Exceptions\Central\PlanException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StorePlanRequest;
use App\Http\Requests\Central\UpdatePlanRequest;
use App\Http\Resources\Central\PlanEditResource;
use App\Http\Resources\Central\PlanResource;
use App\Models\Central\Plan;
use App\Services\Central\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class PlanCatalogController extends Controller implements HasMiddleware
{
    public function __construct(
        protected PlanService $planService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:'.CentralPermission::PLANS_VIEW->value, only: ['index']),
            new Middleware('can:'.CentralPermission::PLANS_CREATE->value, only: ['create', 'store']),
            new Middleware('can:'.CentralPermission::PLANS_EDIT->value, only: ['edit', 'update']),
            new Middleware('can:'.CentralPermission::PLANS_DELETE->value, only: ['destroy']),
            new Middleware('can:'.CentralPermission::PLANS_SYNC->value, only: ['sync', 'syncAll']),
        ];
    }

    public function index(): Response
    {
        $plans = Plan::query()
            ->withCount('tenants')
            ->with('addons')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('central/admin/plans/index', [
            'plans' => PlanResource::collection($plans),
        ]);
    }

    public function create(): Response
    {
        $definitions = $this->planService->getDefinitions();

        return Inertia::render('central/admin/plans/create', [
            'addons' => $this->planService->getAvailableAddons(),
            'featureDefinitions' => $definitions['featureDefinitions'],
            'limitDefinitions' => $definitions['limitDefinitions'],
            'categories' => $definitions['categories'],
        ]);
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        $this->planService->createPlan($request->validated());

        return redirect()->route('central.admin.plans.index')
            ->with('success', __('flash.plan.created'));
    }

    public function edit(Plan $plan): Response
    {
        $plan->load('addons');
        $definitions = $this->planService->getDefinitions();

        return Inertia::render('central/admin/plans/edit', [
            'plan' => new PlanEditResource($plan),
            'addons' => $this->planService->getAvailableAddons(),
            'featureDefinitions' => $definitions['featureDefinitions'],
            'limitDefinitions' => $definitions['limitDefinitions'],
            'categories' => $definitions['categories'],
        ]);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->planService->updatePlan($plan, $request->validated());

        return redirect()->route('central.admin.plans.index')
            ->with('success', __('flash.plan.updated'));
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        try {
            $this->planService->deletePlan($plan);

            return redirect()->route('central.admin.plans.index')
                ->with('success', __('flash.plan.deleted'));
        } catch (PlanException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function sync(Plan $plan): RedirectResponse
    {
        try {
            $this->planService->syncToStripe($plan);

            return back()->with('success', __('flash.plan.synced'));
        } catch (\Exception $e) {
            return back()->with('error', __('flash.plan.sync_failed', ['error' => $e->getMessage()]));
        }
    }

    public function syncAll(): RedirectResponse
    {
        $synced = $this->planService->syncAllToStripe();

        return back()->with('success', __('flash.plan.synced_all', ['count' => $synced]));
    }
}
