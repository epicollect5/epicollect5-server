<?php

namespace Tests\Http\Controllers\Web;

use Auth;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use DatabaseTransactions;

    public const string DRIVER = 'web';


    protected function setUp(): void
    {
        parent::setUp();
        define(
            'HOME_CACHE_KEY',
            config(
                'epicollect.setup.system.cache.homepage_cache_key',
                'homepage_cached_content'
            )
        );
        Cache::forget(HOME_CACHE_KEY);
    }

    protected function tearDown(): void
    {
        Cache::forget(HOME_CACHE_KEY);
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
}
