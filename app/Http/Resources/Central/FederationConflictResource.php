<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * FederationConflictResource
 *
 * Resource for federation conflict listing/detail views.
 */
class FederationConflictResource extends BaseResource
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
            'federated_user_id' => $this->federated_user_id,
            'field' => $this->field,
            'conflicting_values' => $this->conflicting_values ?? [],
            'status' => $this->status,
            'resolved_value' => $this->resolved_value,
            'resolution' => $this->resolution,
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->formatIso($this->resolved_at),
            'notes' => $this->notes,
            'created_at' => $this->formatIso($this->created_at),
            'updated_at' => $this->formatIso($this->updated_at),

            // Federated user
            'federated_user' => $this->when(
                $this->relationLoaded('federatedUser'),
                fn () => new FederatedUserResource($this->federatedUser)
            ),
        ];
    }
}
