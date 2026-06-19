<?php

namespace Tests\Http\Controllers\Web\Admin;

use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_projects_page_renders_correctly_for_admin()
    {
        $user = User::where('email', config('epicollect.setup.super_admin_user.email'))->first();
        $response = $this->actingAs($user)->get(route('admin-projects')); // Replace with the actual route or URL to your view
        $response->assertStatus(200); // Ensure the response is successful
    }

    public function test_projects_page_forbidden_to_basic_user()
    {
        $user = factory(User::class)->create(['email' => config('testing.UNIT_TEST_RANDOM_EMAIL')]);
        $response = $this->actingAs($user)->get(route('admin-projects')); // Replace with the actual route or URL to your view
        $response->assertStatus(302);
        $response->assertRedirect(Route('home'));
    }

    public function test_projects_page_forbidden_to_public()
    {
        $response = $this->get(route('admin-projects')); // Replace with the actual route or URL to your view
        $response->assertStatus(302);
        $response->assertRedirect(Route('login-admin'));
    }

    public function test_stats_page_renders_correctly_for_admin()
    {
        $user = User::where('email', config('epicollect.setup.super_admin_user.email'))->first();
        $response = $this->actingAs($user)->get(route('admin-stats')); // Replace with the actual route or URL to your view
        $response->assertStatus(200); // Ensure the response is successful
    }

    public function test_stats_page_forbidden_to_basic_user()
    {
        $user = factory(User::class)->create(['email' => config('testing.UNIT_TEST_RANDOM_EMAIL')]);
        $response = $this->actingAs($user)->get(route('admin-stats')); // Replace with the actual route or URL to your view
        $response->assertStatus(302);
        $response->assertRedirect(Route('home'));
    }

    public function test_stats_page_forbidden_to_public()
    {
        $response = $this->get(route('admin-stats')); // Replace with the actual route or URL to your view
        $response->assertStatus(302);
        $response->assertRedirect(Route('login-admin'));
    }

    public function test_users_page_renders_correctly_for_admin()
    {
        $user = User::where('email', config('epicollect.setup.super_admin_user.email'))->first();
        $response = $this->actingAs($user)->get(route('admin-users')); // Replace with the actual route or URL to your view
        $response->assertStatus(200); // Ensure the response is successful
    }

    public function test_users_page_forbidden_to_basic_user()
    {
        $user = factory(User::class)->create(['email' => config('testing.UNIT_TEST_RANDOM_EMAIL')]);
        $response = $this->actingAs($user)->get(route('admin-users')); // Replace with the actual route or URL to your view
        $response->assertStatus(302);
        $response->assertRedirect(Route('home'));
    }

    public function test_users_page_forbidden_to_public()
    {
        $response = $this->get(route('admin-users'));
        $response->assertStatus(302);
        $response->assertRedirect(Route('login-admin'));
    }

    public function test_users_page_filters_by_server_role()
    {
        $admin = User::where('email', config('epicollect.setup.super_admin_user.email'))->first();

        $superadminRole = config('epicollect.strings.server_roles.superadmin');
        $basicRole = config('epicollect.strings.server_roles.basic');

        $needle = 'filter-needle-' . uniqid();
        factory(User::class)->create([
            'name' => $needle,
            'email' => $needle . '@example.com',
            'server_role' => $superadminRole,
        ]);
        factory(User::class)->create([
            'name' => $needle,
            'email' => $needle . '-other@example.com',
            'server_role' => $basicRole,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin-users') . '?server_role=' . $superadminRole);

        $response->assertStatus(200);
        $this->assertStringContainsString($needle . '@example.com', $response->getContent());
        $this->assertStringNotContainsString($needle . '-other@example.com', $response->getContent());
    }

    public function test_users_page_filters_by_state()
    {
        $admin = User::where('email', config('epicollect.setup.super_admin_user.email'))->first();

        $archivedState = config('epicollect.strings.user_state.archived');
        $activeState = config('epicollect.strings.user_state.active');

        $needle = 'state-needle-' . uniqid();
        factory(User::class)->create([
            'name' => $needle,
            'email' => $needle . '@example.com',
            'state' => $archivedState,
        ]);
        factory(User::class)->create([
            'name' => $needle,
            'email' => $needle . '-other@example.com',
            'state' => $activeState,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin-users') . '?state=' . $archivedState);

        $response->assertStatus(200);
        $this->assertStringContainsString($needle . '@example.com', $response->getContent());
        $this->assertStringNotContainsString($needle . '-other@example.com', $response->getContent());
    }

    public function test_users_page_pagination_returns_next_link_when_more_pages_exist()
    {
        $admin = User::where('email', config('epicollect.setup.super_admin_user.email'))->first();

        // Seed enough users to overflow the per-page limit (25)
        for ($i = 0; $i < 30; $i++) {
            factory(User::class)->create(['email' => 'paged-' . $i . '-' . uniqid() . '@example.com']);
        }

        $response = $this->actingAs($admin)->get(route('admin-users'));

        $response->assertStatus(200);
        $this->assertStringContainsString('rel="next"', $response->getContent());
    }

    public function test_users_ajax_returns_rendered_table_html()
    {
        $admin = User::where('email', config('epicollect.setup.super_admin_user.email'))->first();

        $needle = 'ajax-needle-' . uniqid();
        factory(User::class)->create([
            'name' => $needle,
            'email' => $needle . '@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin-users') . '?search=' . $needle);

        $response->assertStatus(200);
        $this->assertJson($response->getContent());
        $decoded = json_decode($response->getContent(), true);
        $this->assertIsString($decoded);
        $this->assertStringContainsString($needle . '@example.com', $decoded);
    }

    public function test_users_page_search_supports_multi_word_query()
    {
        $admin = User::where('email', config('epicollect.setup.super_admin_user.email'))->first();

        // Seed a user whose first and last name are separate words and would
        // never match a naive prefix search on `name`.
        $first = 'David' . uniqid();
        $last = 'Aanensen' . uniqid();
        factory(User::class)->create([
            'name' => $first,
            'last_name' => $last,
            'email' => $first . '@example.com',
        ]);
        // A second user sharing only the first name, to make sure the search
        // narrows down to the right one when both tokens are present.
        factory(User::class)->create([
            'name' => $first,
            'last_name' => 'SomeoneElse' . uniqid(),
            'email' => $first . '-other@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin-users') . '?search=' . urlencode($first . ' ' . $last));

        $response->assertStatus(200);
        $this->assertStringContainsString($first . '@example.com', $response->getContent());
        $this->assertStringNotContainsString($first . '-other@example.com', $response->getContent());
    }

    public function test_users_page_search_matches_last_name_only()
    {
        $admin = User::where('email', config('epicollect.setup.super_admin_user.email'))->first();

        $last = 'Aanensen' . uniqid();
        factory(User::class)->create([
            'name' => 'David' . uniqid(),
            'last_name' => $last,
            'email' => 'someone' . uniqid() . '@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin-users') . '?search=' . $last);

        $response->assertStatus(200);
        $this->assertStringContainsString($last, $response->getContent());
    }
}
