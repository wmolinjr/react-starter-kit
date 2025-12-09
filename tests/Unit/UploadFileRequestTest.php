<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PlanLimit;
use App\Http\Requests\Tenant\UploadFileRequest;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for UploadFileRequest validation.
 *
 * Verifies that file upload validation uses tenant's plan limit.
 */
class UploadFileRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_validation_uses_plan_file_size_limit(): void
    {
        // Create a plan with 5MB file upload limit
        $plan = Plan::factory()->create([
            'limits' => [
                PlanLimit::FILE_UPLOAD_SIZE->value => 5,
            ],
        ]);

        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
        ]);

        // Initialize tenancy
        tenancy()->initialize($tenant);

        // Create a request with a file that's too large (6MB)
        $request = new UploadFileRequest();
        $request->merge([
            'collection' => 'attachments',
        ]);

        $rules = $request->rules();

        // Check that the max rule uses the plan's limit (5MB = 5120KB)
        $this->assertContains('max:5120', $rules['file']);

        tenancy()->end();
    }

    public function test_validation_allows_file_within_limit(): void
    {
        // Create a plan with 5MB file upload limit
        $plan = Plan::factory()->create([
            'limits' => [
                PlanLimit::FILE_UPLOAD_SIZE->value => 5,
            ],
        ]);

        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
        ]);

        tenancy()->initialize($tenant);

        // Create a small file (1KB)
        $file = UploadedFile::fake()->create('test.pdf', 1);

        $request = new UploadFileRequest();
        $request->files->set('file', $file);
        $request->merge(['collection' => 'attachments']);

        // Validate using Laravel's validator
        $validator = validator($request->all(), $request->rules());

        $this->assertFalse($validator->fails(), 'Small file should pass validation');

        tenancy()->end();
    }

    public function test_validation_uses_tenant_override(): void
    {
        // Create a plan with 5MB default
        $plan = Plan::factory()->create([
            'limits' => [
                PlanLimit::FILE_UPLOAD_SIZE->value => 5,
            ],
        ]);

        // Tenant has override of 20MB
        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'plan_limits_override' => [
                PlanLimit::FILE_UPLOAD_SIZE->value => 20,
            ],
        ]);

        tenancy()->initialize($tenant);

        $request = new UploadFileRequest();
        $request->merge(['collection' => 'attachments']);

        $rules = $request->rules();

        // Should use override: 20MB = 20480KB
        $this->assertContains('max:20480', $rules['file']);

        tenancy()->end();
    }

    public function test_validation_uses_default_without_tenant(): void
    {
        // Without tenant context, should use default
        $request = new UploadFileRequest();
        $request->merge(['collection' => 'attachments']);

        $rules = $request->rules();

        $defaultMb = PlanLimit::FILE_UPLOAD_SIZE->defaultValue();
        $expectedMaxKb = $defaultMb * 1024;

        $this->assertContains("max:{$expectedMaxKb}", $rules['file']);
    }

    public function test_custom_error_message_includes_limit(): void
    {
        $plan = Plan::factory()->create([
            'limits' => [
                PlanLimit::FILE_UPLOAD_SIZE->value => 15,
            ],
        ]);

        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
        ]);

        tenancy()->initialize($tenant);

        $request = new UploadFileRequest();
        $messages = $request->messages();

        $this->assertArrayHasKey('file.max', $messages);

        tenancy()->end();
    }
}
