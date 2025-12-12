<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;

/**
 * CustomerSummaryResource
 *
 * Minimal customer data for contexts like signup wizard.
 * Used when we need to show customer info without full details.
 */
class CustomerSummaryResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'email_verified' => $this->hasVerifiedEmail(),
        ];
    }

    /**
     * TypeScript type definition for auto-generation.
     *
     * @return array<string, string>
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'email' => 'string',
            'locale' => 'string',
            'email_verified' => 'boolean',
        ];
    }
}
