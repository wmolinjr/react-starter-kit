<?php

namespace Tests\Feature\Central;

use App\Enums\BusinessSector;
use App\Models\Central\Customer;
use App\Models\Central\PendingSignup;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignupTest extends TestCase
{
    use RefreshDatabase;

    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // Run central migrations
        $this->artisan('migrate', [
            '--path' => 'database/migrations',
            '--database' => 'central',
        ]);

        // Create a plan for testing
        $this->plan = Plan::factory()->starter()->create();
    }

    // =========================================================================
    // Pricing Page Tests
    // =========================================================================

    public function test_pricing_page_can_be_rendered(): void
    {
        $response = $this->get(route('central.pricing'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('central/pricing/index')
            ->has('plans')
        );
    }

    public function test_pricing_page_shows_active_plans(): void
    {
        // Create additional plans
        $professional = Plan::factory()->professional()->create(['is_active' => true]);
        $inactive = Plan::factory()->create(['is_active' => false]);

        $response = $this->get(route('central.pricing'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('central/pricing/index')
            ->where('plans', fn ($plans) => count($plans) === 2) // starter + professional
        );
    }

    // =========================================================================
    // Signup Wizard Page Tests
    // =========================================================================

    public function test_signup_page_can_be_rendered(): void
    {
        $response = $this->get(route('central.signup.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('central/signup/index')
            ->has('plans')
            ->has('businessSectors')
            ->has('paymentConfig')
        );
    }

    public function test_signup_page_with_selected_plan(): void
    {
        $response = $this->get(route('central.signup.index', ['plan' => 'starter']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('central/signup/index')
            ->where('selectedPlan', 'starter')
        );
    }

    public function test_signup_page_can_resume_existing_signup(): void
    {
        $signup = PendingSignup::factory()->create();

        $response = $this->get(route('central.signup.index', ['signup_id' => $signup->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('central/signup/index')
            ->has('signup')
        );
    }

    public function test_signup_page_ignores_completed_signup(): void
    {
        $signup = PendingSignup::factory()->completed()->create();

        $response = $this->get(route('central.signup.index', ['signup_id' => $signup->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('central/signup/index')
            ->where('signup', null)
        );
    }

    public function test_signup_page_ignores_expired_signup(): void
    {
        $signup = PendingSignup::factory()->expired()->create();

        $response = $this->get(route('central.signup.index', ['signup_id' => $signup->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('central/signup/index')
            ->where('signup', null)
        );
    }

    // =========================================================================
    // Step 1: Account Data Tests
    // =========================================================================

    public function test_can_create_pending_signup_with_account_data(): void
    {
        $response = $this->postJson(route('central.signup.account.store'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'signup' => [
                'id',
                'name',
                'email',
                'status',
            ],
        ]);

        $this->assertDatabaseHas('pending_signups', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
            'status' => 'pending',
        ]);
    }

    public function test_account_creation_validates_required_fields(): void
    {
        $response = $this->postJson(route('central.signup.account.store'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_account_creation_validates_email_format(): void
    {
        $response = $this->postJson(route('central.signup.account.store'), [
            'name' => 'John Doe',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_account_creation_validates_password_confirmation(): void
    {
        $response = $this->postJson(route('central.signup.account.store'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_account_creation_rejects_duplicate_email_in_customers(): void
    {
        Customer::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson(route('central.signup.account.store'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_account_creation_rejects_duplicate_email_in_pending_signups(): void
    {
        PendingSignup::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson(route('central.signup.account.store'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    // =========================================================================
    // Step 2: Workspace Data Tests
    // =========================================================================

    public function test_can_update_workspace_data(): void
    {
        $signup = PendingSignup::factory()->create();

        $response = $this->patchJson(route('central.signup.workspace.update', $signup), [
            'workspace_name' => 'My Company',
            'workspace_slug' => 'my-company',
            'business_sector' => BusinessSector::TECHNOLOGY->value,
            'plan_id' => $this->plan->id,
            'billing_period' => 'monthly',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'signup' => [
                'id',
                'workspace_name',
                'workspace_slug',
                'business_sector',
                'plan_id',
            ],
        ]);

        $this->assertDatabaseHas('pending_signups', [
            'id' => $signup->id,
            'workspace_name' => 'My Company',
            'workspace_slug' => 'my-company',
            'business_sector' => BusinessSector::TECHNOLOGY->value,
        ]);
    }

    public function test_workspace_update_validates_required_fields(): void
    {
        $signup = PendingSignup::factory()->create();

        $response = $this->patchJson(route('central.signup.workspace.update', $signup), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['workspace_name', 'workspace_slug', 'business_sector', 'plan_id']);
    }

    public function test_workspace_update_rejects_duplicate_slug_in_tenants(): void
    {
        $signup = PendingSignup::factory()->create();
        Tenant::factory()->create(['slug' => 'existing-company']);

        $response = $this->patchJson(route('central.signup.workspace.update', $signup), [
            'workspace_name' => 'My Company',
            'workspace_slug' => 'existing-company',
            'business_sector' => BusinessSector::TECHNOLOGY->value,
            'plan_id' => $this->plan->id,
            'billing_period' => 'monthly',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['workspace_slug']);
    }

    public function test_workspace_update_rejects_duplicate_slug_in_pending_signups(): void
    {
        $signup = PendingSignup::factory()->create();
        PendingSignup::factory()->create(['workspace_slug' => 'existing-company']);

        $response = $this->patchJson(route('central.signup.workspace.update', $signup), [
            'workspace_name' => 'My Company',
            'workspace_slug' => 'existing-company',
            'business_sector' => BusinessSector::TECHNOLOGY->value,
            'plan_id' => $this->plan->id,
            'billing_period' => 'monthly',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['workspace_slug']);
    }

    public function test_workspace_update_allows_own_slug(): void
    {
        $signup = PendingSignup::factory()->withWorkspace($this->plan)->create([
            'workspace_slug' => 'my-company',
        ]);

        $response = $this->patchJson(route('central.signup.workspace.update', $signup), [
            'workspace_name' => 'My Updated Company',
            'workspace_slug' => 'my-company', // Same slug
            'business_sector' => BusinessSector::TECHNOLOGY->value,
            'plan_id' => $this->plan->id,
            'billing_period' => 'yearly',
        ]);

        $response->assertStatus(200);
    }

    public function test_workspace_update_rejects_expired_signup(): void
    {
        $signup = PendingSignup::factory()->expired()->create();

        $response = $this->patchJson(route('central.signup.workspace.update', $signup), [
            'workspace_name' => 'My Company',
            'workspace_slug' => 'my-company',
            'business_sector' => BusinessSector::TECHNOLOGY->value,
            'plan_id' => $this->plan->id,
            'billing_period' => 'monthly',
        ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // Email Validation Tests
    // =========================================================================

    public function test_validate_email_returns_available_for_new_email(): void
    {
        $response = $this->postJson(route('central.signup.validate.email'), [
            'email' => 'new-user@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'available' => true,
        ]);
    }

    public function test_validate_email_returns_unavailable_for_existing_customer(): void
    {
        Customer::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson(route('central.signup.validate.email'), [
            'email' => 'existing@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'available' => false,
        ]);
    }

    public function test_validate_email_returns_unavailable_for_pending_signup(): void
    {
        PendingSignup::factory()->create(['email' => 'pending@example.com']);

        $response = $this->postJson(route('central.signup.validate.email'), [
            'email' => 'pending@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'available' => false,
        ]);
    }

    // =========================================================================
    // Slug Validation Tests
    // =========================================================================

    public function test_validate_slug_returns_available_for_new_slug(): void
    {
        $response = $this->postJson(route('central.signup.validate.slug'), [
            'slug' => 'new-company',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'available' => true,
        ]);
    }

    public function test_validate_slug_returns_unavailable_for_existing_tenant(): void
    {
        Tenant::factory()->create(['slug' => 'existing-company']);

        $response = $this->postJson(route('central.signup.validate.slug'), [
            'slug' => 'existing-company',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'available' => false,
        ]);
    }

    public function test_validate_slug_returns_unavailable_for_pending_signup(): void
    {
        PendingSignup::factory()->create(['workspace_slug' => 'pending-company']);

        $response = $this->postJson(route('central.signup.validate.slug'), [
            'slug' => 'pending-company',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'available' => false,
        ]);
    }

    public function test_validate_slug_allows_own_slug(): void
    {
        $signup = PendingSignup::factory()->create(['workspace_slug' => 'my-company']);

        $response = $this->postJson(route('central.signup.validate.slug'), [
            'slug' => 'my-company',
            'signup_id' => $signup->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'available' => true,
        ]);
    }

    // =========================================================================
    // Status Check Tests
    // =========================================================================

    public function test_can_check_signup_status(): void
    {
        $signup = PendingSignup::factory()->withWorkspace($this->plan)->processing()->create();

        $response = $this->getJson(route('central.signup.status', $signup));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'is_completed',
            'is_expired',
            'tenant_url',
            'tenant_id',
            'failure_reason',
        ]);
    }

    public function test_status_check_returns_tenant_url_when_completed(): void
    {
        $tenant = Tenant::factory()->create();
        $signup = PendingSignup::factory()
            ->withWorkspace($this->plan)
            ->completed()
            ->create([
                'tenant_id' => $tenant->id,
            ]);

        $response = $this->getJson(route('central.signup.status', $signup));

        $response->assertStatus(200);
        $response->assertJson([
            'is_completed' => true,
            'tenant_id' => $tenant->id,
        ]);
    }

    // =========================================================================
    // Success Page Tests
    // =========================================================================

    public function test_success_page_can_be_rendered(): void
    {
        $response = $this->get(route('central.signup.success'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('central/signup/success')
        );
    }

    public function test_success_page_with_signup_id(): void
    {
        $signup = PendingSignup::factory()->completed()->create();

        $response = $this->get(route('central.signup.success', ['signup_id' => $signup->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('central/signup/success')
            ->has('signup')
        );
    }

    // =========================================================================
    // PendingSignup Model Tests
    // =========================================================================

    public function test_pending_signup_is_expired_when_past_expires_at(): void
    {
        $signup = PendingSignup::factory()->create([
            'expires_at' => now()->subHour(),
            'status' => PendingSignup::STATUS_PENDING,
        ]);

        $this->assertTrue($signup->isExpired());
    }

    public function test_pending_signup_is_not_expired_when_future_expires_at(): void
    {
        $signup = PendingSignup::factory()->create([
            'expires_at' => now()->addHour(),
            'status' => PendingSignup::STATUS_PENDING,
        ]);

        $this->assertFalse($signup->isExpired());
    }

    public function test_pending_signup_has_workspace_data(): void
    {
        $signup = PendingSignup::factory()->withWorkspace($this->plan)->create();

        $this->assertTrue($signup->hasWorkspaceData());
    }

    public function test_pending_signup_without_workspace_data(): void
    {
        $signup = PendingSignup::factory()->create();

        $this->assertFalse($signup->hasWorkspaceData());
    }

    public function test_pending_signup_can_be_marked_as_processing(): void
    {
        $signup = PendingSignup::factory()->create();

        $signup->markAsProcessing();

        $this->assertEquals(PendingSignup::STATUS_PROCESSING, $signup->fresh()->status);
    }

    public function test_pending_signup_can_be_marked_as_completed(): void
    {
        $customer = Customer::factory()->create();
        $tenant = Tenant::factory()->create();
        $signup = PendingSignup::factory()->create();

        $signup->markAsCompleted($customer->id, $tenant->id);

        $signup = $signup->fresh();
        $this->assertEquals(PendingSignup::STATUS_COMPLETED, $signup->status);
        $this->assertEquals($customer->id, $signup->customer_id);
        $this->assertEquals($tenant->id, $signup->tenant_id);
        $this->assertNotNull($signup->paid_at);
    }

    public function test_pending_signup_can_be_marked_as_failed(): void
    {
        $signup = PendingSignup::factory()->create();

        $signup->markAsFailed('Payment declined');

        $signup = $signup->fresh();
        $this->assertEquals(PendingSignup::STATUS_FAILED, $signup->status);
        $this->assertEquals('Payment declined', $signup->failure_reason);
    }
}
