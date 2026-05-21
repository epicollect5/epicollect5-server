<?php

namespace Tests\Http\Controllers\Web\Auth;

use Carbon\Carbon;
use ec5\Libraries\Utilities\Generators;
use ec5\Mail\UserPasswordlessApiMail;
use ec5\Models\User\UserPasswordlessWeb;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordlessControllerWebTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test an authenticated user's routes
     * imp: avoid $this->actingAs($user, 'api_external');
     * imp: as that create a valid user object therefore bypassing
     * imp: jwt validation. We need to send a valid token per each request
     * imp: instead.
     */
    public function setup(): void
    {
        parent::setUp();
    }

    public function test_send_code()
    {
        $email = config('testing.MANAGER_EMAIL');

        //send a code to user for authentication
        Mail::fake();

        $response = $this->post(Route('passwordless-token-web'), [
            'email' => $email,
            'cf-turnstile-response' => 'abc'
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

    public function test_missing_captcha()
    {
        $email = config('testing.MANAGER_EMAIL');

        //send a code to user for authentication
        Mail::fake();

        // Mock a previous request, we are posting from /login
        $referer = '/login';
        $this->serverVariables['HTTP_REFERER'] = $referer;

        $response = $this->post(Route('passwordless-token-web'), [
            'email' => $email
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_21', session('errors')->getBag('default')->first());
    }

    public function test_missing_email()
    {
        //send a code to user for authentication
        Mail::fake();

        // Mock a previous request, we are posting from /login
        $referer = '/login';
        $this->serverVariables['HTTP_REFERER'] = $referer;

        $response = $this->post(Route('passwordless-token-web'), [
            'cf-turnstile-response' => 'abc'
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_21', session('errors')->getBag('default')->first());
    }

    public function test_login()
    {
        config()->set('auth.auth_allowed_domains', []);
        $email = config('testing.MANAGER_EMAIL');
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
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

    public function test_login_disallowed_domain()
    {
        config()->set('auth.auth_allowed_domains', ['example.com']);
        $email = config('testing.MANAGER_EMAIL');
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => $code
        ], []);

        //user should not be logged in
        $this->assertFalse(Auth::check());

        $response->assertStatus(302); //redirect to login page
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_266', session('errors')->getBag('default')->first());
    }

    public function test_failed_login()
    {
        $email = config('testing.MANAGER_EMAIL');
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        //add token to db
        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
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
            'code' => Generators::randomNumber(6, 1)
        ], [])->assertStatus(200);
        $this->assertEquals('auth.verification_passwordless', $response->original->getName());
        $this->assertEquals(['ec5_378'], $response->original->getData()['errors']->all());

        //Second invalid attempt
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => Generators::randomNumber(6, 1)
        ], []);

        $response->assertStatus(200);
        $this->assertEquals('auth.verification_passwordless', $response->original->getName());
        $this->assertEquals(['ec5_378'], $response->original->getData()['errors']->all());

        //Third invalid attempt, redirect back to login
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => Generators::randomNumber(6, 1)
        ], [])->assertStatus(302);

        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_378', session('errors')->getBag('default')->first());
    }

    public function test_redirect_after_login()
    {
        $email = config('testing.MANAGER_EMAIL');
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
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
                'token' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
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

    public function test_redirect_after_login_errors()
    {
        $email = config('testing.MANAGER_EMAIL');
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        //add token to db
        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        // First invalid attempt
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => Generators::randomNumber(6, 1)
        ], [])->assertStatus(200);
        $this->assertEquals('auth.verification_passwordless', $response->original->getName());
        $this->assertEquals(['ec5_378'], $response->original->getData()['errors']->all());

        //Second invalid attempt
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => Generators::randomNumber(6, 1)
        ], [])->assertStatus(200);
        $this->assertEquals('auth.verification_passwordless', $response->original->getName());
        $this->assertEquals(['ec5_378'], $response->original->getData()['errors']->all());

        //Third invalid attempt, redirect back to login
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => Generators::randomNumber(6, 1)
        ], [])->assertStatus(302);

        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_378', session('errors')->getBag('default')->first());

        //Now do login successfully
        $code = Generators::randomNumber(6, 1);
        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
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

    public function test_redirect_after_requesting_another_code()
    {
        $email = config('testing.MANAGER_EMAIL');
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        //add token to db
        factory(UserPasswordlessWeb::class)
            ->create([
                'email' => $email,
                'token' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        // First invalid attempt
        $response = $this->post('/login/passwordless/verification', [
            'email' => $email,
            'code' => Generators::randomNumber(6, 1)
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
                'token' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        //Now do login successfully
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

    /**
     * Test sending passwordless code with successful Turnstile verification (enabled).
     * This test is only run if Turnstile is enabled in the environment.
     */
    public function test_send_code_with_turnstile_enabled_success()
    {
        // Enable Turnstile for this test
        config()->set('epicollect.setup.cloudflare_turnstile.use_cloudflare_turnstile', true);

        $email = config('testing.MANAGER_EMAIL');
        Mail::fake();

        // Mock a successful Turnstile verification response
        Http::fake([
            config('epicollect.setup.cloudflare_turnstile.verify_endpoint') => Http::response([
                'success' => true,
                'challenge_ts' => '2021-01-01T00:00:00Z',
                'hostname' => 'example.com',
                'error-codes' => []
            ], 200)
        ]);

        $response = $this->post(Route('passwordless-token-web'), [
            'email' => $email,
            'cf-turnstile-response' => 'valid-turnstile-token'
        ]);

        $response->assertStatus(200);
        $this->assertEquals('auth.verification_passwordless', $response->original->getName());
        $this->assertEquals([
            'email' => $email
        ], $response->original->getData());

        Mail::assertSent(UserPasswordlessApiMail::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });

        // Verify Http request was made to Cloudflare
        Http::assertSent(function ($request) {
            return $request['response'] === 'valid-turnstile-token';
        });
    }

    /**
     * Test sending passwordless code with failed Turnstile verification (success = false).
     */
    public function test_send_code_with_turnstile_enabled_failure_success_false()
    {
        config()->set('epicollect.setup.cloudflare_turnstile.use_cloudflare_turnstile', true);

        $email = config('testing.MANAGER_EMAIL');
        Mail::fake();

        // Mock a failed Turnstile verification response
        Http::fake([
            config('epicollect.setup.cloudflare_turnstile.verify_endpoint') => Http::response([
                'success' => false,
                'challenge_ts' => '2021-01-01T00:00:00Z',
                'hostname' => 'example.com',
                'error-codes' => ['challenge-failed']
            ], 200)
        ]);

        $response = $this->post(Route('passwordless-token-web'), [
            'email' => $email,
            'cf-turnstile-response' => 'invalid-turnstile-token'
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_380', session('errors')->getBag('default')->first());

        Mail::assertNotSent(UserPasswordlessApiMail::class);
    }

    /**
     * Test sending passwordless code with Turnstile enabled but missing turnstile response.
     */
    public function test_send_code_with_turnstile_enabled_missing_response()
    {
        config()->set('epicollect.setup.cloudflare_turnstile.use_cloudflare_turnstile', true);

        $email = config('testing.MANAGER_EMAIL');
        Mail::fake();

        $response = $this->post(Route('passwordless-token-web'), [
            'email' => $email
            // Missing cf-turnstile-response
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_103', session('errors')->getBag('default')->first());

        Mail::assertNotSent(UserPasswordlessApiMail::class);
    }

    /**
     * Test sending passwordless code with Turnstile disabled.
     * When Turnstile is disabled, no HTTP request to Cloudflare should be made.
     */
    public function test_send_code_with_turnstile_disabled()
    {
        config()->set('epicollect.setup.cloudflare_turnstile.use_cloudflare_turnstile', false);

        $email = config('testing.MANAGER_EMAIL');
        Mail::fake();

        // Don't mock HTTP responses since no HTTP requests should be made
        Http::fake();

        $response = $this->post(Route('passwordless-token-web'), [
            'email' => $email,
            'cf-turnstile-response' => 'any-token'
        ]);

        $response->assertStatus(200);
        $this->assertEquals('auth.verification_passwordless', $response->original->getName());

        Mail::assertSent(UserPasswordlessApiMail::class);

        // Verify no HTTP requests were made to Cloudflare
        Http::assertNothingSent();
    }

    /**
     * Test sending passwordless code with Turnstile verification returning invalid JSON.
     */
    public function test_send_code_with_turnstile_invalid_json_response()
    {
        config()->set('epicollect.setup.cloudflare_turnstile.use_cloudflare_turnstile', true);

        $email = config('testing.MANAGER_EMAIL');
        Mail::fake();

        // Mock an invalid JSON response from Cloudflare
        Http::fake([
            config('epicollect.setup.cloudflare_turnstile.verify_endpoint') => Http::response(
                'Invalid JSON',
                200
            )
        ]);

        $response = $this->post(Route('passwordless-token-web'), [
            'email' => $email,
            'cf-turnstile-response' => 'token'
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_380', session('errors')->getBag('default')->first());

        Mail::assertNotSent(UserPasswordlessApiMail::class);
    }

}
