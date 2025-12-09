<?php

namespace App\Events\Tenant;

use App\Models\Tenant\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a new tenant user is created.
 *
 * Used by listeners like AutoFederateNewUser to perform
 * post-creation actions.
 */
class UserCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user
    ) {}
}
