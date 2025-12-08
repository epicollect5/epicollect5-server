<?php

namespace Tests\Traits\Eloquent;

use ec5\Http\Controllers\Api\Entries\DeleteController;
use ec5\Libraries\Utilities\Common;
use Exception;
use ec5\Libraries\Utilities\Generators;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ramsey\Uuid\Uuid;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;
use Mockery;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\Command;

class RemoverS3Test extends TestCase
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
    public function test_controller_handles_s3_429_deleteObjects_too_many_requests_error()
    {
        $projectRef = Generators::projectRef();
        $projectId = 99999;
        $uuid = Uuid::uuid4()->toString();

        // Mock S3Client
        $mockS3Client = Mockery::mock(S3Client::class);

        // listObjectsV2 works normally
        $mockS3Client->shouldReceive('listObjectsV2')
            ->andReturn([
                'Contents' => [
                    ['Key' => $projectRef.'/'.$uuid.'_'.time().'.jpg'],
                    ['Key' => $projectRef.'/'.$uuid.'_'.time().'.jpg']
                ]
            ]);

        // deleteObjects throws S3Exception with 429
        $mockS3Client->shouldReceive('deleteObjects')
            ->times(4) // Expect 4 calls (1 initial + 3 retries)
            ->andThrow(new S3Exception(
                'Too Many Requests',
                new Command('DeleteObjects'),
                ['response' => new Response(429)]
            ));

        // Partial mock of controller to override S3 client creation
        $controller = Mockery::mock(DeleteController::class)->makePartial();
        $controller->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('createS3Client')->andReturn($mockS3Client);

        // Assert exception and check status code/message
        try {
            $controller->removeMediaChunk($projectRef, $projectId);
            $this->fail('Expected S3Exception was not thrown');
        } catch (S3Exception $e) {
            $this->assertEquals(429, $e->getStatusCode());
            $this->assertEquals('Too Many Requests', $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function test_controller_handles_s3_429_listObjectsV2_too_many_requests_error()
    {
        $projectRef = Generators::projectRef();
        $projectId = 99999;

        // Mock S3Client
        $mockS3Client = Mockery::mock(S3Client::class);

        // listObjectsV2 should throw 429
        $mockS3Client->shouldReceive('listObjectsV2')
            ->times(4) // Expect 4 calls (1 initial + 3 retries)
            ->andThrow(new S3Exception(
                'Too Many Requests',
                new Command('DeleteObjects'),
                ['response' => new Response(429)]
            ));

        // Partial mock of controller to override S3 client creation
        $controller = Mockery::mock(DeleteController::class)->makePartial();
        $controller->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('createS3Client')->andReturn($mockS3Client);

        // Assert exception and check status code/message
        try {
            $controller->removeMediaChunk($projectRef, $projectId);
            $this->fail('Expected S3Exception was not thrown');
        } catch (S3Exception $e) {
            $this->assertEquals(429, $e->getStatusCode());
            $this->assertEquals('Too Many Requests', $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function test_controller_handles_s3_503_deleteObjects_service_unavailable_error()
    {
        $projectRef = Generators::projectRef();
        $projectId = 99999;
        $uuid = Uuid::uuid4()->toString();

        // Mock S3Client at the container level
        $mockS3Client = Mockery::mock(S3Client::class);

        // listObjectsV2 works normally
        $mockS3Client->shouldReceive('listObjectsV2')
            ->andReturn([
                'Contents' => [
                    ['Key' => $projectRef.'/'.$uuid.'_'.time().'.jpg'],
                    ['Key' => $projectRef.'/'.$uuid.'_'.time().'.jpg']
                ]
            ]);

        $mockS3Client->shouldReceive('deleteObjects')
            ->times(4) // Expect 4 calls (1 initial + 3 retries)
            ->andThrow(new S3Exception(
                'Service Unavailable',
                new Command('DeleteObjects'),
                ['response' => new Response(503)]
            ));

        // Partial mock of controller to override S3 client creation
        $controller = Mockery::mock(DeleteController::class)->makePartial();
        $controller->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('createS3Client')->andReturn($mockS3Client);

        // Alternative approach if you need to check status code
        try {
            $controller->removeMediaChunk($projectRef, $projectId);
            $this->fail('Expected S3Exception was not thrown');
        } catch (S3Exception $e) {
            $this->assertEquals(503, $e->getStatusCode());
            $this->assertEquals('Service Unavailable', $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function test_controller_handles_s3_503_listObjectsV2_service_unavailable_error()
    {
        $projectRef = Generators::projectRef();
        $projectId = 99999;

        // Mock S3Client
        $mockS3Client = Mockery::mock(S3Client::class);
        $mockS3Client->shouldReceive('listObjectsV2')
            ->times(4)
            ->andThrow(new S3Exception(
                'Service Unavailable',
                new Command('DeleteObjects'),
                ['response' => new Response(503)]
            ));

        // Test 1: Direct exception assertion
        $controller1 = Mockery::mock(DeleteController::class)->makePartial();
        $controller1->shouldAllowMockingProtectedMethods();
        $controller1->shouldReceive('createS3Client')->andReturn($mockS3Client);

        try {
            $controller1->removeMediaChunk($projectRef, $projectId);
            $this->fail('Expected S3Exception was not thrown');
        } catch (S3Exception $e) {
            $this->assertEquals(503, $e->getStatusCode());
            $this->assertEquals('Service Unavailable', $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function test_is_retryable_error_returns_true_for_retryable_s3_exceptions()
    {
        // 1️⃣ Test retryable HTTP status codes
        $retryableHttpCodes = [429, 500, 502, 503, 504];
        foreach ($retryableHttpCodes as $statusCode) {
            $exception = $this->createMock(S3Exception::class);
            $exception->method('getStatusCode')->willReturn($statusCode);
            $exception->method('getAwsErrorCode')->willReturn(null);

            $this->assertTrue(
                Common::isRetryableError($exception),
                "Expected HTTP $statusCode to be retryable"
            );
        }

        // 2️⃣ Test AWS-specific error codes
        $retryableAwsCodes = [
            'RequestTimeout',
            'ServiceUnavailable',
            'SlowDown',
            'RequestLimitExceeded',
            'InternalError'
        ];

        foreach ($retryableAwsCodes as $awsCode) {
            $exception = $this->createMock(S3Exception::class);
            $exception->method('getStatusCode')->willReturn(400); // non-retryable HTTP
            $exception->method('getAwsErrorCode')->willReturn($awsCode);

            $this->assertTrue(
                Common::isRetryableError($exception),
                "Expected AWS error code $awsCode to be retryable"
            );
        }

        // 3️⃣ Negative test: non-retryable error
        $nonRetryable = $this->createMock(S3Exception::class);
        $nonRetryable->method('getStatusCode')->willReturn(403);
        $nonRetryable->method('getAwsErrorCode')->willReturn(null);

        $this->assertFalse(
            Common::isRetryableError($nonRetryable),
            "Expected non-retryable error to return false"
        );
    }
}
