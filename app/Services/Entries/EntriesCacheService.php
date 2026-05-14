<?php

namespace ec5\Services\Entries;

use Closure;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EntriesCacheService
{
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
                return $response;
            }

            Cache::forget($cacheKey);
        }

        $response = $callback();
        Cache::put($cacheKey, $this->getCompressedResponseCachePayload($response), $cacheTTL);

        return $response;
    }

    public function getExportEntriesCacheKey(string $projectSlug, string $fullUrl): string
    {
        return 'export_entries:' . hash('sha256', $projectSlug . '|' . $fullUrl);
    }

    private function getCompressedResponseCachePayload(Response $response): array
    {
        return [
            'compressed' => true,
            'content' => gzencode($response->getContent(), 3),
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
        ];
    }

    private function getCachedExportEntriesResponse(array $cachedResponse): ?Response
    {
        if (
            !isset(
                $cachedResponse['compressed'],
                $cachedResponse['content'],
                $cachedResponse['status'],
                $cachedResponse['headers']
            ) ||
            $cachedResponse['compressed'] !== true
        ) {
            return null;
        }

        $content = gzdecode($cachedResponse['content']);

        if ($content === false) {
            return null;
        }

        $response = response($content, $cachedResponse['status']);

        foreach ($cachedResponse['headers'] as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
