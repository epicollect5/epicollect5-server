<?php

namespace Tests\Routes\Api\internal;


use Carbon\Carbon;
use ec5\Libraries\Utilities\Generators;
use ec5\Mail\UserPasswordlessApiMail;
use ec5\Models\Eloquent\UserPasswordlessWeb;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordlessInternalTest extends TestCase
{
    //to reset database after tests
    use DatabaseTransactions;

    /**
     * Test an authenticated user's routes
     * imp: avoid $this->actingAs($user, 'api_external');
     * imp: as that create a valid user object therefore bypassing
     * imp: jwt validation. We need to send a valid token per each request
     * imp: instead.
     */

    protected $privateProjectSlug;
    protected $publicProjectSlug;

    public function setup()
    {
        parent::setUp();
        $this->privateProjectSlug = 'ec5-private';
        $this->publicProjectSlug = 'ec5-public';
    }

    public function testSendCode()
    {
        $email = env('MANAGER_EMAIL');

        //send a code to user for authentication
        Mail::fake();

        $response = $this->post('/login/passwordless/token', [
            'email' => $email
        ]);

        $response->assertStatus(200);
        //imp: POST request so not possible to use built in assertions
        $this->assertEquals('auth.verification_passwordless', $response->original->getName());
        $this->assertEquals([
            'email' => $email
        ], $response->original->getData());

        // Assert a message was sent to the given users...
        Mail::assertSent(UserPasswordlessApiMail::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
    }

    public function testLogin()
    {
        $email = env('MANAGER_EMAIL');
        $tokenExpiresAt = env('PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => env('BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => $code
        ], []);

        //should redirect to intended url
        $response->assertStatus(302);

        //user should be logged in
        $this->assertTrue(Auth::check());
        $this->assertEquals(Auth::user()->email, $email);
    }

    public function testFailedLogin()
    {
        $email = env('MANAGER_EMAIL');
        $tokenExpiresAt = env('PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        //add token to db
        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => env('BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        $response = $this->post('/login/passwordless/verification', [
            'email' => 'not-an-email',
            'code' => $code
        ], []);

        $response->assertStatus(302); //redirect to login page
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_42', session('errors')->getBag('default')->first());

        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            //'code' => $code
        ], []);

        $response->assertStatus(302); //redirect to login page
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_21', session('errors')->getBag('default')->first());

        $response = $this->post('/login/passwordless/verification', [
            //'email' => $email,
            'code' => $code
        ], [])
            ->assertStatus(302);

        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_21', session('errors')->getBag('default')->first());

        $response = $this->post('/login/passwordless/verification', [
            //  'email' => $email,
            //'code' => $code
        ], [])->assertStatus(302);

        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_21', session('errors')->getBag('default')->first());

        // First invalid attempt
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => strval(Generators::randomNumber(6, 1))
        ], [])->assertStatus(200);
        $this->assertEquals('auth.verification_passwordless', $response->original->getName());
        $this->assertEquals(['ec5_378'], $response->original->getData()['errors']->all());

        //Second invalid attempt
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => strval(Generators::randomNumber(6, 1))
        ], [])->assertStatus(200);
        $this->assertEquals('auth.verification_passwordless', $response->original->getName());
        $this->assertEquals(['ec5_378'], $response->original->getData()['errors']->all());

        //Third invalid attempt, redirect back to login
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => strval(Generators::randomNumber(6, 1))
        ], [])->assertStatus(302);

        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_378', session('errors')->getBag('default')->first());
    }

    public function testRedirectAfterLogin()
    {
        $email = env('MANAGER_EMAIL');
        $tokenExpiresAt = env('PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => env('BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        session()->put('url.intended', route('profile'));
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => $code
        ], []);

        //should redirect to intended url
        $response->assertStatus(302);
        $response->assertRedirect(route('profile'));

        //user should be logged in
        $this->assertTrue(Auth::check());
        $this->assertEquals(Auth::user()->email, $email);

        Auth::logout();
        session()->flush();
        session()->regenerate();
        $code = Generators::randomNumber(6, 1);
        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => env('BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        session()->put('url.intended', route('my-projects'));
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => $code
        ], []);

        //should redirect to intended url
        $response->assertStatus(302);
        $response->assertRedirect(route('my-projects'));

        //user should be logged in
        $this->assertTrue(Auth::check());
        $this->assertEquals(Auth::user()->email, $email);
    }

    public function testRedirectAfterLoginErrors()
    {
        $email = env('MANAGER_EMAIL');
        $tokenExpiresAt = env('PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        //add token to db
        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => env('BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        // First invalid attempt
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => strval(Generators::randomNumber(6, 1))
        ], [])->assertStatus(200);
        $this->assertEquals('auth.verification_passwordless', $response->original->getName());
        $this->assertEquals(['ec5_378'], $response->original->getData()['errors']->all());

        //Second invalid attempt
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => strval(Generators::randomNumber(6, 1))
        ], [])->assertStatus(200);
        $this->assertEquals('auth.verification_passwordless', $response->original->getName());
        $this->assertEquals(['ec5_378'], $response->original->getData()['errors']->all());

        //Third invalid attempt, redirect back to login
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => strval(Generators::randomNumber(6, 1))
        ], [])->assertStatus(302);

        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_378', session('errors')->getBag('default')->first());

        //Now do login succesfully
        $code = Generators::randomNumber(6, 1);
        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => env('BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => $code
        ], []);

        //should redirect to home page, not login/passwordless/verification
        $response->assertStatus(302);
        $response->assertRedirect(route('home'));

        //user should be logged in
        $this->assertTrue(Auth::check());
        $this->assertEquals(Auth::user()->email, $email);
    }

    public function testRedirectAfterRequestingAnotherCode()
    {
        $email = env('MANAGER_EMAIL');
        $tokenExpiresAt = env('PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        //add token to db
        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => env('BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        // First invalid attempt
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => strval(Generators::randomNumber(6, 1))
        ], [])->assertStatus(200);
        $this->assertEquals('auth.verification_passwordless', $response->original->getName());
        $this->assertEquals(['ec5_378'], $response->original->getData()['errors']->all());

        //go back to login page
        $this->get('/login');
        $code = Generators::randomNumber(6, 1);
        UserPasswordlessWeb::where('email', $email)->delete();

        //add token to db
        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => env('BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        //Now do login succesfully
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => $code
        ], []);

        //should redirect to home page, not login/passwordless/token
        $response->assertStatus(302);
        $response->assertRedirect(route('home'));

        //user should be logged in
        $this->assertTrue(Auth::check());
        $this->assertEquals(Auth::user()->email, $email);
    }
}
