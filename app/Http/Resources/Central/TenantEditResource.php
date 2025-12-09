<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * TenantEditResource
 *
 * Tenant data for edit forms.
 */
class TenantEditResource extends BaseResource
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
            'domains' => 'DomainResource[] | undefined',
            'plan' => 'TenantPlanSummary | undefined',
            'plan_id' => 'string | null',
            'plan_features_override' => 'Partial<PlanFeatures>',
            'plan_limits_override' => 'Partial<PlanLimits>',
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

            // Relationships
            'domains' => DomainResource::collection($this->whenLoaded('domains')),
            'plan' => $this->when(
                $this->relationLoaded('plan') && $this->plan,
                fn () => [
                    'id' => $this->plan->id,
                    'name' => $this->plan->name,
                ]
            ),
            'plan_id' => $this->plan_id,

            // Override settings for form
            'plan_features_override' => $this->plan_features_override ?? [],
            'plan_limits_override' => $this->plan_limits_override ?? [],
        ];
    }
}
