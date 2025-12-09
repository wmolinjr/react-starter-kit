<?php

namespace App\Services\Central;

use App\Console\Commands\SyncPlanPermissions;
use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use App\Http\Resources\Central\AddonOptionForPlanResource;
use App\Http\Resources\Shared\CategoryOptionResource;
use App\Http\Resources\Shared\FeatureDefinitionResource;
use App\Http\Resources\Shared\LimitDefinitionResource;
use App\Models\Central\Addon;
use App\Models\Central\Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\StripeClient;

/**
 * PlanService
 *
 * Handles all business logic for plan management in central admin.
 * Includes CRUD operations and Stripe synchronization.
 */
class PlanService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('cashier.secret'));
    }

    /**
     * Get all plans with tenant counts.
     *
     * @return Collection<int, array>
     */
    public function getAllPlans(): Collection
    {
        return Plan::withCount('tenants')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => $this->formatPlanForList($plan));
    }

    /**
     * Format plan for listing page.
     *
     * @return array<string, mixed>
     */
    public function formatPlanForList(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'price' => $plan->price,
            'formatted_price' => $plan->formatted_price,
            'currency' => $plan->currency,
            'billing_period' => $plan->billing_period,
            'stripe_price_id' => $plan->stripe_price_id,
            'features' => $plan->features,
            'limits' => $plan->limits,
            'is_active' => $plan->is_active,
            'is_featured' => $plan->is_featured,
            'badge' => $plan->badge,
            'icon' => $plan->icon,
            'icon_color' => $plan->icon_color,
            'tenants_count' => $plan->tenants_count ?? 0,
            'addons_count' => $plan->addons()->count(),
        ];
    }

    /**
     * Format plan for edit page.
     *
     * @return array<string, mixed>
     */
    public function formatPlanForEdit(Plan $plan): array
    {
        $plan->load('addons');

        return [
            'id' => $plan->id,
            'name' => $plan->getTranslations('name'),
            'name_display' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->getTranslations('description'),
            'price' => $plan->price,
            'currency' => $plan->currency,
            'billing_period' => $plan->billing_period,
            'stripe_price_id' => $plan->stripe_price_id,
            'stripe_product_id' => $plan->stripe_product_id ?? null,
            'features' => $plan->features ?? [],
            'limits' => $plan->limits ?? [],
            'permission_map' => $plan->permission_map ?? [],
            'is_active' => $plan->is_active,
            'is_featured' => $plan->is_featured,
            'badge' => $plan->badge,
            'icon' => $plan->icon ?? 'Layers',
            'icon_color' => $plan->icon_color ?? 'slate',
            'sort_order' => $plan->sort_order,
            'addon_ids' => $plan->addons->pluck('id')->toArray(),
        ];
    }

    /**
     * Get available addons for plan selection.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getAvailableAddons()
    {
        return AddonOptionForPlanResource::collection(
            Addon::active()->orderBy('sort_order')->get()
        );
    }

    /**
     * Get feature and limit definitions for frontend.
     *
     * @return array{featureDefinitions: \Illuminate\Http\Resources\Json\AnonymousResourceCollection, limitDefinitions: \Illuminate\Http\Resources\Json\AnonymousResourceCollection, categories: \Illuminate\Http\Resources\Json\AnonymousResourceCollection}
     */
    public function getDefinitions(): array
    {
        return [
            'featureDefinitions' => FeatureDefinitionResource::collection(PlanFeature::toFrontendArray()),
            'limitDefinitions' => LimitDefinitionResource::collection(PlanLimit::toFrontendArray()),
            'categories' => CategoryOptionResource::collection(PlanFeature::categories()),
        ];
    }

    /**
     * Get limit validation rules for plan creation/update.
     *
     * @return array<string, string>
     */
    public function getLimitValidationRules(): array
    {
        return collect(PlanLimit::values())
            ->mapWithKeys(fn (string $key) => ["limits.{$key}" => 'nullable|integer'])
            ->toArray();
    }

    /**
     * Create a new plan.
     *
     * @param  array<string, mixed>  $data
     */
    public function createPlan(array $data): Plan
    {
        // Generate slug if not provided
        $nameForSlug = $data['name']['en'] ?? reset($data['name']) ?? 'plan';
        $data['slug'] = $data['slug'] ?? Str::slug($nameForSlug);
        $data['currency'] = $data['currency'] ?? stripe_currency();

        $plan = Plan::create($data);

        // Generate permission_map based on enabled features
        $plan->update([
            'permission_map' => SyncPlanPermissions::generatePermissionMap($plan),
        ]);

        // Attach addons
        if (! empty($data['addon_ids'])) {
            $plan->addons()->attach($data['addon_ids'], ['active' => true]);
        }

        return $plan;
    }

    /**
     * Update an existing plan.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePlan(Plan $plan, array $data): Plan
    {
        $plan->update($data);

        // Regenerate permission_map based on enabled features
        $plan->update([
            'permission_map' => SyncPlanPermissions::generatePermissionMap($plan),
        ]);

        // Sync addons
        $addonIds = $data['addon_ids'] ?? [];
        $plan->addons()->sync(
            collect($addonIds)->mapWithKeys(fn ($id) => [$id => ['active' => true]])->toArray()
        );

        return $plan->fresh();
    }

    /**
     * Delete a plan.
     *
     * @throws \App\Exceptions\Central\PlanException
     */
    public function deletePlan(Plan $plan): void
    {
        if ($plan->tenants()->exists()) {
            throw new \App\Exceptions\Central\PlanException(__('flash.plan.cannot_delete_with_tenants'));
        }

        $plan->addons()->detach();
        $plan->delete();
    }

    /**
     * Sync a single plan to Stripe.
     *
     * @throws \Exception
     */
    public function syncToStripe(Plan $plan): void
    {
        try {
            // Create or update Stripe product
            if ($plan->stripe_product_id) {
                $this->stripe->products->update($plan->stripe_product_id, [
                    'name' => $plan->name,
                    'description' => $plan->description,
                ]);
            } else {
                $product = $this->stripe->products->create([
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'metadata' => ['plan_slug' => $plan->slug],
                ]);
                $plan->stripe_product_id = $product->id;
            }

            // Create price if needed
            if ($plan->price > 0 && ! $plan->stripe_price_id) {
                $price = $this->stripe->prices->create([
                    'product' => $plan->stripe_product_id,
                    'unit_amount' => $plan->price,
                    'currency' => $plan->currency ?? stripe_currency(),
                    'recurring' => [
                        'interval' => $plan->billing_period === 'yearly' ? 'year' : 'month',
                    ],
                    'metadata' => ['plan_slug' => $plan->slug],
                ]);
                $plan->stripe_price_id = $price->id;
            }

            $plan->save();
        } catch (\Exception $e) {
            Log::error('Failed to sync plan to Stripe', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Sync all active plans to Stripe.
     *
     * @return int Number of plans synced
     */
    public function syncAllToStripe(): int
    {
        $plans = Plan::active()->get();
        $synced = 0;

        foreach ($plans as $plan) {
            try {
                $this->syncToStripe($plan);
                $synced++;
            } catch (\Exception $e) {
                Log::warning('Failed to sync plan to Stripe during batch sync', [
                    'plan_id' => $plan->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other plans
            }
        }

        return $synced;
    }

    /**
     * Check if plan can be deleted.
     */
    public function canDelete(Plan $plan): bool
    {
        return ! $plan->tenants()->exists();
    }
}
