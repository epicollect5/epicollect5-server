<?php

namespace Tests\Http\Controllers\Api\Entries\View\External\ExportRoutes;

use ec5\Models\Entries\Entry;
use ec5\Services\Entries\EntriesCacheService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Http\Controllers\Api\Entries\View\ViewEntriesBaseControllerTest;
use Throwable;

class EntriesExportCacheTest extends ViewEntriesBaseControllerTest
{
    use DatabaseTransactions;

    private string $endpoint = 'api/export/entries/';

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

    /**
     * @throws Throwable
     */
    public function test_entries_export_cache_hit_returns_same_response_for_same_url()
    {
        config()->set('cache.export_entries_cache_enabled', true);
        config()->set('cache.export_entries_cache_ttl', 123);

        $formRef = $this->makeProjectPublicAndCreateParentEntry();
        $url = $this->endpoint . $this->project->slug . '?form_ref=' . $formRef;

        $firstResponse = $this->actingAs($this->user)->get($url);
        $firstResponse->assertStatus(200);
        $firstResponse->assertHeader('X-Epicollect-Cache', 'miss');
        $firstResponse->assertHeader('X-Epicollect-Cache-Ttl', '123');
        $this->assertEntryCount($firstResponse->getContent(), 1);
        $this->assertCachePayload($url, $firstResponse->getContent());

        $this->createParentEntry($formRef);
        $this->assertCount(2, Entry::where('project_id', $this->project->id)->get());

        $secondResponse = $this->actingAs($this->user)->get($url);
        $secondResponse->assertStatus(200);
        $secondResponse->assertHeader('X-Epicollect-Cache', 'hit');
        $secondResponse->assertHeader('X-Epicollect-Cache-Ttl', '123');

        $this->assertSame($firstResponse->getContent(), $secondResponse->getContent());
        $this->assertEntryCount($secondResponse->getContent(), 1);
    }

    /**
     * @throws Throwable
     */
    public function test_entries_export_cache_disabled_returns_fresh_response()
    {
        config()->set('cache.export_entries_cache_enabled', false);
        config()->set('cache.export_entries_cache_ttl', 3600);

        $formRef = $this->makeProjectPublicAndCreateParentEntry();
        $url = $this->endpoint . $this->project->slug . '?form_ref=' . $formRef;

        $firstResponse = $this->actingAs($this->user)->get($url);
        $firstResponse->assertStatus(200);
        $this->assertEntryCount($firstResponse->getContent(), 1);

        $this->createParentEntry($formRef);

        $secondResponse = $this->actingAs($this->user)->get($url);
        $secondResponse->assertStatus(200);

        $this->assertNotSame($firstResponse->getContent(), $secondResponse->getContent());
        $this->assertEntryCount($secondResponse->getContent(), 2);
    }

    /**
     * @throws Throwable
     */
    public function test_entries_export_cache_ttl_zero_bypasses_cache()
    {
        config()->set('cache.export_entries_cache_enabled', true);
        config()->set('cache.export_entries_cache_ttl', 0);

        $formRef = $this->makeProjectPublicAndCreateParentEntry();
        $url = $this->endpoint . $this->project->slug . '?form_ref=' . $formRef;

        $firstResponse = $this->actingAs($this->user)->get($url);
        $firstResponse->assertStatus(200);
        $this->assertEntryCount($firstResponse->getContent(), 1);

        $this->createParentEntry($formRef);

        $secondResponse = $this->actingAs($this->user)->get($url);
        $secondResponse->assertStatus(200);

        $this->assertNotSame($firstResponse->getContent(), $secondResponse->getContent());
        $this->assertEntryCount($secondResponse->getContent(), 2);
    }

    /**
     * @throws Throwable
     */
    public function test_entries_export_cache_key_uses_full_query_string()
    {
        config()->set('cache.export_entries_cache_enabled', true);
        config()->set('cache.export_entries_cache_ttl', 3600);

        $formRef = $this->makeProjectPublicAndCreateParentEntry();
        $this->createParentEntry($formRef);

        $firstUrl = $this->endpoint . $this->project->slug
            . '?form_ref=' . $formRef
            . '&per_page=1&page=1';
        $secondUrl = $this->endpoint . $this->project->slug
            . '?form_ref=' . $formRef
            . '&per_page=1&page=2';

        $firstResponse = $this->actingAs($this->user)->get($firstUrl);
        $firstResponse->assertStatus(200);
        $this->assertEntryCount($firstResponse->getContent(), 1);

        $secondResponse = $this->actingAs($this->user)->get($secondUrl);
        $secondResponse->assertStatus(200);
        $this->assertEntryCount($secondResponse->getContent(), 1);

        $this->assertNotSame($firstResponse->getContent(), $secondResponse->getContent());
    }

    /**
     * @throws Throwable
     */
    private function makeProjectPublicAndCreateParentEntry(): string
    {
        $this->project->access = config('epicollect.strings.project_access.public');
        $this->project->save();

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $this->createParentEntry($formRef);

        return $formRef;
    }

    /**
     * @throws Throwable
     */
    private function createParentEntry(string $formRef): void
    {
        $entryPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $entryRowBundle = $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $entryPayload
        );

        $this->assertEntryRowAgainstPayload(
            $entryRowBundle,
            $entryPayload
        );
    }

    private function assertEntryCount(string $content, int $count): void
    {
        $json = json_decode($content, true);

        $this->assertCount($count, $json['data']['entries']);
    }

    private function assertCachePayload(string $url, string $expectedContent): void
    {
        $fullUrl = 'http://localhost/' . $url;
        $cacheKey = app(EntriesCacheService::class)->getExportEntriesCacheKey(
            $this->project->slug,
            $fullUrl
        );
        $cachedResponse = Cache::get($cacheKey);

        $this->assertIsArray($cachedResponse);
        $this->assertArrayNotHasKey('compressed', $cachedResponse);
        $this->assertSame($expectedContent, $cachedResponse['content']);
        $this->assertSame(200, $cachedResponse['status']);
    }
}
