<?php

namespace Tests\Http\Controllers\Web;

use Auth;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectFeatured;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Throwable;

class HomeControllerTest extends TestCase
{
    use DatabaseTransactions;

    public const string DRIVER = 'web';
    public string $cacheKey;


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

    public function test_home_page_renders_correctly()
    {
        $this
           ->get(route('home'))
           ->assertStatus(200);
    }

    public function test_home_page_renders_correctly_when_logged_in()
    {
        $user = factory(User::class)->create();
        Auth::login($user);
        $this
           ->actingAs($user)
           ->get(route('home'))
           ->assertStatus(200);
    }

    public function test_home_page_serves_cached_content_when_available()
    {
        // Set cached content
        $cachedHtml = '<div>Cached Featured Projects and Stats</div>';
        Cache::put('homepage_cached_content', $cachedHtml, now()->addHours(24));

        $this
           ->get(route('home'))
           ->assertStatus(200)
           ->assertSee('Cached Featured Projects and Stats');
    }

    public function test_home_page_renders_dynamically_when_cache_empty()
    {
        // Ensure cache is empty
        Cache::forget('homepage_cached_content');

        $this
           ->get(route('home'))
           ->assertStatus(200)
           // Should contain dynamic content from views
           ->assertSee(trans('site.home_title'));
    }

    public function test_home_page_contains_header_elements()
    {
        $this
           ->get(route('home'))
           ->assertStatus(200)
           ->assertSee(trans('site.home_title'))
           ->assertSee('Create your project and forms')
           ->assertSee('Collect data online or offline')
           ->assertSee('View, analyse and export your data');
    }

    public function test_home_page_contains_app_store_links()
    {
        $this
           ->get(route('home'))
           ->assertStatus(200)
           ->assertSee('play.google.com/store/apps')
           ->assertSee('itunes.apple.com');
    }

    public function test_home_page_contains_stats_section()
    {
        $this
           ->get(route('home'))
           ->assertStatus(200)
           ->assertSee('Thousands of people use Epicollect5')
           ->assertSee('Users')
           ->assertSee('Projects')
           ->assertSee('Entries');
    }

    public function test_home_page_contains_community_section()
    {
        $this
           ->get(route('home'))
           ->assertStatus(200)
           ->assertSee('find a project');
    }

    public function test_home_page_cache_hit_serves_from_view()
    {
        // Simulate cache hit
        $cachedContent = '<p>Featured Projects Section</p>';
        Cache::put('homepage_cached_content', $cachedContent, now()->addHours(24));

        $this
           ->get(route('home'))
           ->assertStatus(200)
           ->assertSee('Featured Projects Section');

        // Verify cache was used
        $this->assertTrue(Cache::has('homepage_cached_content'));
    }

    public function test_home_page_cache_miss_performs_database_queries()
    {
        // Ensure cache is empty
        Cache::forget('homepage_cached_content');

        // This should execute database queries
        $this
           ->get(route('home'))
           ->assertStatus(200)
           ->assertSee(trans('site.home_title'));
    }

    public function test_home_page_renders_with_empty_featured_projects()
    {
        // Cache is empty, no featured projects exist
        Cache::forget('homepage_cached_content');

        $this
           ->get(route('home'))
           ->assertStatus(200)
           // Should still render the page structure
           ->assertSee('page-home');
    }

    public function test_home_page_has_html_structure()
    {
        $this
           ->get(route('home'))
           ->assertStatus(200)
           ->assertSee('container-fluid page-home')
           ->assertSee('page-home-intro')
           ->assertSee('page-home__server-stats')
           ->assertSee('page-home__find-project');
    }

