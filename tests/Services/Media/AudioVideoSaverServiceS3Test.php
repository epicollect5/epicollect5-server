<?php

namespace Tests\Services\Media;

use ec5\Libraries\Utilities\Generators;
use ec5\Services\Media\AudioVideoSaverService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Mockery;
use Storage;
use Aws\S3\Exception\S3Exception;
use Aws\Command;
use GuzzleHttp\Psr7\Response;

class AudioVideoSaverServiceS3Test extends TestCase
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
        $fileName = Uuid::uuid4()->toString(). '_' . time() . '.mp4';
        $disk = 'audio';
        $file = ['path' => 'temp/'.$fileName];

        // Mock S3 read stream
        Storage::shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();
        Storage::shouldReceive('readStream')
            ->andReturn(fopen('php://memory', 'r'));

        // Mock target disk to throw S3Exception with 429
        Storage::shouldReceive('disk')
            ->with($disk)
            ->andReturnSelf();
        Storage::shouldReceive('put')
            ->times(4) // Expect 4 calls (1 initial + 3 retries)
            ->andThrow(new S3Exception(
                'Too Many Requests',
                new Command('PutObject'),
                ['response' => new Response(429)]
            ));

        // Assert service returns false when S3 errors occur
        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, true);
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_503_service_unavailable_error()
    {
        $projectRef = Generators::projectRef();
        $fileName = Uuid::uuid4()->toString(). '_' . time() . '.mp4';
        $disk = 'video';
        $file = ['path' => 'temp/.'.$fileName];

        // Mock S3 read stream
        Storage::shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();
        Storage::shouldReceive('readStream')
            ->andReturn(fopen('php://memory', 'r'));

        // Mock target disk to throw S3Exception with 503
        Storage::shouldReceive('disk')
            ->with($disk)
            ->andReturnSelf();
        Storage::shouldReceive('put')
            ->times(4) // Expect 4 calls (1 initial + 3 retries)
            ->andThrow(new S3Exception(
                'Service Unavailable',
                new Command('PutObject'),
                ['response' => new Response(503)]
            ));

        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, true);
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_successfully_saves_file_to_s3()
    {
        $projectRef = Generators::projectRef();
        $fileName = Uuid::uuid4()->toString(). '_' . time() . '.mp4';
        $disk = 'audio';
        $file = ['path' => 'temp/'.$fileName];

        // Mock S3 read stream
        Storage::shouldReceive('disk')
            ->with('s3')
            ->once()
            ->andReturnSelf();
        Storage::shouldReceive('readStream')
            ->once()
            ->andReturn(fopen('php://memory', 'r'));

        // Mock target disk for successful save
        Storage::shouldReceive('disk')
            ->with($disk)
            ->once()
            ->andReturnSelf();
        Storage::shouldReceive('put')
            ->once()
            ->andReturn(true);

        // Assert service returns true on successful save
        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, true);
        $this->assertTrue($result);
    }
}
