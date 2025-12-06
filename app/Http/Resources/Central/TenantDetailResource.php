<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * TenantDetailResource
 *
 * Complete tenant information for show/detail views.
 * Includes users, addons, and full configuration.
 */
class TenantDetailResource extends BaseResource
{
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
        ];
    }
}
