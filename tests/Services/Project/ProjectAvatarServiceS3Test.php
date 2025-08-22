<?php

namespace Tests\Services\Project;

use ec5\Libraries\Utilities\Generators;
use ec5\Services\Project\ProjectAvatarService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;
use Storage;
use Aws\S3\Exception\S3Exception;
use Aws\Command;
use GuzzleHttp\Psr7\Response;

class ProjectAvatarServiceS3Test extends TestCase
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

        // Mock Storage facade to throw S3Exception with 429
        Storage::shouldReceive('disk')
            ->with('project')
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->times(4) // Expect 4 calls (1 initial + 3 retries)
            ->andThrow(new S3Exception(
                'Too Many Requests',
                new Command('PutObject'),
                ['response' => new Response(429)]
            ));

        $service = new ProjectAvatarService();

        // Assert service returns false when S3 errors occur
        $result = $service->generate($projectRef, 'Test Project');
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_503_service_unavailable_error()
    {
        $projectRef = 'test-project-ref';

        // Mock Storage facade to throw S3Exception with 503
        Storage::shouldReceive('disk')
            ->with('project')
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->times(4) // Expect 4 calls (1 initial + 3 retries)
            ->andThrow(new S3Exception(
                'Service Unavailable',
                new Command('PutObject'),
                ['response' => new Response(503)]
            ));

        $service = new ProjectAvatarService();

        $result = $service->generate($projectRef, 'Test Project');
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_403_forbidden_error_without_retry()
    {
        $projectRef = Generators::projectRef();

        // Mock Storage facade - should only be called once (no retries)
        Storage::shouldReceive('disk')
            ->with('project')
            ->once()
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->once() // Expect only 1 call (no retries for 403)
            ->andThrow(new S3Exception(
                'Forbidden',
                new Command('PutObject'),
                ['response' => new Response(403)]
            ));

        $service = new ProjectAvatarService();

        // Assert service returns false when non-retryable S3 error occurs
        $result = $service->generate($projectRef, 'Test Project');
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_successfully_generates_avatar_to_s3()
    {
        $projectRef = Generators::projectRef();

        // Mock Storage facade for successful save
        Storage::shouldReceive('disk')
            ->with('project')
            ->once()
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->once()
            ->andReturn(true);

        $service = new ProjectAvatarService();

        // Assert service returns true on successful generation
        $result = $service->generate($projectRef, 'Test Project');
        $this->assertTrue($result);
    }

    /**
     * @throws Exception
     */
    public function test_service_handles_s3_put_returns_false()
    {
        $projectRef = 'test-project-ref';

        // Mock Storage facade - put() returns false
        Storage::shouldReceive('disk')
            ->with('project')
            ->times(4) // Called 4 times (1 initial + 3 retries)
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->times(4) // Expect 4 calls (1 initial + 3 retries)
            ->andReturn(false);

        $service = new ProjectAvatarService();

        // Assert service returns false when put() fails
        $result = $service->generate($projectRef, 'Test Project');
        $this->assertFalse($result);
    }
}
