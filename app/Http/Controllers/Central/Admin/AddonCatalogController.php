<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\AddonType;
use App\Enums\CentralPermission;
use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\AddonOptionForPlanResource;
use App\Http\Resources\Shared\CategoryOptionResource;
use App\Http\Resources\Shared\FeatureDefinitionResource;
use App\Http\Resources\Shared\LimitDefinitionResource;
use App\Models\Central\Addon;
use App\Models\Central\Plan;
use App\Services\Central\StripeSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AddonCatalogController extends Controller implements HasMiddleware
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
        $addons = Addon::with('plans')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($addon) => $this->transformAddon($addon));

        return Inertia::render('central/admin/catalog/index', [
            'addons' => $addons,
            'types' => AddonType::toFrontendArray(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('central/admin/catalog/create', [
            'types' => AddonType::toFrontendArray(),
            'plans' => AddonOptionForPlanResource::collection(Plan::active()->ordered()->get()),
            'featureDefinitions' => FeatureDefinitionResource::collection($this->getFeatureDefinitions()),
            'limitDefinitions' => LimitDefinitionResource::collection($this->getLimitDefinitions()),
            'categories' => CategoryOptionResource::collection(PlanFeature::categories()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:255', 'unique:addons,slug'],
            'name' => ['required', 'array'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string'],
            'type' => ['required', Rule::enum(AddonType::class)],
            'active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'unit_value' => ['nullable', 'integer', 'min:0'],
            'unit_label' => ['nullable', 'array'],
            'unit_label.*' => ['nullable', 'string', 'max:50'],
            'min_quantity' => ['integer', 'min:1'],
            'max_quantity' => ['nullable', 'integer', 'min:1'],
            'stackable' => ['boolean'],
            'price_monthly' => ['nullable', 'integer', 'min:0'],
            'price_yearly' => ['nullable', 'integer', 'min:0'],
            'price_one_time' => ['nullable', 'integer', 'min:0'],
            'validity_months' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'icon' => ['nullable', 'string', 'max:50'],
            'icon_color' => ['nullable', 'string', 'max:20'],
            'badge' => ['nullable', 'string', 'max:50'],
            'plan_ids' => ['nullable', 'array'],
            'plan_ids.*' => ['exists:plans,id'],
        ]);

        $planIds = $validated['plan_ids'] ?? [];
        unset($validated['plan_ids']);

        // Set currency from system config
        $validated['currency'] = stripe_currency();

        $addon = Addon::create($validated);

        if (! empty($planIds)) {
            $addon->plans()->sync($planIds);
        }

        return redirect()->route('central.admin.catalog.index')
            ->with('success', __('flash.addon.created', ['name' => $addon->name]));
    }

    public function edit(Addon $addon): Response
    {
        $addon->load('plans');

        return Inertia::render('central/admin/catalog/edit', [
            'addon' => $this->transformAddon($addon),
            'types' => AddonType::toFrontendArray(),
            'plans' => AddonOptionForPlanResource::collection(Plan::active()->ordered()->get()),
            'featureDefinitions' => FeatureDefinitionResource::collection($this->getFeatureDefinitions()),
            'limitDefinitions' => LimitDefinitionResource::collection($this->getLimitDefinitions()),
            'categories' => CategoryOptionResource::collection(PlanFeature::categories()),
        ]);
    }

    public function update(Request $request, Addon $addon): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'array'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string'],
            'type' => ['required', Rule::enum(AddonType::class)],
            'active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'unit_value' => ['nullable', 'integer', 'min:0'],
            'unit_label' => ['nullable', 'array'],
            'unit_label.*' => ['nullable', 'string', 'max:50'],
            'min_quantity' => ['integer', 'min:1'],
            'max_quantity' => ['nullable', 'integer', 'min:1'],
            'stackable' => ['boolean'],
            'price_monthly' => ['nullable', 'integer', 'min:0'],
            'price_yearly' => ['nullable', 'integer', 'min:0'],
            'price_one_time' => ['nullable', 'integer', 'min:0'],
            'validity_months' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'icon' => ['nullable', 'string', 'max:50'],
            'icon_color' => ['nullable', 'string', 'max:20'],
            'badge' => ['nullable', 'string', 'max:50'],
            'plan_ids' => ['nullable', 'array'],
            'plan_ids.*' => ['exists:plans,id'],
        ]);

        $planIds = $validated['plan_ids'] ?? [];
        unset($validated['plan_ids']);

        $addon->update($validated);
        $addon->plans()->sync($planIds);

        return redirect()->route('central.admin.catalog.index')
            ->with('success', __('flash.addon.updated'));
    }

    public function destroy(Addon $addon): RedirectResponse
    {
        $name = $addon->name;
        $addon->delete();

        return redirect()->route('central.admin.catalog.index')
            ->with('success', __('flash.addon.deleted', ['name' => $name]));
    }

    public function sync(Addon $addon): RedirectResponse
    {
        $result = $this->syncService->syncAddon($addon);

        if (empty($result['errors'])) {
            return back()->with('success', __('flash.addon.synced', ['name' => $addon->name]));
        }

        return back()->with('error', __('flash.addon.sync_failed', ['errors' => implode(', ', $result['errors'])]));
    }

    public function syncAll(): RedirectResponse
    {
        $results = $this->syncService->syncAll();
        $synced = count(array_filter($results, fn ($r) => empty($r['errors'])));
        $failed = count($results) - $synced;

        $message = "{$synced} addon(s) synced successfully.";
        if ($failed > 0) {
            $message .= " {$failed} failed.";
        }

        return back()->with($failed > 0 ? 'warning' : 'success', $message);
    }

    protected function transformAddon(Addon $addon): array
    {
        return [
            'id' => $addon->id,
            'slug' => $addon->slug,
            'name' => $addon->getTranslations('name'),
            'name_display' => $addon->name,
            'description' => $addon->getTranslations('description'),
            'type' => $addon->type->value ?? $addon->type,
            'type_label' => $addon->type instanceof AddonType ? $addon->type->label() : $addon->type,
            'category' => $addon->category,
            'active' => $addon->active,
            'sort_order' => $addon->sort_order,
            'limit_key' => $addon->limit_key,
            'unit_value' => $addon->unit_value,
            'unit_label' => $addon->getTranslations('unit_label'),
            'min_quantity' => $addon->min_quantity,
            'max_quantity' => $addon->max_quantity,
            'stackable' => $addon->stackable,
            'price_monthly' => $addon->price_monthly,
            'price_yearly' => $addon->price_yearly,
            'price_one_time' => $addon->price_one_time,
            'validity_months' => $addon->validity_months,
            'stripe_product_id' => $addon->stripe_product_id,
            'stripe_price_monthly_id' => $addon->stripe_price_monthly_id,
            'stripe_price_yearly_id' => $addon->stripe_price_yearly_id,
            'stripe_price_one_time_id' => $addon->stripe_price_one_time_id,
            'features' => $addon->features ?? [],
            'icon' => $addon->icon ?? 'Package',
            'icon_color' => $addon->icon_color ?? 'slate',
            'badge' => $addon->badge,
            'is_synced' => (bool) $addon->stripe_product_id,
            'plan_ids' => $addon->plans->pluck('id')->toArray(),
            'plans' => $addon->plans->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->trans('name'),
                'slug' => $p->slug,
            ]),
        ];
    }

    protected function getFeatureDefinitions(): array
    {
        return PlanFeature::toFrontendArray();
    }

    protected function getLimitDefinitions(): array
    {
        return PlanLimit::toFrontendArray();
    }
}
