<?php

namespace Tests\Http\Controllers\Web\Auth;

use Carbon\Carbon;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\User\User;
use ec5\Models\User\UserPasswordlessApi;
use ec5\Models\User\UserProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleControllerWebTest extends TestCase
{
    use DatabaseTransactions;

    protected string $googleProvider;

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

    private function mockSocialiteCallback(SocialiteUser $socialiteUser): void
    {
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('with')
            ->with($this->googleProvider)
            ->andReturn($provider);
    }

    public function test_redirect_returns_socialite_redirect_when_google_enabled()
    {
        config()->set('auth.auth_methods', ['google']);

        $response = $this->get('redirect/google');

        $response->assertStatus(302);
        $this->assertStringContainsString('https://accounts.google.com/o/oauth2/', $response->headers->get('Location'));
    }

    public function test_redirect_shows_login_with_ec5_55_when_google_not_in_auth_methods()
    {
        config()->set('auth.auth_methods', ['passwordless']);

        $response = $this->get('redirect/google');

        $response->assertStatus(200);
        $this->assertEquals('auth.login', $response->original->getName());
        $this->assertEquals(['ec5_55'], $response->original->getData()['errors']->all());
    }

    public function test_redirect_shows_login_with_ec5_38_when_provider_key_empty()
    {
        config()->set('auth.auth_methods', ['google']);
        config()->set('services.google', []);

        $response = $this->get('redirect/google');

        $response->assertStatus(200);
        $this->assertEquals('auth.login', $response->original->getName());
        $this->assertEquals(['ec5_38'], $response->original->getData()['errors']->all());
    }

    public function test_handle_callback_redirects_to_login_with_ec5_266_when_domain_not_whitelisted()
    {
        config()->set('auth.auth_allowed_domains', ['example.com']);
        $socialiteUser = $this->makeGoogleSocialiteUser('fake.user@gmail.com');
        $this->mockSocialiteCallback($socialiteUser);

        $response = $this->get('handle/google');

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_266', session('errors')->getBag('default')->first());
        $this->assertFalse(Auth::check());
    }

    public function test_handle_callback_creates_new_user_and_logs_in_when_user_does_not_exist()
    {
        $email = 'newuser@gmail.com';
        $socialiteUser = $this->makeGoogleSocialiteUser($email, 'Jane', 'Smith');
        $this->mockSocialiteCallback($socialiteUser);

        $this->assertDatabaseMissing('users', ['email' => $email]);

        $response = $this->get('handle/google');

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
            'provider' => $this->googleProvider,
        ]);
    }

    public function test_handle_callback_redirects_to_login_with_ec5_212_when_user_disabled()
    {
        $email = 'disabled.user@gmail.com';
        factory(User::class)->create([
            'email' => $email,
            'state' => config('epicollect.strings.user_state.disabled'),
        ]);
        $socialiteUser = $this->makeGoogleSocialiteUser($email);
        $this->mockSocialiteCallback($socialiteUser);

        $response = $this->get('handle/google');

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_212', session('errors')->getBag('default')->first());
        $this->assertFalse(Auth::check());
    }

    public function test_handle_callback_activates_unverified_user_and_logs_in()
    {
        $email = 'unverified.user@gmail.com';
        factory(User::class)->create([
            'email' => $email,
            'name' => '',
            'last_name' => '',
            'state' => config('epicollect.strings.user_state.unverified'),
        ]);
        $socialiteUser = $this->makeGoogleSocialiteUser($email, 'Verified', 'Now');
        $this->mockSocialiteCallback($socialiteUser);

        $response = $this->get('handle/google');

        $response->assertStatus(302);
        $this->assertTrue(Auth::check());
        $this->assertEquals($email, Auth::user()->email);

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

    public function test_handle_callback_redirects_to_verification_code_when_active_user_has_other_provider()
    {
        $email = 'has.other.provider@gmail.com';
        $user = factory(User::class)->create([
            'email' => $email,
            'state' => config('epicollect.strings.user_state.active'),
        ]);
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $email,
            'provider' => config('epicollect.strings.providers.passwordless'),
        ]);
        $socialiteUser = $this->makeGoogleSocialiteUser($email, 'Some', 'Person');
        $this->mockSocialiteCallback($socialiteUser);

        $response = $this->get('handle/google');

        $response->assertStatus(302);
        $response->assertRedirect(route('verification-code'));
        $this->assertEquals($email, session('email'));
        $this->assertEquals($this->googleProvider, session('provider'));
        $this->assertEquals('Some', session('name'));
        $this->assertEquals('Person', session('last_name'));
        $this->assertFalse(Auth::check());

        $this->assertDatabaseMissing('users_providers', [
            'email' => $email,
            'provider' => $this->googleProvider,
        ]);
    }

    public function test_handle_callback_logs_in_active_user_with_google_provider_and_updates_details()
    {
        $email = 'returning.user@gmail.com';
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
        $this->mockSocialiteCallback($socialiteUser);

        $response = $this->get('handle/google');

        $response->assertStatus(302);
        $this->assertTrue(Auth::check());
        $this->assertEquals($email, Auth::user()->email);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => 'NewName',
            'last_name' => 'NewLast',
            'avatar' => 'https://new-avatar.com/photo.jpg',
        ]);
    }

    public function test_handle_callback_redirects_to_login_with_ec5_213_on_invalid_state_exception()
    {
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')
            ->andThrow(new InvalidStateException('Invalid state'));
        Socialite::shouldReceive('with')
            ->with($this->googleProvider)
            ->andReturn($provider);

        $response = $this->get('handle/google');

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertEquals('ec5_213', session('errors')->getBag('default')->first());
        $this->assertFalse(Auth::check());
    }

    public function test_verify_redirects_back_with_validation_errors_when_payload_invalid()
    {
        $response = $this->post(route('verification-google'), []);

        $response->assertStatus(302);
        $response->assertRedirect(route('verification-code'));
        $this->assertTrue(session('errors')->getBag('default')->has('email'));
        $this->assertTrue(session('errors')->getBag('default')->has('code'));
        $this->assertFalse(Auth::check());
    }

    public function test_verify_redirects_with_ec5_378_when_code_invalid()
    {
        $email = 'verify.user@gmail.com';
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

        //submit a different (still well-formed) code
        $wrongCode = Generators::randomNumber(6, 1);
        while ($wrongCode === $validCode) {
            $wrongCode = Generators::randomNumber(6, 1);
        }

        $response = $this->post(route('verification-google'), [
            'email' => $email,
            'code' => $wrongCode,
            'user' => [
                'given_name' => 'Some',
                'family_name' => 'Person',
            ],
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('verification-code'));
        $this->assertEquals('ec5_378', session('errors')->getBag('default')->first());
        $this->assertFalse(Auth::check());
    }

    public function test_verify_logs_in_user_and_adds_google_provider_on_valid_code()
    {
        $email = 'verify.success@gmail.com';
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

        $response = $this->post(route('verification-google'), [
            'email' => $email,
            'code' => $code,
            'user' => [
                'given_name' => 'Some',
                'family_name' => 'Person',
            ],
        ]);

        $response->assertStatus(302);
        $this->assertTrue(Auth::check());
        $this->assertEquals($email, Auth::user()->email);

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
