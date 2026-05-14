<?php

namespace ec5\Services\Entries;

use Closure;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EntriesCacheService
{
    private const string CACHE_HEADER = 'X-Epicollect-Cache';
    private const string CACHE_TTL_HEADER = 'X-Epicollect-Cache-Ttl';
    private const int CACHEABLE_STATUS_CODE = 200;

    public function isExportEntriesCacheEnabled(): bool
    {
        return (bool) config('cache.export_entries_cache_enabled');
    }

    public function getExportEntriesCacheTTL(): int
    {
        return (int) config('cache.export_entries_cache_ttl');
    }

    public function rememberExportEntriesResponse(
        string $projectSlug,
        string $fullUrl,
        int $cacheTTL,
        Closure $callback
    ): Response {
        $cacheKey = $this->getExportEntriesCacheKey($projectSlug, $fullUrl);
        $cachedResponse = Cache::get($cacheKey);

        if (is_array($cachedResponse)) {
            $response = $this->getCachedExportEntriesResponse($cachedResponse);

            if ($response) {
                $this->setCacheHeaders($response, 'hit', $cacheTTL);

                return $response;
            }

            Cache::forget($cacheKey);
        }

        $response = $callback();
        $cachePayload = $this->getResponseCachePayload($response);

        if (!$cachePayload) {
            $this->setCacheHeaders($response, 'bypass', $cacheTTL);

            return $response;
        }

        Cache::put($cacheKey, $cachePayload, $cacheTTL);
        $this->setCacheHeaders($response, 'miss', $cacheTTL);

        return $response;
    }

    public function getExportEntriesCacheKey(string $projectSlug, string $fullUrl): string
    {
        return 'export_entries:' . hash('sha256', $projectSlug . '|' . $fullUrl);
    }

    private function getResponseCachePayload(Response $response): ?array
    {
        //if the response is not 200, do not cache
        if ($response->getStatusCode() !== self::CACHEABLE_STATUS_CODE) {
            return null;
        }

        $content = $response->getContent();
        if ($content === false) {
            return null;
        }

        //return the cache payload. Content-Type is needed to preserve the content type of the response
        //so that the response is sent with the correct content type
        // JSON: 'application/vnd.api+json; charset=utf-8
        // CSV: text/csv; charset=utf-8
        return [
            'content' => $content,
            'status' => self::CACHEABLE_STATUS_CODE,
            'content_type' => $response->headers->get('Content-Type'),
        ];
    }

    private function getCachedExportEntriesResponse(array $cachedResponse): ?Response
    {
        if (
            !isset(
                $cachedResponse['content'],
                $cachedResponse['status'],
                $cachedResponse['content_type']
            )
        ) {
            return null;
        }

        return response($cachedResponse['content'], $cachedResponse['status'])
            ->header('Content-Type', $cachedResponse['content_type']);
    }

    private function setCacheHeaders(Response $response, string $status, int $cacheTTL): void
    {
        $response->headers->set(self::CACHE_HEADER, $status);
        $response->headers->set(self::CACHE_TTL_HEADER, (string) $cacheTTL);
    }
}
