<?php

namespace Tests\Unit\Services;

use App\Enums\BusinessSector;
use App\Exceptions\Central\AddonException;
use App\Models\Central\Customer;
use App\Models\Central\PendingSignup;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Central\CustomerService;
use App\Services\Central\PaymentSettingsService;
use App\Services\Central\SignupService;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SignupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SignupService $service;

    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // Run central migrations
        $this->artisan('migrate', [
            '--path' => 'database/migrations',
            '--database' => 'central',
        ]);

        // Create dependencies
        $customerService = app(CustomerService::class);
        $gatewayManager = app(PaymentGatewayManager::class);
        $paymentSettingsService = app(PaymentSettingsService::class);

        $this->service = new SignupService(
            $customerService,
            $gatewayManager,
            $paymentSettingsService
        );

        // Create a plan
        $this->plan = Plan::factory()->starter()->create();
    }

    // =========================================================================
    // createPendingSignupWithCustomer Tests (Customer-First Flow)
    // =========================================================================

    #[Test]
    public function it_creates_pending_signup_with_customer(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
            'locale' => 'en',
        ];

        $result = $this->service->createPendingSignupWithCustomer($data);

        $this->assertArrayHasKey('customer', $result);
        $this->assertArrayHasKey('signup', $result);

        $customer = $result['customer'];
        $signup = $result['signup'];

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertInstanceOf(PendingSignup::class, $signup);

        // Customer has account data
        $this->assertEquals('John Doe', $customer->name);
        $this->assertEquals('john@example.com', $customer->email);
        $this->assertEquals('en', $customer->locale);
        $this->assertTrue(Hash::check('secret123', $customer->password));

        // Signup references customer (accessors work)
        $this->assertEquals($customer->id, $signup->customer_id);
        $this->assertEquals('John Doe', $signup->name);
        $this->assertEquals('john@example.com', $signup->email);
        $this->assertEquals('en', $signup->locale);
        $this->assertEquals(PendingSignup::STATUS_PENDING, $signup->status);
        $this->assertNotNull($signup->expires_at);
        $this->assertTrue($signup->expires_at->isAfter(now()->addHours(23)));
    }

    #[Test]
    public function it_creates_pending_signup_with_default_locale(): void
    {
        $data = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret123',
        ];

        $result = $this->service->createPendingSignupWithCustomer($data);

        $this->assertEquals(config('app.locale', 'pt_BR'), $result['customer']->locale);
        $this->assertEquals(config('app.locale', 'pt_BR'), $result['signup']->locale);
    }

    #[Test]
    public function it_creates_pending_signup_for_existing_customer(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Existing Customer',
            'email' => 'existing@example.com',
        ]);

        $signup = $this->service->createPendingSignupForCustomer($customer);

        $this->assertInstanceOf(PendingSignup::class, $signup);
        $this->assertEquals($customer->id, $signup->customer_id);
        $this->assertEquals('Existing Customer', $signup->name);
        $this->assertEquals('existing@example.com', $signup->email);
        $this->assertEquals(PendingSignup::STATUS_PENDING, $signup->status);
    }

    // =========================================================================
    // updateWorkspace Tests
    // =========================================================================

    #[Test]
    public function it_updates_workspace_data(): void
    {
        $signup = PendingSignup::factory()->create();

        $data = [
            'workspace_name' => 'My Company',
            'workspace_slug' => 'my-company',
            'business_sector' => BusinessSector::TECHNOLOGY->value,
            'plan_id' => $this->plan->id,
            'billing_period' => 'monthly',
        ];

        $updatedSignup = $this->service->updateWorkspace($signup, $data);

        $this->assertEquals('My Company', $updatedSignup->workspace_name);
        $this->assertEquals('my-company', $updatedSignup->workspace_slug);
        $this->assertEquals(BusinessSector::TECHNOLOGY, $updatedSignup->business_sector);
        $this->assertEquals($this->plan->id, $updatedSignup->plan_id);
        $this->assertEquals('monthly', $updatedSignup->billing_period);
    }

    #[Test]
    public function it_rejects_workspace_update_for_expired_signup(): void
    {
        $signup = PendingSignup::factory()->expired()->create();

        $data = [
            'workspace_name' => 'My Company',
            'workspace_slug' => 'my-company',
            'business_sector' => BusinessSector::TECHNOLOGY->value,
            'plan_id' => $this->plan->id,
            'billing_period' => 'monthly',
        ];

        $this->expectException(AddonException::class);

        $this->service->updateWorkspace($signup, $data);
    }

    #[Test]
    public function it_rejects_workspace_update_for_already_processed_signup(): void
    {
        $signup = PendingSignup::factory()->processing()->create();

        $data = [
            'workspace_name' => 'My Company',
            'workspace_slug' => 'my-company',
            'business_sector' => BusinessSector::TECHNOLOGY->value,
            'plan_id' => $this->plan->id,
            'billing_period' => 'monthly',
        ];

        $this->expectException(AddonException::class);

        $this->service->updateWorkspace($signup, $data);
    }

    // =========================================================================
    // completeSignup Tests
    // =========================================================================

    #[Test]
    public function it_returns_existing_data_for_already_completed_signup(): void
    {
        $customer = Customer::factory()->create();
        $tenant = Tenant::factory()->create(['customer_id' => $customer->id]);

        $signup = PendingSignup::factory()
            ->withWorkspace($this->plan)
            ->completed()
            ->create([
                'customer_id' => $customer->id,
                'tenant_id' => $tenant->id,
            ]);

        $result = $this->service->completeSignup($signup);

        $this->assertEquals($customer->id, $result['customer']->id);
        $this->assertEquals($tenant->id, $result['tenant']->id);
    }

    #[Test]
    public function it_rejects_complete_for_failed_signup(): void
    {
        $signup = PendingSignup::factory()
            ->withWorkspace($this->plan)
            ->failed()
            ->create();

        $this->expectException(AddonException::class);

        $this->service->completeSignup($signup);
    }

    #[Test]
    public function it_rejects_complete_for_expired_signup(): void
    {
        $signup = PendingSignup::factory()
            ->withWorkspace($this->plan)
            ->expired()
            ->create();

        $this->expectException(AddonException::class);

        $this->service->completeSignup($signup);
    }

    // =========================================================================
    // checkPaymentStatus Tests
    // =========================================================================

    #[Test]
    public function it_returns_pending_status(): void
    {
        $signup = PendingSignup::factory()->create();

        $status = $this->service->checkPaymentStatus($signup);

        $this->assertEquals(PendingSignup::STATUS_PENDING, $status['status']);
        $this->assertFalse($status['is_completed']);
        $this->assertFalse($status['is_expired']);
        $this->assertNull($status['tenant_url']);
        $this->assertNull($status['tenant_id']);
        $this->assertNull($status['failure_reason']);
    }

    #[Test]
    public function it_returns_completed_status_with_tenant_info(): void
    {
        $tenant = Tenant::factory()->create();

        $signup = PendingSignup::factory()
            ->withWorkspace($this->plan)
            ->completed()
            ->create([
                'tenant_id' => $tenant->id,
            ]);

        $status = $this->service->checkPaymentStatus($signup);

        $this->assertEquals(PendingSignup::STATUS_COMPLETED, $status['status']);
        $this->assertTrue($status['is_completed']);
        $this->assertFalse($status['is_expired']);
        $this->assertEquals($tenant->id, $status['tenant_id']);
    }

    #[Test]
    public function it_returns_expired_status(): void
    {
        $signup = PendingSignup::factory()->expired()->create();

        $status = $this->service->checkPaymentStatus($signup);

        $this->assertTrue($status['is_expired']);
    }

    #[Test]
    public function it_returns_failed_status_with_reason(): void
    {
        $signup = PendingSignup::factory()->failed('Insufficient funds')->create();

        $status = $this->service->checkPaymentStatus($signup);

        $this->assertEquals(PendingSignup::STATUS_FAILED, $status['status']);
        $this->assertEquals('Insufficient funds', $status['failure_reason']);
    }

    // =========================================================================
    // findByStripeSession Tests
    // =========================================================================

    #[Test]
    public function it_finds_signup_by_stripe_session(): void
    {
        $signup = PendingSignup::factory()
            ->withStripeSession($this->plan)
            ->create([
                'provider_session_id' => 'cs_test_12345',
            ]);

        $found = $this->service->findByStripeSession('cs_test_12345');

        $this->assertNotNull($found);
        $this->assertEquals($signup->id, $found->id);
    }

    #[Test]
    public function it_returns_null_for_unknown_session(): void
    {
        $found = $this->service->findByStripeSession('cs_test_unknown');

        $this->assertNull($found);
    }

    // =========================================================================
    // findByPaymentId Tests
    // =========================================================================

    #[Test]
    public function it_finds_signup_by_payment_id(): void
    {
        $signup = PendingSignup::factory()
            ->withPixPayment($this->plan)
            ->create([
                'provider_payment_id' => 'pay_12345',
                'payment_provider' => 'asaas',
            ]);

        $found = $this->service->findByPaymentId('pay_12345', 'asaas');

        $this->assertNotNull($found);
        $this->assertEquals($signup->id, $found->id);
    }

    #[Test]
    public function it_returns_null_for_unknown_payment_id(): void
    {
        $found = $this->service->findByPaymentId('pay_unknown', 'stripe');

        $this->assertNull($found);
    }

    // =========================================================================
    // refreshPixQrCode Tests
    // =========================================================================

    #[Test]
    public function it_rejects_refresh_for_non_pix_payment(): void
    {
        $signup = PendingSignup::factory()
            ->withStripeSession($this->plan)
            ->create([
                'payment_method' => 'card',
            ]);

        $this->expectException(AddonException::class);

        $this->service->refreshPixQrCode($signup);
    }
}
