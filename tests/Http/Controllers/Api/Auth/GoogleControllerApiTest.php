<?php

namespace Tests\Http\Controllers\Api\Auth;

use Carbon\Carbon;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\User\User;
use ec5\Models\User\UserPasswordlessApi;
use ec5\Models\User\UserProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class GoogleControllerApiTest extends TestCase
{
    use DatabaseTransactions;

    protected string $googleProvider;

    /**
     * Test an authenticated user's routes
     * imp: avoid $this->actingAs($user, 'api_external');
     * imp: as that create a valid user object therefore bypassing
     * imp: jwt validation. We need to send a valid token per each request
     * imp: instead.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->googleProvider = config('epicollect.strings.providers.google');
        config()->set('auth.auth_methods', ['google', 'passwordless']);
        config()->set('auth.auth_allowed_domains', []);
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeGoogleSocialiteUser(string $email, string $givenName = 'John', string $familyName = 'Doe', string $avatar = 'https://lh3.googleusercontent.com/a/whatever'): SocialiteUser
    {
        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = '101291372019815222806';
        $socialiteUser->name = $givenName . ' ' . $familyName;
        $socialiteUser->email = $email;
        $socialiteUser->avatar = $avatar;
        $socialiteUser->setRaw([
            'id' => '101291372019815222806',
            'email' => $email,
            'verified_email' => true,
            'name' => $givenName . ' ' . $familyName,
            'given_name' => $givenName,
            'family_name' => $familyName,
            'picture' => 'https://lh3.googleusercontent.com/a/picture',
            'locale' => 'en-GB',
        ]);

        return $socialiteUser;
    }

    private function mockSocialiteBuildProvider(SocialiteUser $socialiteUser): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('buildProvider')
            ->andReturn($provider);
    }

    private function mockSocialiteBuildProviderThrows(Throwable $exception): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow($exception);

        Socialite::shouldReceive('buildProvider')
            ->andReturn($provider);
    }

    public function test_returns_ec5_55_when_google_not_in_auth_methods()
    {
        config()->set('auth.auth_methods', ['passwordless']);

        $response = $this->post('api/handle/google', []);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_55',
                    'title' => 'Authentication method not allowed.',
                    'source' => 'api-login-google',
                ],
            ],
        ]);
    }

    public function test_returns_ec5_266_when_domain_not_whitelisted()
    {
        config()->set('auth.auth_allowed_domains', ['example.com']);
        $socialiteUser = $this->makeGoogleSocialiteUser('fake.user@gmail.com');
        $this->mockSocialiteBuildProvider($socialiteUser);

        $response = $this->post('api/handle/google', []);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_266',
                    'title' => 'Auth user exception. Please contact support.',
                    'source' => 'api-login-google',
                ],
            ],
        ]);
    }

    public function test_creates_new_user_and_returns_jwt()
    {
        $email = 'newuser.api@gmail.com';
        $socialiteUser = $this->makeGoogleSocialiteUser($email, 'Jane', 'Smith');
        $this->mockSocialiteBuildProvider($socialiteUser);

        $this->assertDatabaseMissing('users', ['email' => $email]);

        $response = $this->post('api/handle/google', []);

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
            'provider' => $this->googleProvider,
        ]);
    }

    public function test_returns_ec5_32_when_user_disabled()
    {
        $email = 'disabled.api@gmail.com';
        factory(User::class)->create([
            'email' => $email,
            'state' => config('epicollect.strings.user_state.disabled'),
        ]);
        $socialiteUser = $this->makeGoogleSocialiteUser($email);
        $this->mockSocialiteBuildProvider($socialiteUser);

        $response = $this->post('api/handle/google', []);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_32',
                    'title' => 'Google user could not be authenticated.',
                    'source' => 'api-login-google',
                ],
            ],
        ]);
    }

    public function test_activates_unverified_user_and_returns_jwt()
    {
        $email = 'unverified.api@gmail.com';
        factory(User::class)->create([
            'email' => $email,
            'name' => '',
            'last_name' => '',
            'state' => config('epicollect.strings.user_state.unverified'),
        ]);
        $socialiteUser = $this->makeGoogleSocialiteUser($email, 'Verified', 'Now');
        $this->mockSocialiteBuildProvider($socialiteUser);

        $response = $this->post('api/handle/google', []);

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
            'provider' => $this->googleProvider,
        ]);
    }

    public function test_returns_ec5_383_when_active_user_has_other_provider_only()
    {
        $email = 'other.provider.api@gmail.com';
        $user = factory(User::class)->create([
            'email' => $email,
            'state' => config('epicollect.strings.user_state.active'),
        ]);
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $email,
            'provider' => config('epicollect.strings.providers.passwordless'),
        ]);
        $socialiteUser = $this->makeGoogleSocialiteUser($email);
        $this->mockSocialiteBuildProvider($socialiteUser);

        $response = $this->post('api/handle/google', []);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_383',
                    'title' => "Account with the provided email already exists.\n Login with email to connect your Google Account",
                    'source' => 'api-login-google',
                ],
            ],
        ]);
        $this->assertDatabaseMissing('users_providers', [
            'email' => $email,
            'provider' => $this->googleProvider,
        ]);
    }

    public function test_returns_jwt_and_updates_details_for_active_google_user()
    {
        $email = 'returning.api@gmail.com';
        $user = factory(User::class)->create([
            'email' => $email,
            'name' => 'OldName',
            'last_name' => 'OldLast',
            'avatar' => 'https://old-avatar.com/photo.jpg',
            'state' => config('epicollect.strings.user_state.active'),
        ]);
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $email,
            'provider' => $this->googleProvider,
        ]);
        $socialiteUser = $this->makeGoogleSocialiteUser($email, 'NewName', 'NewLast', 'https://new-avatar.com/photo.jpg');
        $this->mockSocialiteBuildProvider($socialiteUser);

        $response = $this->post('api/handle/google', []);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->original['data']['jwt']);
        $this->assertEquals('jwt', $response->original['data']['type']);
        $this->assertEquals($email, $response->original['meta']['user']['email']);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => 'NewName',
            'last_name' => 'NewLast',
            'avatar' => 'https://new-avatar.com/photo.jpg',
        ]);
    }

    public function test_returns_ec5_266_when_socialite_throws()
    {
        $this->mockSocialiteBuildProviderThrows(new RuntimeException('boom'));

        $response = $this->post('api/handle/google', []);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_266',
                    'title' => 'Auth user exception. Please contact support.',
                    'source' => 'api-login-google',
                ],
            ],
        ]);
    }

    public function test_verify_returns_validation_errors_when_payload_invalid()
    {
        $response = $this->post(route('verify-google'), []);

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

        $response = $this->post(route('verify-google'), [
            'email' => $email,
            'code' => $code,
        ]);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_378',
                    'title' => 'Code invalid!',
                    'source' => 'api-login-google',
                ],
            ],
        ]);
    }

    public function test_verify_returns_ec5_378_when_code_invalid()
    {
        $email = 'verify.api.bad.code@gmail.com';
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

        $response = $this->post(route('verify-google'), [
            'email' => $email,
            'code' => $wrongCode,
        ]);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_378',
                    'title' => 'Code invalid!',
                    'source' => 'api-login-google',
                ],
            ],
        ]);
    }

    public function test_verify_returns_ec5_34_when_user_missing()
    {
        $email = 'orphan.passwordless@gmail.com';
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        factory(UserPasswordlessApi::class)->create([
            'email' => $email,
            'code' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
            'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString(),
        ]);

        $response = $this->post(route('verify-google'), [
            'email' => $email,
            'code' => $code,
        ]);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_34',
                    'title' => 'User not found.',
                    'source' => 'api-login-google',
                ],
            ],
        ]);
    }

    public function test_verify_adds_google_provider_and_returns_jwt_on_success()
    {
        $email = 'verify.api.success@gmail.com';
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

        $response = $this->post(route('verify-google'), [
            'email' => $email,
            'code' => $code,
            'user' => [
                'given_name' => 'Some',
                'family_name' => 'Person',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->original['data']['jwt']);
        $this->assertEquals('jwt', $response->original['data']['type']);
        $this->assertEquals($email, $response->original['meta']['user']['email']);

        $this->assertDatabaseHas('users_providers', [
            'user_id' => $user->id,
            'email' => $email,
            'provider' => $this->googleProvider,
        ]);
        $this->assertDatabaseMissing('users_passwordless_api', [
            'email' => $email,
        ]);
    }
}
