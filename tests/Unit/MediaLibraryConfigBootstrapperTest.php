<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Bootstrappers\MediaLibraryConfigBootstrapper;
use App\Enums\PlanLimit;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Tests\TestCase;

/**
 * Tests for MediaLibraryConfigBootstrapper.
 *
 * Verifies that max_file_size is correctly overridden per tenant based on plan limits.
 */
class MediaLibraryConfigBootstrapperTest extends TestCase
{
    protected int $originalMaxFileSize;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalMaxFileSize = (int) config('media-library.max_file_size');
    }

    protected function tearDown(): void
    {
        // Restore original config after each test
        config(['media-library.max_file_size' => $this->originalMaxFileSize]);
        parent::tearDown();
    }

    public function test_bootstrap_converts_mb_to_bytes(): void
    {
        // Create a plan with 25MB file upload limit
        $plan = Plan::factory()->create([
            'limits' => [
                PlanLimit::FILE_UPLOAD_SIZE->value => 25,
            ],
        ]);

        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
        ]);

        $bootstrapper = new MediaLibraryConfigBootstrapper();
        $bootstrapper->bootstrap($tenant);

        // 25 MB = 25 * 1024 * 1024 = 26214400 bytes
        $this->assertEquals(26214400, config('media-library.max_file_size'));
    }

    public function test_bootstrap_respects_tenant_override(): void
    {
        // Create a plan with 10MB default
        $plan = Plan::factory()->create([
            'limits' => [
                PlanLimit::FILE_UPLOAD_SIZE->value => 10,
            ],
        ]);

        // Tenant has override of 50MB
        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'plan_limits_override' => [
                PlanLimit::FILE_UPLOAD_SIZE->value => 50,
            ],
        ]);

        $bootstrapper = new MediaLibraryConfigBootstrapper();
        $bootstrapper->bootstrap($tenant);

        // Override takes priority: 50 MB = 52428800 bytes
        $this->assertEquals(52428800, config('media-library.max_file_size'));
    }

    public function test_bootstrap_uses_plan_limit_when_no_override(): void
    {
        // Create a plan with 15MB limit
        $plan = Plan::factory()->create([
            'limits' => [
                PlanLimit::FILE_UPLOAD_SIZE->value => 15,
            ],
        ]);

        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'plan_limits_override' => [], // No override
        ]);

        $bootstrapper = new MediaLibraryConfigBootstrapper();
        $bootstrapper->bootstrap($tenant);

        // 15 MB = 15728640 bytes
        $this->assertEquals(15728640, config('media-library.max_file_size'));
    }

    public function test_bootstrap_uses_default_when_no_plan(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_id' => null,
            'plan_limits_override' => [],
        ]);

        $bootstrapper = new MediaLibraryConfigBootstrapper();
        $bootstrapper->bootstrap($tenant);

        // Default: 10 MB = 10485760 bytes
        $defaultMb = PlanLimit::FILE_UPLOAD_SIZE->defaultValue();
        $this->assertEquals($defaultMb * 1024 * 1024, config('media-library.max_file_size'));
    }

    public function test_revert_restores_original_value(): void
    {
        // Set a known original value BEFORE creating the bootstrapper
        $knownOriginal = 5 * 1024 * 1024; // 5MB
        config(['media-library.max_file_size' => $knownOriginal]);

        // Bootstrapper captures the current config value in constructor
        $bootstrapper = new MediaLibraryConfigBootstrapper();

        // Create a tenant with different limit
        $plan = Plan::factory()->create([
            'limits' => [
                PlanLimit::FILE_UPLOAD_SIZE->value => 30,
            ],
        ]);

        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
        ]);

        // Bootstrap changes the config
        $bootstrapper->bootstrap($tenant);
        $this->assertEquals(30 * 1024 * 1024, config('media-library.max_file_size'));

        // Revert restores the value that was captured at construction time
        $bootstrapper->revert();
        $this->assertEquals($knownOriginal, config('media-library.max_file_size'));
    }

    public function test_config_value_is_integer(): void
    {
        $plan = Plan::factory()->create([
            'limits' => [
                PlanLimit::FILE_UPLOAD_SIZE->value => 20,
            ],
        ]);

        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
        ]);

        $bootstrapper = new MediaLibraryConfigBootstrapper();
        $bootstrapper->bootstrap($tenant);

        $this->assertIsInt(config('media-library.max_file_size'));
    }
}
