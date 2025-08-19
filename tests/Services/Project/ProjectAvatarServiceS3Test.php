<?php

namespace Tests\Services\Project;

use ec5\Services\Project\ProjectAvatarService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Mockery;
use Storage;
use Aws\S3\Exception\S3Exception;
use Aws\Command;
use GuzzleHttp\Psr7\Response;

class ProjectAvatarServiceS3Test extends TestCase
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
        $projectRef = 'test-project-ref';

        // Mock Storage facade to throw S3Exception with 429
        Storage::shouldReceive('disk')
            ->with('project_thumb')
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
            ->with('project_thumb')
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
}
