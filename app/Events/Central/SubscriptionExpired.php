<?php

declare(strict_types=1);

namespace App\Events\Central;

use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a subscription grace period expires.
 */
class SubscriptionExpired
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public Tenant $tenant
    ) {}
}
