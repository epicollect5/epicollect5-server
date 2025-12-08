<?php

namespace Tests\Http\Controllers\Web\Auth;

use ec5\Models\Project\Project;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_page_renders_correctly()
    {
        $response = $this->get(route('login')); // Replace with the actual route or URL to your view
        $response->assertStatus(200); // Ensure the response is successful
    }

    public function test_logout()
    {
        $user = factory(User::class)->create();
        $response = $this->actingAs($user)->get(route('logout')); // Replace with the actual route or URL to your view
        $response->assertRedirect(Route('home')); // Ensure the response is successful

        // Assert that the user is logged out after logout action
        $this->assertFalse(auth()->check());
    }

    public function test_logout_when_not_logged_in()
    {
        $response = $this->get(route('logout')); // Replace with the actual route or URL to your view
        $response->assertRedirect(Route('login')); // Ensure the response is successful

        // Assert that the user is logged out after logout action
        $this->assertFalse(auth()->check());
    }

    public function test_logout_redirection_from_dataviewer()
    {
        $user = factory(User::class)->create();
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'access' => config('epicollect.strings.project_access.private')
        ]);

        //check redirection from dataviewer
        $startingUrl = '/project/' . $project->slug . '/data'; // Set the starting URL

        // Visit the dataviewer URL and perform an action (e.g., logging out)
        $this->actingAs($user)->get($startingUrl);

        $response = $this->actingAs($user)->get(route('logout'));

        $response->assertRedirect(Route('dataviewer', ['project_slug' => $project->slug]));

        // Assert that the user is logged out after logout action
        $this->assertFalse(auth()->check());
    }

    public function test_logout_redirection_from_pwa()
    {
        $user = factory(User::class)->create();
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'access' => config('epicollect.strings.project_access.private')
        ]);

        //check redirection from dataviewer
        $startingUrl = '/project/' . $project->slug . '/data'; // Set the starting URL

        // Visit the dataviewer URL and perform an action (e.g., logging out)
        $this->actingAs($user)->get($startingUrl);

        $response = $this->actingAs($user)->get(route('logout'));

        $response->assertRedirect(Route('dataviewer', ['project_slug' => $project->slug]));

        // Assert that the user is logged out after logout action
        $this->assertFalse(auth()->check());
    }
}
