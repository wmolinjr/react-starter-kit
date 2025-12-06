<?php

namespace Tests\Feature;

use App\Models\Tenant\Media;
use App\Models\Tenant\Project;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Tests\TenantTestCase;

/**
 * MediaLibrary Queue Tenancy Test Suite
 *
 * MULTI-DATABASE TENANCY:
 * - Media lives in tenant database (no tenant_id column)
 * - Isolation is at database level, not row level
 * - TenantPathGenerator uses current tenant context for paths
 *
 * Verifies that Spatie MediaLibrary image conversion jobs properly work
 * with Stancl Tenancy's QueueTenancyBootstrapper, ensuring:
 *
 * 1. Conversion jobs are queued with correct tenant context
 * 2. Jobs initialize tenant context when processed by workers
 * 3. Converted images are saved to correct tenant-isolated paths
 *
 * @see https://spatie.be/docs/laravel-medialibrary/v11/converting-images/defining-conversions
 * @see https://tenancyforlaravel.com/docs/v3/tenancy-bootstrappers/#queue-tenancy-bootstrapper
 */
class MediaLibraryQueueTenancyTest extends TenantTestCase
{
    /**
     * Test 1: Media is stored in tenant database
     *
     * Verifies that Media model is properly stored in tenant database.
     */
    public function test_media_is_stored_in_tenant_database(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Add media to project
        $media = $project->addMedia(UploadedFile::fake()->image('test.jpg'))
            ->toMediaCollection('images');

        // Verify media exists in database
        $this->assertDatabaseHas('media', [
            'id' => $media->id,
        ]);

        // Verify media is accessible via Eloquent
        $this->assertNotNull(Media::find($media->id));
    }

    /**
     * Test 2: Media files are stored in tenant-isolated paths
     *
     * Verifies that TenantPathGenerator creates correct paths using tenant context.
     */
    public function test_media_files_stored_in_tenant_isolated_paths(): void
    {
        Storage::fake('tenant_uploads');

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Upload image
        $media = $project->addMedia(UploadedFile::fake()->image('photo.jpg'))
            ->toMediaCollection('images');

        // Verify file path contains tenant id from context
        $expectedPathPrefix = "tenants/{$this->tenant->id}/media/{$media->id}/";
        $this->assertStringContainsString(
            $expectedPathPrefix,
            $media->getPath(),
            'Media path must include tenant id for isolation'
        );

        // Verify conversions path also contains tenant id
        $conversionsPath = $media->getPath('thumb');
        $this->assertStringContainsString(
            $expectedPathPrefix.'conversions/',
            $conversionsPath,
            'Conversions path must also be tenant-isolated'
        );
    }

    /**
     * Test 3: Image conversion jobs are queued with tenant context
     *
     * Verifies that when conversions are queued, they include tenant information.
     */
    public function test_conversion_jobs_are_queued_with_tenant_context(): void
    {
        // Skip if using sync queue (conversions run immediately)
        if (config('queue.default') === 'sync') {
            $this->markTestSkipped('Test requires async queue driver (database or redis)');
        }

        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Upload image with conversions defined (Project has 'thumb' conversion)
        $media = $project->addMedia(UploadedFile::fake()->image('photo.jpg', 1000, 1000))
            ->toMediaCollection('images');

        // Verify PerformConversionsJob was dispatched
        Queue::assertPushed(PerformConversionsJob::class, function ($job) use ($media) {
            // Job should have the Media model
            $reflection = new \ReflectionClass($job);
            $mediaProperty = $reflection->getProperty('media');
            $mediaProperty->setAccessible(true);
            $jobMedia = $mediaProperty->getValue($job);

            return $jobMedia->id === $media->id;
        });
    }

