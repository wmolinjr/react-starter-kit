<?php

namespace Database\Seeders;

use App\Models\Central\Customer;
use App\Models\Central\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * CustomerSeeder
 *
 * Creates Customer billing entities and associates them with existing tenants.
 *
 * CENTRALIZED BILLING ARCHITECTURE (Option E):
 * - Customer = Billing entity (real person who pays)
 * - Customer owns tenants (workspaces)
 * - Customer uses Resource Syncing to sync profile to Tenant\User (owner)
 * - Separate authentication via 'customer' guard at /account/*
 *
 * Test Users Created:
 * - billing@acme.com - Owns tenant1 (Professional) - PT_BR
 * - billing@startup.com - Owns tenant2 (Starter) - EN
 * - billing@enterprise.com - Owns tenant3 (Enterprise) - ES
 *
 * All passwords: 'password'
 */
class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Get existing tenants (created by TenantSeeder)
        $tenant1 = Tenant::where('slug', 'acme')->first();
        $tenant2 = Tenant::where('slug', 'startup')->first();
        $tenant3 = Tenant::where('slug', 'enterprise')->first();

        if (!$tenant1 || !$tenant2 || !$tenant3) {
            $this->command->error('Tenants not found! Run TenantSeeder first.');
            return;
        }

        // Customer 1 - Brazilian (owns Acme/tenant1)
        $customer1 = $this->createCustomer(
            name: 'Acme Billing',
            email: 'billing@acme.com',
            locale: 'pt_BR',
            currency: 'brl',
            phone: '+55 11 99999-9999',
            billingAddress: [
                'line1' => 'Av. Paulista, 1000',
                'line2' => 'Sala 1001',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postal_code' => '01310-100',
                'country' => 'BR',
            ]
        );
        $this->associateTenant($customer1, $tenant1);

        // Customer 2 - American (owns Startup/tenant2)
        $customer2 = $this->createCustomer(
            name: 'Startup Billing',
            email: 'billing@startup.com',
            locale: 'en',
            currency: 'usd',
            phone: '+1 555-123-4567',
            billingAddress: [
                'line1' => '123 Startup Ave',
                'line2' => 'Suite 100',
                'city' => 'San Francisco',
                'state' => 'CA',
                'postal_code' => '94107',
                'country' => 'US',
            ]
        );
        $this->associateTenant($customer2, $tenant2);

        // Customer 3 - Spanish (owns Enterprise/tenant3)
        $customer3 = $this->createCustomer(
            name: 'Enterprise Billing',
            email: 'billing@enterprise.com',
            locale: 'es',
            currency: 'eur',
            phone: '+34 91 123 4567',
            billingAddress: [
                'line1' => 'Calle Gran Vía, 28',
                'line2' => 'Piso 5',
                'city' => 'Madrid',
                'state' => 'Madrid',
                'postal_code' => '28013',
                'country' => 'ES',
            ]
        );
        $this->associateTenant($customer3, $tenant3);

        // Multi-tenant Customer - Owns tenant1 AND tenant2 (for testing multi-workspace scenarios)
        $customer4 = $this->createCustomer(
            name: 'Multi Tenant Owner',
            email: 'multi@example.com',
            locale: 'en',
            currency: 'usd',
            phone: '+1 555-999-8888',
            billingAddress: [
                'line1' => '456 Multi Way',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'US',
            ]
        );
        // Note: This customer has access but doesn't own these tenants
        // Ownership remains with customer1 and customer2

        $this->command->info('');
        $this->command->info('Customers created successfully!');
        $this->command->info('');
        $this->command->info('Login at /account/login:');
        $this->command->info('  - billing@acme.com / password - Owns tenant1 (Professional) [pt_BR, BRL]');
        $this->command->info('  - billing@startup.com / password - Owns tenant2 (Starter) [en, USD]');
        $this->command->info('  - billing@enterprise.com / password - Owns tenant3 (Enterprise) [es, EUR]');
        $this->command->info('  - multi@example.com / password - Multi-tenant test customer [en, USD]');
    }

    /**
     * Create a customer with profile data.
     */
    private function createCustomer(
        string $name,
        string $email,
        string $locale = 'pt_BR',
        string $currency = 'brl',
        ?string $phone = null,
        ?array $billingAddress = null
    ): Customer {
        $customer = Customer::create([
            'global_id' => 'cust_' . Str::orderedUuid()->toString(),
            'name' => $name,
            'email' => $email,
            'password' => 'password', // Will be hashed by model cast
            'locale' => $locale,
            'currency' => $currency,
            'phone' => $phone,
            'billing_address' => $billingAddress,
            'email_verified_at' => now(),
        ]);

        $this->command->info("Created customer: {$name} ({$email})");

        return $customer;
    }

    /**
     * Associate a customer with a tenant as owner.
     */
    private function associateTenant(Customer $customer, Tenant $tenant): void
    {
        // Update tenant ownership
        $tenant->update(['customer_id' => $customer->id]);

        // Attach customer to tenant (triggers Resource Syncing)
        $customer->tenants()->attach($tenant);

        $this->command->info("  -> Associated with tenant: {$tenant->name}");
    }
}
