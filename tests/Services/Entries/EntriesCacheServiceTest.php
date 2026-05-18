<?php

namespace Tests\Services\Entries;

use ec5\Services\Entries\EntriesCacheService;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class EntriesCacheServiceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_streamed_export_response_is_not_cached(): void
    {
        $service = app(EntriesCacheService::class);
        $projectSlug = 'test-project';
        $fullUrl = 'http://localhost/api/export/entries/test-project?format=csv';
        $cacheTTL = 123;
        $cacheKey = $service->getExportEntriesCacheKey($projectSlug, $fullUrl);

        $response = $service->rememberExportEntriesResponse(
            $projectSlug,
            $fullUrl,
            $cacheTTL,
            function () {
                return new StreamedResponse(function () {
                    echo 'csv';
                });
            }
        );

        $this->assertNull(Cache::get($cacheKey));
        $this->assertSame('bypass', $response->headers->get('X-Epicollect-Cache'));
        $this->assertSame('123', $response->headers->get('X-Epicollect-Cache-Ttl'));
    }

    public function test_non_successful_export_response_is_not_cached(): void
    {
        $service = app(EntriesCacheService::class);
        $projectSlug = 'test-project';
        $fullUrl = 'http://localhost/api/export/entries/test-project?format=json';
        $cacheTTL = 123;
        $cacheKey = $service->getExportEntriesCacheKey($projectSlug, $fullUrl);

        $response = $service->rememberExportEntriesResponse(
            $projectSlug,
            $fullUrl,
            $cacheTTL,
            function () {
                return new Response('error', 400);
            }
        );

        $this->assertNull(Cache::get($cacheKey));
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('bypass', $response->headers->get('X-Epicollect-Cache'));
        $this->assertSame('123', $response->headers->get('X-Epicollect-Cache-Ttl'));
    }

    public function test_cached_response_preserves_content_type(): void
    {
        $service = app(EntriesCacheService::class);
        $projectSlug = 'test-project';
        $fullUrl = 'http://localhost/api/export/entries/test-project?format=json';
        $cacheTTL = 123;
        $cacheKey = $service->getExportEntriesCacheKey($projectSlug, $fullUrl);

        $firstResponse = $service->rememberExportEntriesResponse(
            $projectSlug,
            $fullUrl,
            $cacheTTL,
            function () {
                return response()->json(['ok' => true], 200, [
                    'Content-Type' => 'application/vnd.api+json; charset=utf-8',
                ]);
            }
        );

        $secondResponse = $service->rememberExportEntriesResponse(
            $projectSlug,
            $fullUrl,
            $cacheTTL,
            function () {
                return response()->json(['ok' => false]);
            }
        );

        $this->assertSame(
            'application/vnd.api+json; charset=utf-8',
            $firstResponse->headers->get('Content-Type')
        );
        $this->assertSame(
            'application/vnd.api+json; charset=utf-8',
            $secondResponse->headers->get('Content-Type')
        );
        $this->assertSame($firstResponse->getContent(), $secondResponse->getContent());
        $this->assertSame(200, Cache::get($cacheKey)['status']);
        $this->assertSame(
            'application/vnd.api+json; charset=utf-8',
            Cache::get($cacheKey)['headers']['Content-Type'][0]
        );
        $this->assertArrayNotHasKey('content_type', Cache::get($cacheKey));
    }
}
