<?php

namespace Tests\Services\Media;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use ec5\Services\Media\PhotoSaverService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Mockery;
use Storage;
use Aws\S3\Exception\S3Exception;
use Aws\Command;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Testing\File;

class PhotoSaverServiceS3Test extends TestCase
{
    use DatabaseTransactions;
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->overrideStorageDriver('s3');
    }

    public function test_service_handles_s3_429_too_many_requests_error()
    {
        $project = factory(Project::class)->create();
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0,
            'total_files' => 0,
            'total_bytes' => 0,
            'form_counts' => json_encode([]),
            'branch_counts' => json_encode([])
        ]);
        $fileName = Uuid::uuid4()->toString(). '_' . time() . '.jpg';
        $disk = 'photo';

        // Create a fake uploaded file
        $uploadedFile = File::fake()->image($fileName, 1024, 768);

        // Alternative approach - mock Storage directly with the full method chain
        Storage::shouldReceive('disk->put')
            ->with($project->ref . '/' . $fileName, Mockery::any())
            ->times(4) // Expect 4 calls (1 initial + 3 retries)
            ->andThrow(new S3Exception(
                'Too Many Requests',
                new Command('PutObject'),
                ['response' => new Response(429)]
            ));

        // Assert service returns false when S3 errors occur
        $result = PhotoSaverService::saveImage($project->ref, $project->id, $uploadedFile, $fileName, $disk);
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_503_service_unavailable_error()
    {
        $project = factory(Project::class)->create();
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0,
            'total_files' => 0,
            'total_bytes' => 0,
            'form_counts' => json_encode([]),
            'branch_counts' => json_encode([])
        ]);
        $fileName = Uuid::uuid4()->toString(). '_' . time() . '.jpg';
        $disk = 'photo';

        // Create a fake uploaded file
        $uploadedFile = File::fake()->image($fileName, 1024, 768);

        Storage::shouldReceive('disk->put')
            //imp: match the  number of args expected, passing any() for the 3rd arg
            ->with($project->ref . '/' . $fileName, Mockery::any())
            ->times(4) // Expect 4 calls (1 initial + 3 retries)
            ->andThrow(new S3Exception(
                'Service Unavailable',
                new Command('PutObject'),
                ['response' => new Response(503)]
            ));

        $result = PhotoSaverService::saveImage($project->ref, $project->id, $uploadedFile, $fileName, $disk);
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_successfully_saves_image_to_s3()
    {
        $project = factory(Project::class)->create();
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0,
            'total_files' => 0,
            'photo_files' => 0,
            'photo_bytes' => 0,
            'audio_files' => 0,
            'audio_bytes' => 0,
            'video_files' => 0,
            'video_bytes' => 0,
            'total_bytes' => 0,
            'form_counts' => json_encode([]),
            'branch_counts' => json_encode([])
        ]);
        $fileName = Uuid::uuid4()->toString(). '_' . time() . '.jpg';
        $disk = 'photo';
        $fileSize = 1024 * 768 * 3; // Approx size for a 1024x768 image

        // Create a fake uploaded file
        $uploadedFile = File::fake()
            ->image($fileName, 1024, 768)
            ->size($fileSize / 1024); // size() expects KB

        $encodedImage = PhotoSaverService::processImage($uploadedFile->getPathname(), [1024, 768], 70);
        $compressedSize = strlen($encodedImage);

        // Mock Storage facade for successful save
        Storage::shouldReceive('disk')
            ->with($disk)
            ->once()
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->with($project->ref . '/' . $fileName, Mockery::any())
            ->once()
            ->andReturn(true);

        // Assert service returns true on successful save
        $result = PhotoSaverService::saveImage($project->ref, $project->id, $uploadedFile, $fileName, $disk);
        $this->assertTrue($result);

        //assert the file size is correctly recorded in project stats
        $this->assertDatabaseHas('project_stats', [
            'project_id' => $project->id,
            'photo_bytes' => $compressedSize,
            'photo_files' => 1,
            'total_bytes' => $compressedSize,
            'total_files' => 1
        ]);
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_403_forbidden_error_without_retry()
    {
        $project = factory(Project::class)->create();
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0,
            'total_files' => 0,
            'total_bytes' => 0,
            'form_counts' => json_encode([]),
            'branch_counts' => json_encode([])
        ]);
        $fileName = 'test-photo.jpg';
        $disk = 'photo';

        // Create a fake uploaded file
        $uploadedFile = File::fake()->image('test.jpg', 100, 100);

        // Mock Storage facade - should only be called once (no retries)
        Storage::shouldReceive('disk')
            ->with($disk)
            ->once()
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->with($project->ref . '/' . $fileName, Mockery::any())
            ->once() // Expect only 1 call (no retries for 403)
            ->andThrow(new S3Exception(
                'Forbidden',
                new Command('PutObject'),
                ['response' => new Response(403)]
            ));

        // Assert service returns false when non-retryable S3 error occurs
        $result = PhotoSaverService::saveImage($project->ref, $project->id, $uploadedFile, $fileName, $disk);
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_put_returns_false()
    {
        $project = factory(Project::class)->create();
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0,
            'total_files' => 0,
            'total_bytes' => 0,
            'form_counts' => json_encode([]),
            'branch_counts' => json_encode([])
        ]);
        $fileName = 'test-photo.jpg';
        $disk = 'photo';

        // Create a fake uploaded file
        $uploadedFile = File::fake()->image('test.jpg', 100, 100);

        // Mock Storage facade - put() returns false
        Storage::shouldReceive('disk')
            ->with($disk)
            ->times(4) // Called 4 times (1 initial + 3 retries)
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->with($project->ref . '/' . $fileName, Mockery::any())
            ->times(4) // Expect 4 calls (1 initial + 3 retries)
            ->andReturn(false);

        // Assert service returns false when put() fails
        $result = PhotoSaverService::saveImage($project->ref, $project->id, $uploadedFile, $fileName, $disk);
        $this->assertFalse($result);
    }
}
