<?php

namespace Tests\Routes\Web;

use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class UserAuthTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Mock the Socialite Factory, so we can hijack the OAuth Request.
     * @return void
     */
    // public function mockSocialiteFacade($email = 'foo@bar.com', $token = 'abc', $id = 1)
    // {
    //     $socialiteUser = $this->getMock(Laravel\Socialite\Two\User::class);
    //     $socialiteUser->token = $token;
    //     $socialiteUser->id = $id;
    //     $socialiteUser->email = $email;

    //     $provider = $this->getMock(Laravel\Socialite\Two\GoogleProvider::class);
    //     $provider->expects($this->any())
    //         ->method('user')
    //         ->willReturn($socialiteUser);

    //     $stub = $this->getMock(Socialite::class);
    //     $stub->expects($this->any())
    //         ->method('with')
    //         ->willReturn($provider);

    //     // Replace Socialite Instance with our mock
    //     $this->app->instance(Socialite::class, $stub);
    // }
    public function testAuthGoogleRedirect()
    {
        //for named routes
        //$response = $this->get(route('users.index'));
        $response = $this->get('redirect/google');

        $this->assertStringContainsString('https://accounts.google.com/o/oauth2/', $response->getTargetUrl());
    }


    public function testSocialiteGoogleLogin()
    {
        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');

        $abstractUser
            ->shouldReceive('getId')
            ->andReturn(rand())
            ->shouldReceive('getNickName')
            ->andReturn(uniqid())
            ->shouldReceive('getName')
            ->andReturn(uniqid())
            ->shouldReceive('getEmail')
            ->andReturn(uniqid() . '@gmail.com')
            ->shouldReceive('getAvatar')
            ->andReturn('https://en.gravatar.com/userimage');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')
            ->andReturn($abstractUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($provider);

        $this->get('handle/google')
            ->assertStatus(302);
    }

    /**
     * Test an authenticated user's routes
     */
    public function testAuthLocalUser()
    {

        $user = factory(User::class)->create();
        $userProvider = factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => config('epicollect.strings.providers.local')
        ]);

        //  $this->actingAs($user);


        $response = $this->get('/login');
        $response->assertStatus(200);
    }
}
