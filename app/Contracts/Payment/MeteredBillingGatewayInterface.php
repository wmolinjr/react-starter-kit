<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

/**
 * Interface for gateways that support metered/usage-based billing.
 */
interface MeteredBillingGatewayInterface
{
    /**
     * Report usage for a metered subscription item.
     *
     * @param  array{
     *     identifier: string,
     *     event_name: string,
     *     value: int,
     *     timestamp?: int
     * }  $usage
     * @return array{id: string, ...}
     */
    public function createMeterEvent(array $usage): array;

    /**
     * Get usage summary for a subscription item.
     *
     * @return array{total_usage: int, ...}
     */
    public function getUsageSummary(string $subscriptionItemId): array;
}