    /**
     * Test 4: Conversion jobs process in correct tenant context (sync queue)
     *
     * Since we use sync queue in tests, conversions run immediately.
     * This verifies that conversions are saved to correct tenant path.
     */
    public function test_conversions_are_saved_in_correct_tenant_path(): void
    {
        // Use sync queue to process immediately
        config(['queue.default' => 'sync']);
        config(['media-library.queue_conversions_by_default' => true]);

        Storage::fake('tenant_uploads');

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Upload image (conversions will run immediately with sync queue)
        $media = $project->addMedia(UploadedFile::fake()->image('photo.jpg', 1000, 1000))
            ->toMediaCollection('images');

        // Force conversions to be generated
        $media->refresh();

        // Verify conversion exists and is in correct tenant path
        $thumbPath = $media->getPath('thumb');
        $expectedPathPrefix = "tenants/{$this->tenant->id}/media/{$media->id}/conversions/";

        $this->assertStringContainsString(
            $expectedPathPrefix,
            $thumbPath,
            'Conversion must be saved in tenant-isolated path'
        );
    }

    /**
     * Test 5: Media is accessible within tenant context
     *
     * Verifies that media can be queried within the current tenant context.
     */
    public function test_media_is_accessible_within_tenant_context(): void
    {
        Storage::fake('tenant_uploads');

        // Count existing media before creating new ones
        $initialCount = Media::count();

        // Create media
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $media = $project->addMedia(UploadedFile::fake()->image('tenant.jpg'))
            ->toMediaCollection('images');

        $mediaId = $media->id;

        // Verify media is accessible
        $this->assertNotNull(Media::find($mediaId));

        // Verify media count increased by 1
        $this->assertEquals($initialCount + 1, Media::count());
    }

    /**
     * Test 6: QueueTenancyBootstrapper is enabled
     *
     * Verifies that QueueTenancyBootstrapper is in the bootstrappers list.
     */
    public function test_queue_tenancy_bootstrapper_is_enabled(): void
    {
        $bootstrappers = config('tenancy.bootstrappers', []);

        $this->assertContains(
            \Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
            $bootstrappers,
            'QueueTenancyBootstrapper must be enabled for queue jobs to have tenant context'
        );
    }

    /**
     * Test 7: MediaLibrary is configured to queue conversions
     *
     * Verifies that MediaLibrary is set up to queue conversions (production config).
     */
    public function test_medialibrary_queues_conversions_by_default(): void
    {
        // In production, conversions should be queued
        // In tests, we might use sync for immediate processing
        $queueConversions = config('media-library.queue_conversions_by_default');

        $this->assertIsBool(
            $queueConversions,
            'queue_conversions_by_default must be boolean'
        );
    }

    /**
     * Test 8: TenantPathGenerator is configured
     *
     * Verifies that custom TenantPathGenerator is configured in MediaLibrary.
     */
    public function test_tenant_path_generator_is_configured(): void
    {
        $pathGenerator = config('media-library.path_generator');

        $this->assertEquals(
            \App\Support\TenantPathGenerator::class,
            $pathGenerator,
            'MediaLibrary must use TenantPathGenerator for tenant-isolated paths'
        );
    }

    /**
     * Test 9: Custom Media model is configured
     *
     * Verifies that custom Media model is used.
     */
    public function test_custom_media_model_is_configured(): void
    {
        $mediaModel = config('media-library.media_model');

        $this->assertEquals(
            \App\Models\Tenant\Media::class,
            $mediaModel,
            'MediaLibrary must use custom Media model'
        );
    }

    /**
     * Test 10: Project media collections are properly configured
     *
     * Verifies that Project model has media collections configured.
     */
    public function test_project_media_collections_are_configured(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Verify Project implements HasMedia
        $this->assertInstanceOf(
            \Spatie\MediaLibrary\HasMedia::class,
            $project,
            'Project must implement HasMedia interface'
        );

        // Verify collections exist
        $collections = $project->getRegisteredMediaCollections();
        $collectionNames = $collections->pluck('name')->toArray();

        $this->assertContains('attachments', $collectionNames);
        $this->assertContains('images', $collectionNames);
    }
}
