<?php

namespace App\Events\Central\Federation;

use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a tenant joins a federation group.
 */
class TenantJoinedFederation
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public FederationGroup $group,
        public Tenant $tenant
    ) {}
}
