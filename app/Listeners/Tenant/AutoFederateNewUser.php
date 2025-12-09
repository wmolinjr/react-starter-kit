<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\UserCreated;
use App\Exceptions\Tenant\FederationException;
use App\Services\Tenant\FederationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Automatically federates new users when they are created,
 * if the federation group has auto_federate_new_users enabled.
 */
class AutoFederateNewUser implements ShouldQueue
{
    public function __construct(
        protected FederationService $federationService
    ) {}

    public function handle(UserCreated $event): void
    {
        $user = $event->user;

        // Check if tenant is federated
        $group = $this->federationService->getCurrentGroup();
        if (!$group) {
            return;
        }

        // Check if auto-federate is enabled
        if (!$group->shouldAutoFederateNewUsers()) {
            return;
        }

        // Skip if user is already federated
        if ($user->isFederated()) {
            return;
        }

        try {
            $this->federationService->federateUser($user);

            Log::info('Auto-federated new user', [
                'user_id' => $user->id,
                'email' => $user->email,
                'group_id' => $group->id,
            ]);
        } catch (FederationException $e) {
            Log::warning('Failed to auto-federate new user', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
