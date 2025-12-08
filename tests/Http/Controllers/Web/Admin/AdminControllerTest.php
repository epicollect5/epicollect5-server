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
        $response = $this->get(route('admin-users')); // Replace with the actual route or URL to your view
        $response->assertStatus(302);
        $response->assertRedirect(Route('login-admin'));
    }
}