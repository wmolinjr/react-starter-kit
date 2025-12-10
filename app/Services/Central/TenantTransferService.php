<?php

namespace App\Services\Central;

use App\Exceptions\Central\TransferException;
use App\Models\Central\Customer;
use App\Models\Central\Tenant;
use App\Models\Central\TenantTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TenantTransferService
 *
 * Manages tenant ownership transfers between customers.
 *
 * FLOW:
 * 1. Owner initiates transfer (creates pending transfer)
 * 2. Recipient receives email with token
 * 3. Recipient accepts (sets status to accepted)
 * 4. Transfer completes (updates tenant ownership, Resource Syncing handles user sync)
 */
class TenantTransferService
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    /**
     * Initiate a tenant transfer.
     *
     * @param Tenant $tenant Tenant to transfer
     * @param Customer $fromCustomer Current owner
     * @param string $toEmail Recipient email (may be existing or new customer)
     * @param array $options Additional options (transfer_fee, notes, etc.)
     * @return TenantTransfer
     */
    public function initiate(
        Tenant $tenant,
        Customer $fromCustomer,
        string $toEmail,
        array $options = []
    ): TenantTransfer {
        // Validate ownership
        if ($tenant->customer_id !== $fromCustomer->id) {
            throw new TransferException('Only the owner can transfer a tenant.');
        }

        // Check for pending transfers
        $pendingTransfer = TenantTransfer::where('tenant_id', $tenant->id)
            ->pending()
            ->first();

        if ($pendingTransfer) {
            throw new TransferException('A pending transfer already exists for this tenant.');
        }

        // Check if recipient is an existing customer
        $toCustomer = Customer::where('email', $toEmail)->first();

        // Calculate remaining subscription value (if applicable)
        $remainingValue = $this->calculateRemainingSubscriptionValue($tenant);

        return TenantTransfer::create([
            'tenant_id' => $tenant->id,
            'from_customer_id' => $fromCustomer->id,
            'to_customer_id' => $toCustomer?->id,
            'to_email' => $toEmail,
            'transfer_fee' => $options['transfer_fee'] ?? 0,
            'transfer_fee_currency' => $options['currency'] ?? 'brl',
            'remaining_subscription_value' => $remainingValue,
            'token' => Str::random(64),
            'expires_at' => now()->addDays($options['expires_in_days'] ?? 7),
            'notes' => $options['notes'] ?? null,
        ]);
    }

    /**
     * Accept a transfer (by recipient).
     *
     * @param TenantTransfer $transfer
     * @param Customer $acceptingCustomer Customer accepting the transfer
     * @return TenantTransfer
     */
    public function accept(TenantTransfer $transfer, Customer $acceptingCustomer): TenantTransfer
    {
        // Validate transfer can be accepted
        if (!$transfer->canBeAccepted()) {
            throw new TransferException('This transfer cannot be accepted.');
        }

        // Validate recipient
        if ($transfer->to_email !== $acceptingCustomer->email) {
            throw new TransferException('You are not authorized to accept this transfer.');
        }

        $transfer->update([
            'status' => 'accepted',
            'to_customer_id' => $acceptingCustomer->id,
            'accepted_at' => now(),
        ]);

        return $transfer->fresh();
    }

    /**
     * Complete a transfer (actually move ownership).
     *
     * @param TenantTransfer $transfer
     * @return Tenant
     */
    public function complete(TenantTransfer $transfer): Tenant
    {
        if ($transfer->status !== 'accepted') {
            throw new TransferException('Transfer must be accepted before completion.');
        }

        return DB::transaction(function () use ($transfer) {
            $tenant = $transfer->tenant;
            $fromCustomer = $transfer->fromCustomer;
            $toCustomer = $transfer->toCustomer;

            // 1. Remove old owner from tenant (Resource Syncing will delete their Tenant\User)
            $fromCustomer->tenants()->detach($tenant);

            // 2. Update tenant ownership
            $tenant->update([
                'customer_id' => $toCustomer->id,
            ]);

            // 3. Add new owner to tenant (Resource Syncing will create their Tenant\User)
            $toCustomer->tenants()->attach($tenant);

            // 4. Assign owner role to new owner's user
            $tenant->run(function () use ($toCustomer) {
                $user = \App\Models\Tenant\User::where('global_id', $toCustomer->global_id)->first();
                if ($user) {
                    $user->assignRole('owner');
                }
            });

            // 5. Mark transfer as completed
            $transfer->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $tenant->fresh();
        });
    }

    /**
     * Cancel a pending transfer (by initiator).
     *
     * @param TenantTransfer $transfer
     * @param Customer $cancellingCustomer
     * @return TenantTransfer
     */
    public function cancel(TenantTransfer $transfer, Customer $cancellingCustomer): TenantTransfer
    {
        if (!$transfer->canBeCancelled()) {
            throw new TransferException('This transfer cannot be cancelled.');
        }

        if ($transfer->from_customer_id !== $cancellingCustomer->id) {
            throw new TransferException('Only the initiator can cancel this transfer.');
        }

        $transfer->update([
            'status' => 'cancelled',
        ]);

        return $transfer->fresh();
    }

    /**
     * Reject a transfer (by recipient).
     *
     * @param TenantTransfer $transfer
     * @param Customer $rejectingCustomer
     * @return TenantTransfer
     */
    public function reject(TenantTransfer $transfer, Customer $rejectingCustomer): TenantTransfer
    {
        if (!$transfer->canBeAccepted()) {
            throw new TransferException('This transfer cannot be rejected.');
        }

        if ($transfer->to_email !== $rejectingCustomer->email) {
            throw new TransferException('You are not authorized to reject this transfer.');
        }

        $transfer->update([
            'status' => 'rejected',
        ]);

        return $transfer->fresh();
    }

    /**
     * Find a transfer by token (for acceptance link).
     *
     * @param string $token
     * @return TenantTransfer|null
     */
    public function findByToken(string $token): ?TenantTransfer
    {
        return TenantTransfer::where('token', $token)->first();
    }

    /**
     * Get pending transfers for a customer (as recipient).
     *
     * @param Customer $customer
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingTransfersForRecipient(Customer $customer): \Illuminate\Database\Eloquent\Collection
    {
        return TenantTransfer::where('to_email', $customer->email)
            ->pending()
            ->with(['tenant', 'fromCustomer'])
            ->get();
    }

    /**
     * Get transfers initiated by a customer.
     *
     * @param Customer $customer
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getInitiatedTransfers(Customer $customer): \Illuminate\Database\Eloquent\Collection
    {
        return TenantTransfer::where('from_customer_id', $customer->id)
            ->with(['tenant', 'toCustomer'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Expire old pending transfers.
     * Should be called via scheduled command.
     *
     * @return int Number of expired transfers
     */
    public function expireOldTransfers(): int
    {
        return TenantTransfer::where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Calculate remaining subscription value for a tenant.
     *
     * @param Tenant $tenant
     * @return float
     */
    protected function calculateRemainingSubscriptionValue(Tenant $tenant): float
    {
        // Get active subscription via customer
        $subscription = $tenant->getSubscriptionViaCustomer();

        if (!$subscription || !$subscription->active()) {
            return 0;
        }

        // Calculate remaining days until end of billing period
        // This would need actual Stripe subscription data for accurate calculation
        // For now, return 0 as placeholder

        return 0;
    }
}
