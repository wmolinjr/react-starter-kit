<?php

namespace App\Events\Central\Federation;

use App\Models\Central\FederatedUser;
use App\Models\Central\Tenant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a new federated user is created.
 */
class FederatedUserCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public FederatedUser $federatedUser,
        public Tenant $sourceTenant
    ) {}
}
