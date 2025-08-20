<?php

namespace Tests\Services\Media;

use ec5\Libraries\Utilities\Generators;
use ec5\Services\Media\AudioVideoSaverService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Storage;
use Aws\S3\Exception\S3Exception;
use Aws\Command;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class AudioVideoSaverServiceS3Test extends TestCase
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
        $projectRef = Generators::projectRef();
        $fileName = Uuid::uuid4()->toString(). '_' . time() . '.mp4';
        $disk = 'audio';
        $file = ['path' => 'temp/'.$fileName];

        // Mock S3 read stream with fixture data
        Storage::shouldReceive('disk')
            ->with('s3')
            ->once()
            ->andReturnSelf();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'fake audio content');
        rewind($stream);
        Storage::shouldReceive('readStream')
            ->once() // Explicit expectation: stream should be read only once
            ->andReturn($stream);

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
        $file = ['path' => 'temp/'.$fileName];

        // Mock S3 read stream with fixture data
        Storage::shouldReceive('disk')
            ->with('s3')
            ->once()
            ->andReturnSelf();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'fake video content');
        rewind($stream);
        Storage::shouldReceive('readStream')
            ->once() // Explicit expectation: stream should be read only once
            ->andReturn($stream);

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

        // Mock S3 read stream with fixture data
        Storage::shouldReceive('disk')
            ->with('s3')
            ->once()
            ->andReturnSelf();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'fake audio content for success test');
        rewind($stream);
        Storage::shouldReceive('readStream')
            ->once() // Explicit expectation: stream should be read only once
            ->andReturn($stream);

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

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_403_forbidden_error_without_retry()
    {
        $projectRef = 'test-project-ref';
        $fileName = 'test-audio.mp3';
        $disk = 'audio';
        $file = ['path' => 'temp/test-audio.mp3'];

        // Mock S3 read stream with fixture data
        Storage::shouldReceive('disk')
            ->with('s3')
            ->once()
            ->andReturnSelf();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'fake audio content for 403 test');
        rewind($stream);
        Storage::shouldReceive('readStream')
            ->once() // Explicit expectation: stream should be read only once
            ->andReturn($stream);

        // Mock target disk to throw non-retryable S3Exception with 403
        Storage::shouldReceive('disk')
            ->with($disk)
            ->once()
            ->andReturnSelf();
        Storage::shouldReceive('put')
            ->once() // Expect only 1 call (no retries for 403)
            ->andThrow(new S3Exception(
                'Forbidden',
                new Command('PutObject'),
                ['response' => new Response(403)]
            ));

        // Assert service returns false when non-retryable S3 error occurs
        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, true);
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_put_returns_false()
    {
        $projectRef = 'test-project-ref';
        $fileName = 'test-audio.mp3';
        $disk = 'audio';
        $file = ['path' => 'temp/test-audio.mp3'];

        // Mock S3 read stream with fixture data
        Storage::shouldReceive('disk')
            ->with('s3')
            ->once()
            ->andReturnSelf();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'fake audio content for put false test');
        rewind($stream);
        Storage::shouldReceive('readStream')
            ->once()
            ->andReturn($stream);

        // Mock target disk - put() returns false
        Storage::shouldReceive('disk')
            ->with($disk)
            ->times(4) // Called 4 times (1 initial + 3 retries)
            ->andReturnSelf();
        Storage::shouldReceive('put')
            ->times(4) // Expect 4 calls (1 initial + 3 retries)
            ->andReturn(false);

        // Assert service returns false when put() fails
        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, true);
        $this->assertFalse($result);
    }
}
