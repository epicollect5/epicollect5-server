<?php

namespace Tests\Services\Media;

use Aws\Command;
use Aws\S3\Exception\S3Exception;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use ec5\Services\Media\AudioVideoSaverService;
use Exception;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Ramsey\Uuid\Uuid;
use Storage;
use Tests\TestCase;

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
        $projectId = 99999;
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

        $result = AudioVideoSaverService::saveFile($projectRef, $projectId, $file, $fileName, $disk, true);
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_503_service_unavailable_error()
    {
        $projectRef = Generators::projectRef();
        $projectId = 99999;
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

        $result = AudioVideoSaverService::saveFile($projectRef, $projectId, $file, $fileName, $disk, true);
        $this->assertFalse($result);
    }

    public function test_service_successfully_saves_file_to_s3()
    {
        // Create project & stats
        $project = factory(Project::class)->create();
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
        ]);

        $fileName = Uuid::uuid4()->toString() . '_' . time() . '.mp4';
        $disk = 'audio';
        $filePath = 'temp/' . $fileName;
        $fileBytes = 21;

        // --- Mock all Storage interactions ---
        Storage::shouldReceive('disk')
            ->andReturnSelf();

        Storage::shouldReceive('exists')
            ->andReturn(true);

        Storage::shouldReceive('size')
            ->andReturn($fileBytes);

        Storage::shouldReceive('move')
            ->andReturn(true);

        Storage::shouldReceive('delete')
            ->andReturn(true);

        Storage::shouldReceive('put')
            ->andReturn(true);

        // Create fake stream for S3 read
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'fake audio content');
        rewind($stream);

        Storage::shouldReceive('readStream')
            ->andReturn($stream);

        // --- Execute service ---
        $result = AudioVideoSaverService::saveFile(
            $project->ref,
            $project->id,
            ['path' => $filePath],
            $fileName,
            $disk,
            true // fromS3
        );

        // --- Assertions ---
        $this->assertTrue($result);

        $projectStats = ProjectStats::where('project_id', $project->id)->first();

        $this->assertEquals($fileBytes, $projectStats->audio_bytes);
        $this->assertEquals(1, $projectStats->audio_files);
        $this->assertEquals($fileBytes, $projectStats->total_bytes);
        $this->assertEquals(1, $projectStats->total_files);
    }



    /**
     * @throws Exception
     */
    public function test_service_handles_s3_403_forbidden_error_without_retry()
    {
        $projectRef = Generators::projectRef();
        $projectId = 99999;
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
        $result = AudioVideoSaverService::saveFile($projectRef, $projectId, $file, $fileName, $disk, true);
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_put_returns_false()
    {
        $projectRef = Generators::projectRef();
        $projectId = 99999;
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
        $result = AudioVideoSaverService::saveFile($projectRef, $projectId, $file, $fileName, $disk, true);
        $this->assertFalse($result);
    }
}
