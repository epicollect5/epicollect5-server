<?php

namespace Tests\Http\Controllers\Web\Auth;

use Carbon\Carbon;
use Closure;
use ec5\Http\Controllers\Web\Auth\AppleController;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\User\User;
use ec5\Models\User\UserPasswordlessApi;
use ec5\Models\User\UserProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class AppleControllerWebTest extends TestCase
{
    use DatabaseTransactions;

    protected string $appleProvider;

    public function setUp(): void
    {
        parent::setUp();
        $this->appleProvider = config('epicollect.strings.providers.apple');
        config()->set('auth.auth_methods', ['apple', 'google', 'passwordless']);
        config()->set('auth.auth_allowed_domains', []);
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockAppleControllerWeb(?array $tokenPayload = null): void
    {
        $mock = Mockery::mock(AppleController::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();
        $mock->shouldReceive('parseIdentityToken')->andReturn($tokenPayload);

        Closure::bind(function () use ($mock) {
            $mock->authMethods = config('auth.auth_methods');
            $mock->appleProviderLabel = config('epicollect.strings.providers.apple');
            $mock->googleProviderLabel = config('epicollect.strings.providers.google');
            $mock->passwordlessProviderLabel = config('epicollect.strings.providers.passwordless');
            $mock->isAuthWebEnabled = config('auth.auth_web_enabled');
        }, null, AppleController::class)();

        $this->app->instance(AppleController::class, $mock);
    }

    private function makeParsedIdToken(string $email, string $nonce = 'test-nonce'): array
    {
        return [
            'email' => $email,
            'nonce' => $nonce,
        ];
    }

    public function test_handle_callback_returns_ec5_382_when_id_token_missing()
    {
        $response = $this->post('handle/apple', []);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_382', session('errors')->getBag('default')->first());
        $this->assertFalse(Auth::check());
    }

    public function test_handle_callback_returns_ec5_382_when_parse_identity_token_fails()
    {
        $this->mockAppleControllerWeb(null);

        $response = $this->post('handle/apple', ['id_token' => 'invalid.token.here']);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_382', session('errors')->getBag('default')->first());
        $this->assertFalse(Auth::check());
    }

    public function test_handle_callback_returns_ec5_386_when_email_missing_in_token()
    {
        $this->mockAppleControllerWeb(['nonce' => 'test-nonce']);
        session()->put('nonce', 'test-nonce');

        $response = $this->post('handle/apple', ['id_token' => 'token']);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_386', session('errors')->getBag('default')->first());
        $this->assertFalse(Auth::check());
    }

    public function test_handle_callback_returns_ec5_266_when_domain_not_whitelisted()
    {
        config()->set('auth.auth_allowed_domains', ['example.com']);
        $this->mockAppleControllerWeb($this->makeParsedIdToken('user@gmail.com'));
        session()->put('nonce', 'test-nonce');

        $response = $this->post('handle/apple', ['id_token' => 'token']);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_266', session('errors')->getBag('default')->first());
        $this->assertFalse(Auth::check());
    }

    public function test_handle_callback_returns_ec5_386_when_nonce_mismatch()
    {
        $this->mockAppleControllerWeb($this->makeParsedIdToken('user@example.com', 'expected-nonce'));
        session()->put('nonce', 'different-nonce');

        $response = $this->post('handle/apple', ['id_token' => 'token']);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_386', session('errors')->getBag('default')->first());
        $this->assertFalse(Auth::check());
    }

    public function test_handle_callback_creates_new_user_and_logs_in()
    {
        $email = 'new.apple.user@example.com';
        $this->mockAppleControllerWeb($this->makeParsedIdToken($email));
        session()->put('nonce', 'test-nonce');

        $this->assertDatabaseMissing('users', ['email' => $email]);

        $response = $this->post('handle/apple', [
            'id_token' => 'token',
            'user' => json_encode([
                'name' => ['firstName' => 'Jane', 'lastName' => 'Smith'],
            ]),
        ]);

        $response->assertStatus(302);
        $this->assertTrue(Auth::check());
        $this->assertEquals($email, Auth::user()->email);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => 'Jane',
            'last_name' => 'Smith',
            'state' => config('epicollect.strings.user_state.active'),
        ]);
        $this->assertDatabaseHas('users_providers', [
            'email' => $email,
            'provider' => $this->appleProvider,
        ]);
    }

    public function test_handle_callback_uses_default_name_when_no_user_object()
    {
        $email = 'no.name@example.com';
        $this->mockAppleControllerWeb($this->makeParsedIdToken($email));
        session()->put('nonce', 'test-nonce');

        $response = $this->post('handle/apple', [
            'id_token' => 'token',
        ]);

        $response->assertStatus(302);
        $this->assertTrue(Auth::check());

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => config('epicollect.mappings.user_placeholder.apple_first_name'),
            'last_name' => config('epicollect.mappings.user_placeholder.apple_last_name'),
        ]);
    }

    public function test_handle_callback_returns_ec5_212_when_user_disabled()
    {
        $email = 'disabled.apple@example.com';
        factory(User::class)->create([
            'email' => $email,
            'state' => config('epicollect.strings.user_state.disabled'),
        ]);
        $this->mockAppleControllerWeb($this->makeParsedIdToken($email));
        session()->put('nonce', 'test-nonce');

        $response = $this->post('handle/apple', [
            'id_token' => 'token',
            'user' => json_encode([
                'name' => ['firstName' => 'Any', 'lastName' => 'Body'],
            ]),
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_212', session('errors')->getBag('default')->first());
        $this->assertFalse(Auth::check());
    }

    public function test_handle_callback_activates_unverified_user_and_logs_in()
    {
        $email = 'unverified.apple@example.com';
        factory(User::class)->create([
            'email' => $email,
            'name' => '',
            'last_name' => '',
            'state' => config('epicollect.strings.user_state.unverified'),
        ]);
        $this->mockAppleControllerWeb($this->makeParsedIdToken($email));
        session()->put('nonce', 'test-nonce');

        $response = $this->post('handle/apple', [
            'id_token' => 'token',
            'user' => json_encode([
                'name' => ['firstName' => 'Active', 'lastName' => 'Now'],
            ]),
        ]);

        $response->assertStatus(302);
        $this->assertTrue(Auth::check());
        $this->assertEquals($email, Auth::user()->email);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => 'Active',
            'last_name' => 'Now',
            'state' => config('epicollect.strings.user_state.active'),
        ]);
        $this->assertDatabaseHas('users_providers', [
            'email' => $email,
            'provider' => $this->appleProvider,
        ]);
    }

    public function test_handle_callback_redirects_to_verification_code_when_active_user_has_other_provider()
    {
        $email = 'other.apple@example.com';
        $user = factory(User::class)->create([
            'email' => $email,
            'state' => config('epicollect.strings.user_state.active'),
        ]);
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $email,
            'provider' => config('epicollect.strings.providers.google'),
        ]);
        $this->mockAppleControllerWeb($this->makeParsedIdToken($email));
        session()->put('nonce', 'test-nonce');

        $response = $this->post('handle/apple', [
            'id_token' => 'token',
            'user' => json_encode([
                'name' => ['firstName' => 'Cross', 'lastName' => 'Provider'],
            ]),
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('verification-code'));
        $this->assertEquals($email, session('email'));
        $this->assertEquals($this->appleProvider, session('provider'));
        $this->assertFalse(Auth::check());
    }

    public function test_handle_callback_logs_in_existing_apple_user_and_updates_details()
    {
        $email = 'returning.apple@example.com';
        $user = factory(User::class)->create([
            'email' => $email,
            'name' => config('epicollect.mappings.user_placeholder.apple_first_name'),
            'last_name' => '',
            'state' => config('epicollect.strings.user_state.active'),
        ]);
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $email,
            'provider' => $this->appleProvider,
        ]);
        $this->mockAppleControllerWeb($this->makeParsedIdToken($email));
        session()->put('nonce', 'test-nonce');

        $response = $this->post('handle/apple', [
            'id_token' => 'token',
            'user' => json_encode([
                'name' => ['firstName' => 'Updated', 'lastName' => 'Name'],
            ]),
        ]);

        $response->assertStatus(302);
        $this->assertTrue(Auth::check());
        $this->assertEquals($email, Auth::user()->email);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => 'Updated',
            'last_name' => 'Name',
        ]);
    }

    public function test_verify_redirects_back_with_validation_errors_when_payload_invalid()
    {
        $response = $this->post(route('verification-apple'), []);

        $response->assertStatus(302);
        $response->assertRedirect(route('verification-code'));
        $this->assertTrue(session('errors')->getBag('default')->has('email'));
        $this->assertTrue(session('errors')->getBag('default')->has('code'));
        $this->assertFalse(Auth::check());
    }

    public function test_verify_redirects_with_ec5_378_when_code_invalid()
    {
        $email = 'verify.apple@example.com';
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $validCode = Generators::randomNumber(6, 1);

        factory(User::class)->create([
            'email' => $email,
            'state' => config('epicollect.strings.user_state.active'),
        ]);
        factory(UserPasswordlessApi::class)->create([
            'email' => $email,
            'code' => bcrypt($validCode, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
            'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString(),
        ]);

        $wrongCode = Generators::randomNumber(6, 1);
        while ($wrongCode === $validCode) {
            $wrongCode = Generators::randomNumber(6, 1);
        }

        $response = $this->post(route('verification-apple'), [
            'email' => $email,
            'code' => $wrongCode,
            'user' => [
                'givenName' => 'Some',
                'familyName' => 'Person',
            ],
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('verification-code'));
        $this->assertEquals('ec5_378', session('errors')->getBag('default')->first());
        $this->assertFalse(Auth::check());
    }

    public function test_verify_logs_in_user_and_adds_apple_provider_on_valid_code()
    {
        $email = 'verify.apple.success@example.com';
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        $user = factory(User::class)->create([
            'email' => $email,
            'name' => 'Existing',
            'last_name' => 'User',
            'state' => config('epicollect.strings.user_state.active'),
        ]);
        factory(UserPasswordlessApi::class)->create([
            'email' => $email,
            'code' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
            'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString(),
        ]);

        $response = $this->post(route('verification-apple'), [
            'email' => $email,
            'code' => $code,
            'user' => [
                'givenName' => 'Some',
                'familyName' => 'Person',
            ],
        ]);

        $response->assertStatus(302);
        $this->assertTrue(Auth::check());
        $this->assertEquals($email, Auth::user()->email);

        $this->assertDatabaseHas('users_providers', [
            'user_id' => $user->id,
            'email' => $email,
            'provider' => $this->appleProvider,
        ]);
        $this->assertDatabaseMissing('users_passwordless_api', [
            'email' => $email,
        ]);
    }
}
