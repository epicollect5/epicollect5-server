<?php

namespace Tests\Http\Controllers\Web\Auth;

use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use DatabaseTransactions;

    public const string DRIVER = 'web';

    public function test_profile_page_renders_correctly()
    {
        //create mock user
        $user = factory(User::class)->create();
        $response = $this
            ->actingAs($user, self::DRIVER)
            ->get(route('profile'));
        $response->assertStatus(200);
    }

    public function test_profile_page_redirect_when_not_logged_in()
    {
        $response = $this
            ->get(route('profile'));
        $response->assertStatus(302);
        $response->assertRedirect(Route('login'));
    }

    public function test_connect_google()
    {
        //create mock user
        $user = factory(User::class)->create();
        $response = $this
            ->actingAs($user, self::DRIVER)
            ->get(route('profile-connect-google'));
        $response->assertStatus(302);

        // Get the redirected URL from the response without the query string
        $redirectUrl = strtok($response->headers->get('Location'), '?');

        // Define the expected URL pattern without the query string
        $expectedUrlPattern = 'https://accounts.google.com/o/oauth2/auth';

        // Assert that the redirected URL matches the expected pattern without the query string
        $this->assertSame($expectedUrlPattern, $redirectUrl);
    }

    public function test_disconnect_google()
    {
        //create mock user
        $user = factory(User::class)->create();

        //add google provider
        factory(UserProvider::class)->create(
            [
                'user_id' => $user->id,
                'email' => $user->email,
                'provider' => config('epicollect.strings.providers.google')
            ]
        );

        $this->assertDatabaseHas('users_providers', [
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => config('epicollect.strings.providers.google')
        ]);

        $response = $this->actingAs($user)->post(Route('profile-disconnect-google'))->assertStatus(302);
        $response->assertRedirect(Route('profile'));
        $response->assertSessionHas('message', 'ec5_385');

        $this->assertDatabaseMissing('users_providers', [
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => config('epicollect.strings.providers.google')
        ]);


    }

    public function test_disconnect_apple()
    {
        //create mock user
        $user = factory(User::class)->create();

        //add google provider
        factory(UserProvider::class)->create(
            [
                'user_id' => $user->id,
                'email' => $user->email,
                'provider' => config('epicollect.strings.providers.apple')
            ]
        );

        $this->assertDatabaseHas('users_providers', [
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => config('epicollect.strings.providers.apple')
        ]);

        $response = $this->actingAs($user)->post(Route('profile-disconnect-apple'))->assertStatus(302);
        $response->assertRedirect(Route('profile'));
        $response->assertSessionHas('message', 'ec5_385');

        $this->assertDatabaseMissing('users_providers', [
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => config('epicollect.strings.providers.apple')
        ]);


    }
}
