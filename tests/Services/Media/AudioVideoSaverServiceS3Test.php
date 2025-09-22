<?php

namespace Tests\Services\Media;

use ec5\DTO\ProjectDefinitionDTO;
use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectExtraDTO;
use ec5\DTO\ProjectMappingDTO;
use ec5\DTO\ProjectStatsDTO;
use ec5\Services\Mapping\ProjectMappingService;
use ec5\Services\Media\AudioVideoSaverService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
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
        $project = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
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

        $result = AudioVideoSaverService::saveFile($project, $file, $fileName, $disk, true);
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_503_service_unavailable_error()
    {
        $project = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
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

        $result = AudioVideoSaverService::saveFile($project, $file, $fileName, $disk, true);
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_successfully_saves_file_to_s3()
    {
        $project = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
        $fileName = Uuid::uuid4()->toString(). '_' . time() . '.mp4';
        $disk = 'audio';
        $file = ['path' => 'temp/'.$fileName];
        $fileBytes = 21;

        // Mock S3 read stream with fixture data
        Storage::shouldReceive('disk')
            ->with('s3')
            ->twice()
            ->andReturnSelf();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'fake audio content for success test');
        rewind($stream);
        Storage::shouldReceive('readStream')
            ->once() // Explicit expectation: stream should be read only once
            ->andReturn($stream);

        Storage::shouldReceive('size')
            ->once()
            ->with($file['path'])
            ->andReturn($fileBytes); // match the size of your fake content

        // Mock target disk for successful save
        Storage::shouldReceive('disk')
            ->with($disk)
            ->once()
            ->andReturnSelf();
        Storage::shouldReceive('put')
            ->once()
            ->andReturn(true);

        // Mock ProjectStats model
        $mockStats = Mockery::mock('alias:ec5\Models\Project\ProjectStats');

        // Mock the instance returned by first()
        $mockStatsInstance = Mockery::mock();
        $mockStatsInstance->shouldReceive('adjustTotalBytes')
            ->once()
            ->with($fileBytes)
            ->andReturnTrue(); // or whatever you want

        // Mock the static where() call to return a builder-like object
        $mockStats->shouldReceive('where')
            ->once()
            ->with('project_id', $project->getId())
            ->andReturnSelf();

        $mockStats->shouldReceive('first')
            ->once()
            ->andReturn($mockStatsInstance);

        // Assert service returns true on successful save
        $result = AudioVideoSaverService::saveFile($project, $file, $fileName, $disk, true);
        $this->assertTrue($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_403_forbidden_error_without_retry()
    {
        $project = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
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
        $result = AudioVideoSaverService::saveFile($project, $file, $fileName, $disk, true);
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_put_returns_false()
    {
        $project = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
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
        $result = AudioVideoSaverService::saveFile($project, $file, $fileName, $disk, true);
        $this->assertFalse($result);
    }
}