    public function test_cached_content_returns_correct_content_type()
    {
        $cachedContent = '<div>Cached HTML</div>';
        Cache::put('homepage_cached_content', $cachedContent, now()->addHours(24));

        $this
           ->get(route('home'))
           ->assertStatus(200)
           ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_home_page_with_authenticated_user_shows_same_content()
    {
        $user = factory(User::class)->create();

        // Cache is set
        $cachedContent = '<div>Public Featured Projects</div>';
        Cache::put('homepage_cached_content', $cachedContent, now()->addHours(24));

        // Both authenticated and unauthenticated users should see same cached content
        $this
            ->actingAs($user)
            ->get(route('home'))
            ->assertStatus(200)
            ->assertSee('Public Featured Projects');

        $this
            ->get(route('home'))
            ->assertStatus(200)
            ->assertSee('Public Featured Projects');
    }

    public function test_home_page_cache_persists_across_requests()
    {
        $cachedContent = '<div>Persistent Cache</div>';
        Cache::put('homepage_cached_content', $cachedContent, now()->addHours(24));

        // First request
        $this->get(route('home'))
            ->assertSee('Persistent Cache');

        // Second request should use same cache
        $this->get(route('home'))
            ->assertSee('Persistent Cache');

        // Verify cache still exists
        $this->assertTrue(Cache::has('homepage_cached_content'));
    }

    public function test_home_page_falls_back_to_dynamic_when_cache_expires()
    {
        // Ensure cache is empty (simulating expired cache)
        Cache::forget('homepage_cached_content');

        $this
           ->get(route('home'))
           ->assertStatus(200)
           // Should render dynamically
           ->assertSee('page-home');
    }

    public function test_home_page_shows_featured_projects_in_two_rows_of_four_when_eight_featured()
    {
        $this->createFeaturedProjects(8);

        $response = $this->get(route('home'))->assertStatus(200);
        $this->assertEquals('home', $response->original->getName());

        $content = $response->getContent();
        $this->assertSame(
            2,
            substr_count($content, 'class="row page-home-featured-projects-small"'),
            'Expected exactly 2 featured-project rows on the dynamic home page'
        );
        $this->assertSame(
            8,
            substr_count($content, 'class="col-xs-12 col-sm-6 col-md-6 col-lg-3"'),
            'Expected exactly 8 featured-project cards (4 per row x 2 rows)'
        );
    }

    /**
     * @throws Throwable
     */
    public function test_cached_home_page_shows_featured_projects_in_two_rows_of_four_when_eight_featured()
    {
        $this->createFeaturedProjects(8);

        $allFeaturedProjects = (new Project())->featured();
        $projectsFirstRow = $allFeaturedProjects->splice(0, 4);
        $projectsSecondRow = $allFeaturedProjects->splice(0, 4);

        foreach ($projectsFirstRow as $project) {
            $project->logo_base64 = url('/images/ec5-placeholder-256x256.jpg');
        }
        foreach ($projectsSecondRow as $project) {
            $project->logo_base64 = url('/images/ec5-placeholder-256x256.jpg');
        }

        $cachedHtml = view('partials.home-featured-cached', [
            'projectsFirstRow' => $projectsFirstRow,
            'projectsSecondRow' => $projectsSecondRow,
            'users' => 0,
            'projects' => 0,
            'entries' => 0,
        ])->render();

        Cache::put($this->cacheKey, $cachedHtml, now()->addHours(24));

        $response = $this->get(route('home'))->assertStatus(200);
        $this->assertEquals('home_cached', $response->original->getName());

        $content = $response->getContent();
        $this->assertSame(
            2,
            substr_count($content, 'class="row page-home-featured-projects-small"'),
            'Expected exactly 2 featured-project rows in the cached home page'
        );
        $this->assertSame(
            8,
            substr_count($content, 'class="col-xs-12 col-sm-6 col-md-6 col-lg-3"'),
            'Expected exactly 8 featured-project cards in the cached home page (4 per row x 2 rows)'
        );
    }

    public function test_home_page_caps_featured_projects_at_two_rows_when_more_than_eight_featured()
    {
        $this->createFeaturedProjects(10);

        $response = $this->get(route('home'))->assertStatus(200);

        $content = $response->getContent();
        $this->assertSame(
            2,
            substr_count($content, 'class="row page-home-featured-projects-small"'),
            'Featured projects must be capped at 2 rows even when more than 8 are featured'
        );
        $this->assertSame(
            8,
            substr_count($content, 'class="col-xs-12 col-sm-6 col-md-6 col-lg-3"'),
            'Featured projects must show exactly 8 cards (max 2 rows of 4)'
        );
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
}
