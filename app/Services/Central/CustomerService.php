<?php

namespace App\Services\Central;

use App\Contracts\Payment\SubscriptionGatewayInterface;
use App\Models\Central\Customer;
use App\Models\Central\Tenant;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CustomerService
 *
 * Manages Customer (billing entity) lifecycle including:
 * - Registration and tenant creation
 * - Customer-Tenant linking (Resource Syncing)
 * - Billing operations via Payment Gateway
 */
class CustomerService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {}

    /**
     * Register a new customer and create their first tenant.
     *
     * Flow:
     * 1. Create Customer in central database
     * 2. Create Tenant with customer_id
     * 3. Attach Customer to Tenant (triggers Resource Syncing to create Tenant\User)
     *
     * @param  array  $customerData  Customer attributes (name, email, password, etc.)
     * @param  array  $tenantData  Tenant attributes (name, slug, plan_id, etc.)
     * @return array{customer: Customer, tenant: Tenant}
     */
    public function register(array $customerData, array $tenantData): array
    {
        return DB::transaction(function () use ($customerData, $tenantData) {
            // 1. Create Customer with unique global_id
            $customer = Customer::create([
                'global_id' => $this->generateGlobalId(),
                'name' => $customerData['name'],
                'email' => $customerData['email'],
                'password' => $customerData['password'], // Will be hashed by model
                'phone' => $customerData['phone'] ?? null,
                'locale' => $customerData['locale'] ?? config('app.locale', 'pt_BR'),
                'currency' => $customerData['currency'] ?? 'brl',
                'billing_address' => $customerData['billing_address'] ?? null,
                'tax_ids' => $customerData['tax_ids'] ?? null,
                'metadata' => $customerData['metadata'] ?? null,
            ]);

            // 2. Create Tenant owned by this customer
            $tenant = Tenant::create([
                'name' => $tenantData['name'],
                'slug' => $tenantData['slug'] ?? Str::slug($tenantData['name']),
                'customer_id' => $customer->id,
                'plan_id' => $tenantData['plan_id'] ?? null,
            ]);

            // 3. Attach Customer to Tenant
            // This triggers CentralResourceAttachedToTenant event
            // Which creates Tenant\User via ResourceSyncing
            $customer->tenants()->attach($tenant);

            return [
                'customer' => $customer->fresh(),
                'tenant' => $tenant->fresh(),
            ];
        });
    }

    /**
     * Add existing customer to an existing tenant.
     * Used when inviting a customer to access another tenant.
     *
     * @param  Customer  $customer  The customer to add
     * @param  Tenant  $tenant  The tenant to grant access to
     * @param  string|null  $role  Role to assign in tenant (owner, admin, member)
     */
    public function addCustomerToTenant(
        Customer $customer,
        Tenant $tenant,
        ?string $role = 'member'
    ): void {
        // Attach via pivot (triggers Resource Syncing)
        $customer->tenants()->attach($tenant);

        // If a specific role is requested, assign it after sync completes
        if ($role) {
            $tenant->run(function () use ($customer, $role) {
                $user = \App\Models\Tenant\User::where('global_id', $customer->global_id)->first();
                if ($user) {
                    $user->assignRole($role);
                }
            });
        }
    }

    /**
     * Remove customer access from a tenant.
     *
     * @param  Customer  $customer  The customer to remove
     * @param  Tenant  $tenant  The tenant to remove access from
     */
    public function removeCustomerFromTenant(Customer $customer, Tenant $tenant): void
    {
        // Detach via pivot (triggers Resource Syncing to delete Tenant\User)
        $customer->tenants()->detach($tenant);
    }

    /**
     * Create a new tenant for an existing customer.
     *
     * @param  Customer  $customer  Owner customer
     * @param  array  $tenantData  Tenant attributes
     */
    public function createTenantForCustomer(Customer $customer, array $tenantData): Tenant
    {
        return DB::transaction(function () use ($customer, $tenantData) {
            $tenant = Tenant::create([
                'name' => $tenantData['name'],
                'slug' => $tenantData['slug'] ?? Str::slug($tenantData['name']),
                'customer_id' => $customer->id,
                'plan_id' => $tenantData['plan_id'] ?? null,
            ]);

            // Attach customer (triggers Resource Syncing)
            $customer->tenants()->attach($tenant);

            // Assign owner role
            $tenant->run(function () use ($customer) {
                $user = \App\Models\Tenant\User::where('global_id', $customer->global_id)->first();
                if ($user) {
                    $user->assignRole('owner');
                }
            });

            return $tenant->fresh();
        });
    }

    /**
     * Find or create a customer by email.
     * Used for transfers and invitations.
     *
     * @param  string  $email  Customer email
     * @param  array  $data  Additional customer data if creating
     * @return array{customer: Customer, created: bool}
     */
    public function findOrCreateByEmail(string $email, array $data = []): array
    {
        $customer = Customer::where('email', $email)->first();

        if ($customer) {
            return ['customer' => $customer, 'created' => false];
        }

        // Create new customer with temporary password
        $customer = Customer::create([
            'global_id' => $this->generateGlobalId(),
            'name' => $data['name'] ?? explode('@', $email)[0],
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'locale' => $data['locale'] ?? config('app.locale', 'pt_BR'),
            'currency' => $data['currency'] ?? 'brl',
        ]);

        return ['customer' => $customer, 'created' => true];
    }

    /**
     * Get customer's billing summary across all tenants.
     */
    public function getBillingSummary(Customer $customer): array
    {
        $activeSubscriptions = $customer->subscriptions()
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->with('items')
            ->get();

        $tenantCount = $customer->ownedTenants()->count();
        $accessibleTenantCount = $customer->tenants()->count();

        return [
            'owned_tenants' => $tenantCount,
            'accessible_tenants' => $accessibleTenantCount,
            'active_subscriptions' => $activeSubscriptions->count(),
            'subscriptions' => $activeSubscriptions,
            'has_default_payment_method' => $customer->hasDefaultPaymentMethod(),
            'default_payment_method' => $customer->defaultPaymentMethod(),
            'stripe_id' => $customer->stripe_id,
            'trial_ends_at' => $customer->trial_ends_at,
        ];
    }

    /**
     * Update customer profile.
     */
    public function updateProfile(Customer $customer, array $data): Customer
    {
        $customer->update(array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'locale' => $data['locale'] ?? null,
            'billing_address' => $data['billing_address'] ?? null,
            'tax_ids' => $data['tax_ids'] ?? null,
        ], fn ($value) => $value !== null));

        return $customer->fresh();
    }

    /**
     * Update customer password.
     */
    public function updatePassword(Customer $customer, string $newPassword): void
    {
        $customer->update([
            'password' => $newPassword, // Will be hashed by model
        ]);
    }

    /**
     * Delete customer and clean up resources.
     */
    public function deleteCustomer(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            // Cancel all active subscriptions via gateway
            foreach ($customer->subscriptions as $subscription) {
                if ($subscription->isActive()) {
                    $this->cancelSubscription($subscription);
                }
            }

            // Transfer ownership of any owned tenants (or delete them)
            // This should be handled by a separate transfer flow in production

            // Soft delete customer (triggers Resource Syncing cascade)
            $customer->delete();
        });
    }

    /**
     * Cancel a subscription via payment gateway.
     */
    public function cancelSubscription(
        \App\Models\Central\Subscription $subscription,
        bool $immediately = false
    ): void {
        $gateway = $this->getGateway($subscription->provider);

        if ($gateway) {
            try {
                $result = $gateway->cancelSubscription($subscription, $immediately);

                if ($result->success) {
                    $subscription->markAsCanceled($result->endsAt);
                } else {
                    Log::error('Failed to cancel subscription via gateway', [
                        'subscription_id' => $subscription->id,
                        'error' => $result->failureMessage,
                    ]);
                    // Still mark as canceled locally
                    $subscription->markAsCanceled();
                }
            } catch (\Exception $e) {
                Log::error('Exception canceling subscription', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
                $subscription->markAsCanceled();
            }
        } else {
            // No gateway, just mark as canceled locally
            $subscription->markAsCanceled();
        }
    }

    /**
     * Get the subscription gateway for a provider.
     */
    protected function getGateway(string $provider): ?SubscriptionGatewayInterface
    {
        $gateway = $this->gatewayManager->driver($provider);

        return $gateway instanceof SubscriptionGatewayInterface ? $gateway : null;
    }

    /**
     * Register a new customer (without tenant).
     * Used for customer portal registration.
     *
     * @param  array  $data  Customer attributes (name, email, password)
     */
    public function registerCustomerOnly(array $data): Customer
    {
        return Customer::create([
            'global_id' => $this->generateGlobalId(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'locale' => $data['locale'] ?? config('app.locale', 'pt_BR'),
            'currency' => $data['currency'] ?? 'brl',
        ]);
    }

    /**
     * Create a new tenant for an existing customer (controller-friendly).
     *
     * @param  Customer  $customer  Owner customer
     * @param  array  $data  Tenant data (name, slug, domain)
     */
    public function createTenant(Customer $customer, array $data): Tenant
    {
        return $this->createTenantForCustomer($customer, [
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);
    }

    /**
     * Update customer billing address.
     */
    public function updateBillingAddress(Customer $customer, array $billingAddress): Customer
    {
        $customer->update([
            'billing_address' => $billingAddress,
        ]);

        // Sync to Stripe if customer exists there
        // Note: Provider address sync handled by webhooks if needed
        // Future: Add gateway->updateCustomer() method for address sync

        return $customer->fresh();
    }

    /**
     * Generate a unique global_id for Resource Syncing.
     */
    protected function generateGlobalId(): string
    {
        return 'cust_'.Str::orderedUuid()->toString();
    }
}
