<?php

namespace App\Events\Central\Federation;

use App\Models\Central\FederatedUser;
use App\Models\Central\Tenant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a federated user's 2FA settings are changed.
 */
class FederatedUserTwoFactorChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public FederatedUser $federatedUser,
        public Tenant $sourceTenant,
        public array $twoFactorData
    ) {}
}
