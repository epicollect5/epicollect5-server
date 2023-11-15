<?php

namespace Tests\Routes\Web;

use ec5\Models\Eloquent\UserProvider;
use ec5\Models\Eloquent\User;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

//use Laravel\Socialite\Contracts\Factory as Socialite;

class UserAuthTest extends TestCase
{
    /**
     * Mock the Socialite Factory, so we can hijack the OAuth Request.
     * @param string $email
     * @param string $token
     * @param int $id
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

        $this->assertContains('https://accounts.google.com/o/oauth2/', $response->getTargetUrl());
    }

    /** @test */
    // public function testAuthGoogleCallback()
    // {
    //     // Mock the Facade and return a User Object with the email 'foo@bar.com'
    //     $this->mockSocialiteFacade('foo@bar.com');

    //     $response = $this->get('handle/google');
    //     $response->assertStatus(302);

    //     $this->seeInDatabase('users', [
    //         'email' => 'foo@bar.com',
    //     ]);
    // }

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

        //Socialite::shouldReceive('driver->user')->andReturn($abstractUser);

        $this->get('handle/google')
            ->assertStatus(302);
        // ->assertRedirect(route('home'));
    }

    public function testAnother()
    {
        $mockSocialite = \Mockery::mock('Laravel\Socialite\Contracts\Factory');
        $this->app['Laravel\Socialite\Contracts\Factory'] = $mockSocialite;

        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser
            ->shouldReceive('getId')
            ->andReturn(rand())
            ->shouldReceive('getName')
            ->andReturn(str_random(10))
            ->shouldReceive('getEmail')
            ->andReturn('test' . '@ncsu.edu')
            ->shouldReceive('getAvatar')
            ->andReturn('https://en.gravatar.com/userimage');

        $provider = Mockery::mock('Laravel\Socialite\Contract\Provider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        $mockSocialite->shouldReceive('driver')->andReturn($provider);

        $response = $this->get('/handle/google');


        // $this->get('handle/google')
        //     ->assertStatus(302);
        // // ->assertRedirect(route('home'));
        // dd($response);
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
            'provider' => config('ec5Strings.providers.local')
        ]);

        //  $this->actingAs($user);


        $response = $this->get('/login');
        $response->assertStatus(200);
    }
}
