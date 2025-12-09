<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * TenantDetailResource
 *
 * Complete tenant information for show/detail views.
 * Includes users, addons, and full configuration.
 */
class TenantDetailResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'slug' => 'string',
            'settings' => 'Record<string, unknown>',
            'created_at' => 'string',
            'updated_at' => 'string',
            'domains' => 'DomainResource[] | undefined',
            'plan' => 'TenantPlanSummary | undefined',
            'addons' => 'AddonSummary[] | undefined',
            'users' => 'TenantUser[]',
            'users_count' => 'number',
            'plan_features_override' => 'Partial<PlanFeatures> | null',
            'plan_limits_override' => 'Partial<PlanLimits> | null',
            'current_usage' => 'PlanUsage | null',
            'trial_ends_at' => 'string | null',
            'is_on_trial' => 'boolean',
            'federation_groups' => 'TenantFederationGroup[] | undefined',
            'federation_groups_count' => 'number | undefined',
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'settings' => $this->settings,
            'created_at' => $this->formatIso($this->created_at),
            'updated_at' => $this->formatIso($this->updated_at),

            // Relationships
            'domains' => DomainResource::collection($this->whenLoaded('domains')),
            'plan' => $this->when(
                $this->relationLoaded('plan') && $this->plan,
                fn () => [
                    'id' => $this->plan->id,
                    'name' => $this->plan->trans('name'),
                    'slug' => $this->plan->slug,
                ]
            ),
            'addons' => $this->when(
                $this->relationLoaded('addons'),
                fn () => $this->addons->map(fn ($addon) => [
                    'id' => $addon->id,
                    'name' => $addon->trans('name'),
                    'slug' => $addon->slug,
                ])
            ),

            // Users from tenant database
            'users' => $this->getUsers()->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
            ]),
            'users_count' => $this->getUserCount(),

            // Plan limits and usage
            'plan_features_override' => $this->plan_features_override,
            'plan_limits_override' => $this->plan_limits_override,
            'current_usage' => $this->current_usage,

            // Subscription info
            'trial_ends_at' => $this->formatIso($this->trial_ends_at),
            'is_on_trial' => $this->isOnTrial(),

            // Federation groups
            'federation_groups' => $this->when(
                $this->relationLoaded('federationGroups'),
                fn () => $this->federationGroups->map(fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'sync_strategy' => $group->sync_strategy,
                    'is_active' => $group->is_active,
                    'federated_users_count' => $group->federated_users_count ?? 0,
                    'master_tenant_id' => $group->master_tenant_id,
                    'is_master' => $group->master_tenant_id === $this->id,
                    'master_tenant' => $group->masterTenant ? [
                        'id' => $group->masterTenant->id,
                        'name' => $group->masterTenant->name,
                    ] : null,
                    // Pivot data
                    'sync_enabled' => $group->pivot->sync_enabled,
                    'joined_at' => $this->formatIso($group->pivot->joined_at),
                    'left_at' => $this->formatIso($group->pivot->left_at),
                ])
            ),
            'federation_groups_count' => $this->whenCounted('federationGroups', fn () => $this->federation_groups_count ?? $this->federationGroups->count()),
        ];
    }
}
