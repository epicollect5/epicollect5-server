<?php

namespace Tests\Services\Home;

use Carbon\Carbon;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectFeatured;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\System\SystemStats;
use ec5\Services\Home\GenerateHomePageCacheService;
use ec5\Services\Project\ProjectLogoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GenerateHomePageCacheServiceTest extends TestCase
{
    use DatabaseTransactions;

    private string $cacheKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheKey = config('epicollect.setup.system.cache.homepage_cache_key');
        Cache::forget($this->cacheKey);
    }

    protected function tearDown(): void
    {
        Cache::forget($this->cacheKey);
        parent::tearDown();
    }

    public function test_generate_returns_true_and_populates_cache(): void
    {
        $this->createFeaturedProjects(8);
        $this->createSystemStats();

        $service = new GenerateHomePageCacheService(new ProjectLogoService());
        $result = $service->generate();

        $this->assertTrue($result);
        $this->assertTrue(Cache::has($this->cacheKey));

        $cached = Cache::get($this->cacheKey);
        $this->assertNotEmpty($cached);
        $this->assertStringContainsString('page-home-featured-projects-small', $cached);
    }

    public function test_generate_returns_true_with_no_featured_projects(): void
    {
        $this->createSystemStats();

        $service = new GenerateHomePageCacheService(new ProjectLogoService());
        $result = $service->generate();

        $this->assertTrue($result);
        $this->assertTrue(Cache::has($this->cacheKey));

        $cached = Cache::get($this->cacheKey);
        $this->assertNotEmpty($cached);
    }

    public function test_generate_returns_true_with_no_system_stats(): void
    {
        $this->createFeaturedProjects(4);

        $service = new GenerateHomePageCacheService(new ProjectLogoService());
        $result = $service->generate();

        $this->assertTrue($result);
        $this->assertTrue(Cache::has($this->cacheKey));

        $cached = Cache::get($this->cacheKey);
        $this->assertNotEmpty($cached);
    }

    public function test_generate_caps_featured_at_two_rows_when_more_than_eight(): void
    {
        $this->createFeaturedProjects(10);
        $this->createSystemStats();

        $service = new GenerateHomePageCacheService(new ProjectLogoService());
        $result = $service->generate();

        $this->assertTrue($result);

        $cached = Cache::get($this->cacheKey);
        $this->assertSame(
            2,
            substr_count($cached, 'class="row page-home-featured-projects-small"'),
            'Expected exactly 2 featured-project rows when more than 8 projects exist'
        );
        $this->assertSame(
            8,
            substr_count($cached, 'class="col-xs-12 col-sm-6 col-md-6 col-lg-3"'),
            'Expected exactly 8 featured-project cards (max 2 rows of 4)'
        );
    }

    public function test_generate_formats_stats_using_round_number(): void
    {
        $this->createFeaturedProjects(4);
        $this->createSystemStats(
            totalUsers: 1500,
            totalPublicProjects: 25,
            totalPrivateProjects: 30,
            totalPublicEntries: 2500,
            totalPrivateEntries: 3500,
            totalPublicBranchEntries: 100,
            totalPrivateBranchEntries: 200,
        );

        $service = new GenerateHomePageCacheService(new ProjectLogoService());
        $result = $service->generate();

        $this->assertTrue($result);

        $cached = Cache::get($this->cacheKey);
        $this->assertStringContainsString('2K', $cached);
        $this->assertStringContainsString('≈ 60', $cached);
        $this->assertStringContainsString('6K', $cached);
    }

    private function createFeaturedProjects(int $count): array
    {
        $projects = [];
        for ($i = 0; $i < $count; $i++) {
            $project = factory(Project::class)->create();
            factory(ProjectStructure::class)->create(['project_id' => $project->id]);
            factory(ProjectFeatured::class)->create(['project_id' => $project->id]);
            $projects[] = $project;
        }
        return $projects;
    }

    private function createSystemStats(
        int $totalUsers = 1000,
        int $totalPublicProjects = 20,
        int $totalPrivateProjects = 30,
        int $totalPublicEntries = 25000,
        int $totalPrivateEntries = 25000,
        int $totalPublicBranchEntries = 5000,
        int $totalPrivateBranchEntries = 5000,
    ): void {
        // Remove any pre-existing records so initDailyStats() picks our test data
        SystemStats::query()->delete();

        $stats = new SystemStats();
        $stats->user_stats = json_encode(['total' => $totalUsers]);
        $stats->project_stats = json_encode([
            'total' => [
                'public' => ['hidden' => 10, 'listed' => $totalPublicProjects - 10],
                'private' => ['hidden' => 15, 'listed' => $totalPrivateProjects - 15],
            ],
        ]);
        $stats->entries_stats = json_encode([
            'total' => [
                'public' => $totalPublicEntries,
                'private' => $totalPrivateEntries,
            ],
        ]);
        $stats->branch_entries_stats = json_encode([
            'total' => [
                'public' => $totalPublicBranchEntries,
                'private' => $totalPrivateBranchEntries,
            ],
        ]);
        $stats->created_at = Carbon::now();
        $stats->save();
    }
}
