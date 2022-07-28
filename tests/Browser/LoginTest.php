<?php

namespace Tests\Browser;

use ec5\Models\Users\User;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;

class LoginTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * A Dusk login example.
     *
     * @return void
     */
    // public function testPassedLogin()
    // {
    //     $user = factory(User::class)->create([
    //         'email' => 'taylor@laravel.com',
    //     ]);

    //     //Login succeeded
    //     $this->browse(function ($browser) use ($user) {
    //         $browser->visit('/login')
    //             ->type('email', $user->email)
    //             ->type('password', 'secret')
    //             ->press('Login')
    //             ->assertPathIs('/');
    //     });
    // }
    public function testAnotherGoogle()
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

        // $response = $this->get('/handle/google');
        $this->browse(function ($browser) {
            $browser->visit('handle/google')->assertPathIs('/');
        });
    }
}
