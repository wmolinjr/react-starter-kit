<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\Central\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
    }

    #[Test]
    public function customer_can_store_provider_ids(): void
    {
        $customer = Customer::factory()->create([
            'provider_ids' => [
                'stripe' => 'cus_test_stripe',
                'asaas' => 'cus_test_asaas',
            ],
        ]);

        $this->assertEquals('cus_test_stripe', $customer->provider_ids['stripe']);
        $this->assertEquals('cus_test_asaas', $customer->provider_ids['asaas']);
    }

    #[Test]
    public function customer_can_get_provider_id(): void
    {
        $customer = Customer::factory()->create([
            'provider_ids' => [
                'stripe' => 'cus_test_stripe',
            ],
        ]);

        $this->assertEquals('cus_test_stripe', $customer->getProviderId('stripe'));
        $this->assertNull($customer->getProviderId('asaas'));
    }

    #[Test]
    public function customer_can_set_provider_id(): void
    {
        $customer = Customer::factory()->create([
            'provider_ids' => [],
        ]);

        $customer->setProviderId('stripe', 'cus_new_stripe');

        $this->assertEquals('cus_new_stripe', $customer->getProviderId('stripe'));

        // Verify it's persisted
        $customer->refresh();
        $this->assertEquals('cus_new_stripe', $customer->getProviderId('stripe'));
    }

    #[Test]
    public function customer_can_have_multiple_provider_ids(): void
    {
        $customer = Customer::factory()->create([
            'provider_ids' => [],
        ]);

        $customer->setProviderId('stripe', 'cus_stripe_123');
        $customer->setProviderId('asaas', 'cus_asaas_456');
        $customer->setProviderId('mercadopago', 'cus_mp_789');

        $this->assertEquals('cus_stripe_123', $customer->getProviderId('stripe'));
        $this->assertEquals('cus_asaas_456', $customer->getProviderId('asaas'));
        $this->assertEquals('cus_mp_789', $customer->getProviderId('mercadopago'));
    }

    #[Test]
    public function customer_can_check_if_has_provider(): void
    {
        $customer = Customer::factory()->create([
            'provider_ids' => [
                'stripe' => 'cus_test_stripe',
            ],
        ]);

        $this->assertTrue($customer->hasProviderId('stripe'));
        $this->assertFalse($customer->hasProviderId('asaas'));
    }

    #[Test]
    public function customer_provider_ids_default_to_null_or_empty(): void
    {
        $customer = Customer::factory()->create();

        // Provider IDs may be null or empty array depending on factory
        $this->assertTrue(
            $customer->provider_ids === null || $customer->provider_ids === [],
            'Provider IDs should be null or empty array when not set'
        );
    }

    #[Test]
    public function customer_can_set_provider_id_when_provider_ids_is_null(): void
    {
        $customer = Customer::factory()->create([
            'provider_ids' => null,
        ]);

        $customer->setProviderId('stripe', 'cus_new');

        $this->assertEquals('cus_new', $customer->getProviderId('stripe'));
    }

    #[Test]
    public function customer_can_be_queried_by_provider_id(): void
    {
        Customer::factory()->create([
            'email' => 'customer1@test.com',
            'provider_ids' => ['stripe' => 'cus_stripe_1'],
        ]);

        Customer::factory()->create([
            'email' => 'customer2@test.com',
            'provider_ids' => ['stripe' => 'cus_stripe_2'],
        ]);

        $found = Customer::whereJsonContains('provider_ids->stripe', 'cus_stripe_1')->first();

        $this->assertEquals('customer1@test.com', $found->email);
    }

    #[Test]
    public function customer_can_remove_provider_id(): void
    {
        $customer = Customer::factory()->create([
            'provider_ids' => [
                'stripe' => 'cus_stripe',
                'asaas' => 'cus_asaas',
            ],
        ]);

        $customer->removeProviderCustomerId('stripe');

        $this->assertFalse($customer->hasProviderId('stripe'));
        $this->assertTrue($customer->hasProviderId('asaas'));
    }
}
