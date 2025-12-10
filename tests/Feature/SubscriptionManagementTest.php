<?php

namespace Tests\Feature;

use App\Models\Central\Plan;
use App\Services\Tenant\BillingService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * SubscriptionManagement Test Suite
 *
 * Tests subscription lifecycle management (pause, resume, cancel, change plan).
 * Tests focus on validation behavior since actual Stripe API calls require valid keys.
 */
class SubscriptionManagementTest extends TenantTestCase
{
    protected BillingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BillingService::class);
    }

    #[Test]
    public function cancel_subscription_fails_without_subscription(): void
    {
        // Tenant without subscription
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active subscription found');

        $this->service->cancelSubscription($this->tenant);
    }

    #[Test]
    public function resume_subscription_fails_without_subscription(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No subscription found');

        $this->service->resumeSubscription($this->tenant);
    }

    #[Test]
    public function pause_subscription_fails_without_subscription(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active subscription found');

        $this->service->pauseSubscription($this->tenant);
    }

    #[Test]
    public function unpause_subscription_fails_without_subscription(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No subscription found');

        $this->service->unpauseSubscription($this->tenant);
    }

    #[Test]
    public function change_plan_fails_without_subscription(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active subscription found');

        $this->service->changePlan($this->tenant, 'enterprise');
    }

    #[Test]
    public function format_subscription_returns_null_for_no_subscription(): void
    {
        $result = $this->service->formatSubscription(null);

        $this->assertNull($result);
    }

    #[Test]
    public function get_dashboard_data_works_without_subscription(): void
    {
        $data = $this->service->getDashboardData($this->tenant);

        $this->assertArrayHasKey('plan', $data);
        $this->assertArrayHasKey('subscription', $data);
        $this->assertArrayHasKey('usage', $data);
        $this->assertArrayHasKey('costs', $data);
        $this->assertArrayHasKey('nextInvoice', $data);
        $this->assertArrayHasKey('activeAddons', $data);
        $this->assertArrayHasKey('activeBundles', $data);
        $this->assertArrayHasKey('recentInvoices', $data);
        $this->assertArrayHasKey('trialEndsAt', $data);

        // Subscription should be null
        $this->assertNull($data['subscription']);
    }

    #[Test]
    public function get_dashboard_data_includes_plan(): void
    {
        $data = $this->service->getDashboardData($this->tenant);

        // Plan should be the tenant's plan
        $this->assertNotNull($data['plan']);
        $this->assertEquals($this->tenant->plan_id, $data['plan']->id);
    }

    #[Test]
    public function get_billing_overview_returns_array(): void
    {
        $overview = $this->service->getBillingOverview($this->tenant);

        $this->assertIsArray($overview);
        $this->assertArrayHasKey('subscription', $overview);
        $this->assertArrayHasKey('plans', $overview);
        $this->assertArrayHasKey('invoices', $overview);
    }

    #[Test]
    public function has_active_subscription_returns_false_without_subscription(): void
    {
        $result = $this->service->hasActiveSubscription($this->tenant);

        $this->assertFalse($result);
    }

    #[Test]
    public function is_on_trial_returns_false_without_subscription(): void
    {
        $result = $this->service->isOnTrial($this->tenant);

        $this->assertFalse($result);
    }

    #[Test]
    public function get_detailed_invoices_returns_empty_without_stripe(): void
    {
        // Tenant without stripe_id should return empty invoices
        $invoices = $this->service->getDetailedInvoices($this->tenant);

        $this->assertEmpty($invoices);
    }

    #[Test]
    public function billing_controller_requires_permission(): void
    {
        // Create a member user (no billing permissions)
        $memberUser = $this->createTenantUser('member');
        $this->actingAs($memberUser);

        // Attempt to access billing dashboard
        $response = $this->get($this->tenantRoute('tenant.admin.billing.index'));

        // Should be forbidden
        $response->assertStatus(403);
    }

    #[Test]
    public function billing_dashboard_accessible_for_owner(): void
    {
        // Default user is owner with billing permissions
        $response = $this->get($this->tenantRoute('tenant.admin.billing.index'));

        $response->assertStatus(200);
    }

    #[Test]
    public function plans_page_accessible(): void
    {
        $response = $this->get($this->tenantRoute('tenant.admin.billing.plans'));

        $response->assertStatus(200);
    }

    #[Test]
    public function bundles_page_accessible(): void
    {
        $response = $this->get($this->tenantRoute('tenant.admin.billing.bundles'));

        $response->assertStatus(200);
    }

    #[Test]
    public function invoices_page_accessible(): void
    {
        $response = $this->get($this->tenantRoute('tenant.admin.billing.invoices'));

        $response->assertStatus(200);
    }

    #[Test]
    public function cancel_subscription_requires_manage_permission(): void
    {
        // Create a member user (no billing:manage permission)
        $memberUser = $this->createTenantUser('member');
        $this->actingAs($memberUser);

        $response = $this->post($this->tenantRoute('tenant.admin.billing.subscription.cancel'));

        // Should be forbidden
        $response->assertStatus(403);
    }

    #[Test]
    public function pause_subscription_requires_manage_permission(): void
    {
        $memberUser = $this->createTenantUser('member');
        $this->actingAs($memberUser);

        $response = $this->post($this->tenantRoute('tenant.admin.billing.subscription.pause'));

        $response->assertStatus(403);
    }
}
