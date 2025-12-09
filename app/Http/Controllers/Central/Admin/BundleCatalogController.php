<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\CentralPermission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\BundleResource;
use App\Models\Central\Addon;
use App\Models\Central\AddonBundle;
use App\Models\Central\Plan;
use App\Services\Central\StripeSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class BundleCatalogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:'.CentralPermission::CATALOG_VIEW->value, only: ['index']),
            new Middleware('can:'.CentralPermission::CATALOG_CREATE->value, only: ['create', 'store']),
            new Middleware('can:'.CentralPermission::CATALOG_EDIT->value, only: ['edit', 'update']),
            new Middleware('can:'.CentralPermission::CATALOG_DELETE->value, only: ['destroy']),
            new Middleware('can:'.CentralPermission::CATALOG_SYNC->value, only: ['sync', 'syncAll']),
        ];
    }

    public function __construct(
        protected StripeSyncService $syncService
    ) {}

    public function index(): Response
    {
        $bundles = AddonBundle::with(['addons', 'plans'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('central/admin/bundles/index', [
            'bundles' => BundleResource::collection($bundles),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('central/admin/bundles/create', [
            'addons' => $this->getAddonsForSelect(),
            'plans' => $this->getPlansForSelect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:255', 'unique:addon_bundles,slug'],
            'name' => ['required', 'array'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string'],
            'active' => ['boolean'],
            'discount_percent' => ['integer', 'min:0', 'max:100'],
            'price_monthly' => ['nullable', 'integer', 'min:0'],
            'price_yearly' => ['nullable', 'integer', 'min:0'],
            'badge' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
            'icon_color' => ['nullable', 'string', 'max:20'],
            'features' => ['nullable', 'array'],
            'features.*' => ['array'],
            'features.*.*' => ['nullable', 'string'],
            'sort_order' => ['integer', 'min:0'],
            'addons' => ['required', 'array', 'min:2'],
            'addons.*.addon_id' => ['required', 'exists:addons,id'],
            'addons.*.quantity' => ['integer', 'min:1'],
            'plan_ids' => ['nullable', 'array'],
            'plan_ids.*' => ['exists:plans,id'],
        ]);

        $addons = $validated['addons'] ?? [];
        $planIds = $validated['plan_ids'] ?? [];
        unset($validated['addons'], $validated['plan_ids']);

        // Set currency from system config
        $validated['currency'] = stripe_currency();

        $bundle = AddonBundle::create($validated);

        // Attach addons with quantities
        $addonAttachments = [];
        foreach ($addons as $index => $addonConfig) {
            $addonAttachments[$addonConfig['addon_id']] = [
                'quantity' => $addonConfig['quantity'] ?? 1,
                'sort_order' => $index,
            ];
        }
        $bundle->addons()->sync($addonAttachments);

        // Attach plans
        if (! empty($planIds)) {
            foreach ($planIds as $planId) {
                $bundle->plans()->attach($planId, ['active' => true]);
            }
        }

        return redirect()->route('central.admin.bundles.index')
            ->with('success', __('flash.bundle.created', ['name' => $bundle->name]));
    }

    public function edit(AddonBundle $bundle): Response
    {
        $bundle->load(['addons', 'plans']);

        return Inertia::render('central/admin/bundles/edit', [
            'bundle' => new BundleResource($bundle),
            'addons' => $this->getAddonsForSelect(),
            'plans' => $this->getPlansForSelect(),
        ]);
    }

    public function update(Request $request, AddonBundle $bundle): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'array'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string'],
            'active' => ['boolean'],
            'discount_percent' => ['integer', 'min:0', 'max:100'],
            'price_monthly' => ['nullable', 'integer', 'min:0'],
            'price_yearly' => ['nullable', 'integer', 'min:0'],
            'badge' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
            'icon_color' => ['nullable', 'string', 'max:20'],
            'features' => ['nullable', 'array'],
            'features.*' => ['array'],
            'features.*.*' => ['nullable', 'string'],
            'sort_order' => ['integer', 'min:0'],
            'addons' => ['required', 'array', 'min:2'],
            'addons.*.addon_id' => ['required', 'exists:addons,id'],
            'addons.*.quantity' => ['integer', 'min:1'],
            'plan_ids' => ['nullable', 'array'],
            'plan_ids.*' => ['exists:plans,id'],
        ]);

        $addons = $validated['addons'] ?? [];
        $planIds = $validated['plan_ids'] ?? [];
        unset($validated['addons'], $validated['plan_ids']);

        $bundle->update($validated);

        // Sync addons with quantities
        $addonAttachments = [];
        foreach ($addons as $index => $addonConfig) {
            $addonAttachments[$addonConfig['addon_id']] = [
                'quantity' => $addonConfig['quantity'] ?? 1,
                'sort_order' => $index,
            ];
        }
        $bundle->addons()->sync($addonAttachments);

        // Sync plans
        $planAttachments = [];
        foreach ($planIds as $planId) {
            $planAttachments[$planId] = ['active' => true];
        }
        $bundle->plans()->sync($planAttachments);

        return redirect()->route('central.admin.bundles.index')
            ->with('success', __('flash.bundle.updated'));
    }

    public function destroy(AddonBundle $bundle): RedirectResponse
    {
        $name = $bundle->name;
        $bundle->delete();

        return redirect()->route('central.admin.bundles.index')
            ->with('success', __('flash.bundle.deleted', ['name' => $name]));
    }

    public function sync(AddonBundle $bundle): RedirectResponse
    {
        $bundle->load('addons');
        $result = $this->syncService->syncBundle($bundle);

        if (empty($result['errors'])) {
            return back()->with('success', __('flash.bundle.synced', ['name' => $bundle->name]));
        }

        return back()->with('error', __('flash.bundle.sync_failed', ['errors' => implode(', ', $result['errors'])]));
    }

    public function syncAll(): RedirectResponse
    {
        $results = $this->syncService->syncAllBundles();
        $synced = count(array_filter($results, fn ($r) => empty($r['errors'])));
        $failed = count($results) - $synced;

        $message = "{$synced} bundle(s) synced successfully.";
        if ($failed > 0) {
            $message .= " {$failed} failed.";
        }

        return back()->with($failed > 0 ? 'warning' : 'success', $message);
    }

    protected function getAddonsForSelect(): array
    {
        return Addon::active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($addon) => [
                'id' => $addon->id,
                'slug' => $addon->slug,
                'name' => $addon->name,
                'type' => $addon->type->value,
                'type_label' => $addon->type->label(),
                'price_monthly' => $addon->price_monthly,
                'price_yearly' => $addon->price_yearly,
            ])
            ->toArray();
    }

    protected function getPlansForSelect(): array
    {
        return Plan::active()
            ->ordered()
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
            ])
            ->toArray();
    }
}
