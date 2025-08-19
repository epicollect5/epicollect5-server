<?php

namespace Tests\Services\Media;

use ec5\Libraries\Utilities\Generators;
use ec5\Services\Media\PhotoSaverService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->overrideStorageDriver('s3');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_429_too_many_requests_error()
    {
        $projectRef = Generators::projectRef();
        $fileName = Uuid::uuid4()->toString(). '_' . time() . '.jpg';
        $disk = 'entry_original';

        // Create a fake uploaded file
        $uploadedFile = File::fake()->image($fileName, 1024, 768);

        // Mock Storage facade - need to mock the actual disk calls
        Storage::shouldReceive('disk')
            ->with($disk)
            ->times(4) // Called 4 times (1 initial + 3 retries)
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->with($projectRef . '/' . $fileName, Mockery::any())
            ->times(4) // Expect 4 calls (1 initial + 3 retries)
            ->andThrow(new S3Exception(
                'Too Many Requests',
                new Command('PutObject'),
                ['response' => new Response(429)]
            ));

        // Assert service returns false when S3 errors occur
        $result = PhotoSaverService::saveImage($projectRef, $uploadedFile, $fileName, $disk);
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_503_service_unavailable_error()
    {
        $projectRef = Generators::projectRef();
        $fileName = Uuid::uuid4()->toString(). '_' . time() . '.jpg';
        $disk = 'entry_original';

        // Create a fake uploaded file
        $uploadedFile = File::fake()->image($fileName, 1024, 768);

        // Mock Storage facade - need to mock the actual disk calls
        Storage::shouldReceive('disk')
            ->with($disk)
            ->times(4) // Called 4 times (1 initial + 3 retries)
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->with($projectRef . '/' . $fileName, Mockery::any())
            ->times(4) // Expect 4 calls (1 initial + 3 retries)
            ->andThrow(new S3Exception(
                'Service Unavailable',
                new Command('PutObject'),
                ['response' => new Response(503)]
            ));

        $result = PhotoSaverService::saveImage($projectRef, $uploadedFile, $fileName, $disk);
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_successfully_saves_image_to_s3()
    {
        $projectRef = Generators::projectRef();
        $fileName = Uuid::uuid4()->toString(). '_' . time() . '.jpg';
        $disk = 'entry_original';

        // Create a fake uploaded file
        $uploadedFile = File::fake()->image($fileName, 1024, 768);

        // Mock Storage facade for successful save
        Storage::shouldReceive('disk')
            ->with($disk)
            ->once()
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->with($projectRef . '/' . $fileName, Mockery::any())
            ->once()
            ->andReturn(true);

        // Assert service returns true on successful save
        $result = PhotoSaverService::saveImage($projectRef, $uploadedFile, $fileName, $disk);
        $this->assertTrue($result);
    }
}
