<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * ImpersonationTenantResource
 *
 * Tenant information for impersonation page.
 * Simplified tenant data with primary domain.
 */
class ImpersonationTenantResource extends BaseResource
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
            'domain' => 'string | null',
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
            'domain' => $this->primaryDomain()?->domain,
        ];
    }
}
