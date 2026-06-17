<?php

namespace Tests\Http\Controllers\Api\Auth;

use Carbon\Carbon;
use Closure;
use ec5\Http\Controllers\Api\Auth\AppleController;
use ec5\Libraries\Auth\Jwt\JwtUserProvider;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\User\User;
use ec5\Models\User\UserPasswordlessApi;
use ec5\Models\User\UserProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class AppleControllerApiTest extends TestCase
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

    private function mockAppleControllerApiWithParsedToken(?array $parsedToken): void
    {
        $app = $this->app;
        $mock = Mockery::mock(AppleController::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();
        $mock->shouldReceive('parseIdentityToken')->andReturn($parsedToken);

        Closure::bind(function () use ($mock, $app) {
            $mock->authMethods = config('auth.auth_methods');
            $mock->appleProviderLabel = config('epicollect.strings.providers.apple');
            $mock->googleProviderLabel = config('epicollect.strings.providers.google');
            $mock->passwordlessProviderLabel = config('epicollect.strings.providers.passwordless');
            $mock->provider = $app->make(JwtUserProvider::class);
        }, null, AppleController::class)();

        $this->app->instance(AppleController::class, $mock);
    }

    public function test_returns_ec5_382_when_identity_token_missing_or_parse_fails()
    {
        $this->mockAppleControllerApiWithParsedToken(null);

        $response = $this->post('api/handle/apple', ['identityToken' => 'token']);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_382',
                    'title' => 'Error verifying Apple jwt',
                    'source' => 'api-login-apple',
                ],
            ],
        ]);
    }

    public function test_returns_ec5_386_when_email_missing_in_parsed_token()
    {
        $this->mockAppleControllerApiWithParsedToken(['nonce' => 'abc']);

        $response = $this->post('api/handle/apple', [
            'identityToken' => 'token',
            'user' => [
                'givenName' => 'Jane',
                'familyName' => 'Smith',
            ],
        ]);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_386',
                    'title' => 'Apple Account cannot be verified',
                    'source' => 'api-login-apple',
                ],
            ],
        ]);
    }

    public function test_returns_ec5_266_when_domain_not_whitelisted()
    {
        config()->set('auth.auth_allowed_domains', ['example.com']);
        $this->mockAppleControllerApiWithParsedToken(['email' => 'user@gmail.com']);

        $response = $this->post('api/handle/apple', [
            'identityToken' => 'token',
        ]);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_266',
                    'title' => 'Auth user exception. Please contact support.',
                    'source' => 'api-login-apple',
                ],
            ],
        ]);
    }

    public function test_creates_new_user_and_returns_jwt()
    {
        $email = 'new.apple.api@example.com';
        $this->mockAppleControllerApiWithParsedToken(['email' => $email]);

        $this->assertDatabaseMissing('users', ['email' => $email]);

        $response = $this->post('api/handle/apple', [
            'identityToken' => 'token',
            'user' => [
                'givenName' => 'Jane',
                'familyName' => 'Smith',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->original['data']['jwt']);
        $this->assertEquals('jwt', $response->original['data']['type']);
        $this->assertEquals($email, $response->original['meta']['user']['email']);
        $this->assertEquals('Jane', $response->original['meta']['user']['name']);

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

    public function test_uses_default_name_when_no_user_object()
    {
        $email = 'no.name.api@example.com';
        $this->mockAppleControllerApiWithParsedToken(['email' => $email]);

        $response = $this->post('api/handle/apple', [
            'identityToken' => 'token',
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->original['data']['jwt']);
        $this->assertEquals('jwt', $response->original['data']['type']);
        $this->assertEquals($email, $response->original['meta']['user']['email']);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => config('epicollect.mappings.user_placeholder.apple_first_name'),
            'last_name' => config('epicollect.mappings.user_placeholder.apple_last_name'),
        ]);
    }

    public function test_returns_ec5_212_when_user_disabled()
    {
        $email = 'disabled.apple.api@example.com';
        factory(User::class)->create([
            'email' => $email,
            'state' => config('epicollect.strings.user_state.disabled'),
        ]);
        $this->mockAppleControllerApiWithParsedToken(['email' => $email]);

        $response = $this->post('api/handle/apple', [
            'identityToken' => 'token',
            'user' => [
                'givenName' => 'Any',
                'familyName' => 'Body',
            ],
        ]);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_212',
                    'title' => 'Sorry, your account has been disabled.',
                    'source' => 'api-login-apple',
                ],
            ],
        ]);
    }

    public function test_activates_unverified_user_and_returns_jwt()
    {
        $email = 'unverified.apple.api@example.com';
        factory(User::class)->create([
            'email' => $email,
            'name' => '',
            'last_name' => '',
            'state' => config('epicollect.strings.user_state.unverified'),
        ]);
        $this->mockAppleControllerApiWithParsedToken(['email' => $email]);

        $response = $this->post('api/handle/apple', [
            'identityToken' => 'token',
            'user' => [
                'givenName' => 'Verified',
                'familyName' => 'Now',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->original['data']['jwt']);
        $this->assertEquals('jwt', $response->original['data']['type']);
        $this->assertEquals($email, $response->original['meta']['user']['email']);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => 'Verified',
            'last_name' => 'Now',
            'state' => config('epicollect.strings.user_state.active'),
        ]);
        $this->assertDatabaseHas('users_providers', [
            'email' => $email,
            'provider' => $this->appleProvider,
        ]);
    }

    public function test_returns_ec5_384_when_active_user_has_other_provider_only()
    {
        $email = 'other.provider.apple.api@example.com';
        $user = factory(User::class)->create([
            'email' => $email,
            'state' => config('epicollect.strings.user_state.active'),
        ]);
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $email,
            'provider' => config('epicollect.strings.providers.google'),
        ]);
        $this->mockAppleControllerApiWithParsedToken(['email' => $email]);

        $response = $this->post('api/handle/apple', [
            'identityToken' => 'token',
            'user' => [
                'givenName' => 'Cross',
                'familyName' => 'Provider',
            ],
        ]);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_384',
                    'title' => "Account with the provided email already exists.\n Login with email to connect your Apple Account",
                    'source' => 'api-login-apple',
                ],
            ],
        ]);
        $this->assertDatabaseMissing('users_providers', [
            'email' => $email,
            'provider' => $this->appleProvider,
        ]);
    }

    public function test_returns_jwt_and_updates_details_for_existing_apple_user()
    {
        $email = 'returning.apple.api@example.com';
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
        $this->mockAppleControllerApiWithParsedToken(['email' => $email]);

        $response = $this->post('api/handle/apple', [
            'identityToken' => 'token',
            'user' => [
                'givenName' => 'Updated',
                'familyName' => 'Name',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->original['data']['jwt']);
        $this->assertEquals('jwt', $response->original['data']['type']);
        $this->assertEquals($email, $response->original['meta']['user']['email']);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => 'Updated',
            'last_name' => 'Name',
        ]);
    }

    public function test_verify_returns_validation_errors_when_payload_invalid()
    {
        $response = $this->post(route('verify-apple'), []);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_21',
                    'title' => 'Required field is missing.',
                    'source' => 'email',
                ],
                [
                    'code' => 'ec5_21',
                    'title' => 'Required field is missing.',
                    'source' => 'code',
                ],
            ],
        ]);
    }

    public function test_verify_returns_ec5_378_when_email_not_in_passwordless_table()
    {
        $email = config('testing.MANAGER_EMAIL');
        $code = Generators::randomNumber(6, 1);

        $response = $this->post(route('verify-apple'), [
            'email' => $email,
            'code' => $code,
        ]);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_378',
                    'title' => 'Code invalid!',
                    'source' => 'api-login-apple',
                ],
            ],
        ]);
    }

    public function test_verify_returns_ec5_378_when_code_invalid()
    {
        $email = 'verify.apple.api.bad.code@example.com';
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $validCode = Generators::randomNumber(6, 1);

        factory(UserPasswordlessApi::class)->create([
            'email' => $email,
            'code' => bcrypt($validCode, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
            'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString(),
        ]);

        $wrongCode = Generators::randomNumber(6, 1);
        while ($wrongCode === $validCode) {
            $wrongCode = Generators::randomNumber(6, 1);
        }

        $response = $this->post(route('verify-apple'), [
            'email' => $email,
            'code' => $wrongCode,
        ]);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_378',
                    'title' => 'Code invalid!',
                    'source' => 'api-login-apple',
                ],
            ],
        ]);
    }

    public function test_verify_returns_ec5_34_when_user_missing()
    {
        $email = 'orphan.apple.passwordless@example.com';
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        factory(UserPasswordlessApi::class)->create([
            'email' => $email,
            'code' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
            'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString(),
        ]);

        $response = $this->post(route('verify-apple'), [
            'email' => $email,
            'code' => $code,
        ]);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_34',
                    'title' => 'User not found.',
                    'source' => 'api-login-apple',
                ],
            ],
        ]);
    }

    public function test_verify_adds_apple_provider_and_returns_jwt_on_success()
    {
        $email = 'verify.apple.api.success@example.com';
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

        $response = $this->post(route('verify-apple'), [
            'email' => $email,
            'code' => $code,
            'user' => [
                'givenName' => 'Some',
                'familyName' => 'Person',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->original['data']['jwt']);
        $this->assertEquals('jwt', $response->original['data']['type']);
        $this->assertEquals($email, $response->original['meta']['user']['email']);

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
