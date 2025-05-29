<?php

namespace Tests\Services;

use ec5\Models\User\User;
use ec5\Services\User\UserService;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Throwable;

class UserServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected Generator $faker;
    protected object $googleUser;

    public function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create();
        $this->googleUser = (object)[
            'token' => 'ya29.xxx.xxx',
            'refreshToken' => null,
            'expiresIn' => 3599,
            'id' => $this->getRandomGoogleUserId(),
            'nickname' => null,
            'name' => 'John Doe',
            'email' => 'fake.user@gmail.com',
            'avatar' => 'https://lh3.googleusercontent.com/a/whatever',
            'user' => [
                'id' => '101291372019815222806',
                'email' => 'fake.user@gmail.com',
                'verified_email' => true,
                'name' => 'John Doe',
                'given_name' => 'John',
                'family_name' => 'Doe',
                'picture' => 'https://lh3.googleusercontent.com/a/picture',
                'locale' => 'en-GB',
            ], // This array's contents are not specified in the provided data
            'avatar_original' => 'https://lh3.googleusercontent.com/a/whatever',
        ];
    }

    /**
     * @throws Throwable
     */
    public function test_should_create_passwordless_user()
    {
        $email = 'fake@email.com';
        $this->assertDatabaseMissing('users', [
            'email' => $email,
            'name' => config('epicollect.mappings.user_placeholder.passwordless_first_name'),
            'last_name' => '',
            'state' => config('epicollect.strings.user_state.active'),
            'server_role' => config('epicollect.strings.server_roles.basic')
        ]);

        $this->assertDatabaseMissing('users_providers', [
            'email' => $email,
            'provider' => config('epicollect.strings.providers.passwordless')
        ]);


        UserService::createPasswordlessUser($email);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => config('epicollect.mappings.user_placeholder.passwordless_first_name'),
            'last_name' => '',
            'state' => config('epicollect.strings.user_state.active'),
            'server_role' => config('epicollect.strings.server_roles.basic')
        ]);

        $this->assertDatabaseHas('users_providers', [
            'email' => $email,
            'provider' => config('epicollect.strings.providers.passwordless')
        ]);
    }

    /**
     * @throws Throwable
     */
    public function test_should_update_unverified_user_with_passwordless()
    {
        /*
        Create a fake unverified user and save it to DB
        (when new users are added as members to projects)
        unverified users do not have a provider yet
        as they never logged in
        */
        $email = $this->faker->safeEmail;
        $user = factory(User::class)->create(
            [
                'email' => $email,
                'name' => '',
                'last_name' => '',
                'state' => config('epicollect.strings.user_state.unverified')
            ]
        );

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => '',
            'last_name' => '',
            'state' => config('epicollect.strings.user_state.unverified'),
            'server_role' => config('epicollect.strings.server_roles.basic')
        ]);
        $this->assertDatabaseMissing('users_providers', [
            'email' => $email,
            'user_id' => $user->id
        ]);

        UserService::updateUnverifiedPasswordlessUser($user);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => config('epicollect.mappings.user_placeholder.passwordless_first_name'),
            'last_name' => '',
            'state' => config('epicollect.strings.user_state.active'),
            'server_role' => config('epicollect.strings.server_roles.basic')
        ]);

        $this->assertDatabaseHas('users_providers', [
            'email' => $email,
            'user_id' => $user->id,
            'provider' => config('epicollect.strings.providers.passwordless')
        ]);
    }

    /**
     * @throws Throwable
     */
    public function test_should_create_google_user_with_empty_name_and_last_name()
    {
        //empty name and last name
        $this->googleUser->user['given_name'] = '';
        $this->googleUser->user['family_name'] = '';
        $user = UserService::createGoogleUser($this->googleUser);

        $this->assertDatabaseHas('users', [
            'email' => $this->googleUser->email,
            'name' => '',
            'last_name' => '',
            'state' => config('epicollect.strings.user_state.active'),
            'server_role' => config('epicollect.strings.server_roles.basic')
        ]);

        $this->assertDatabaseHas('users_providers', [
            'email' => $this->googleUser->email,
            'user_id' => $user->id,
            'provider' => config('epicollect.strings.providers.google')
        ]);
    }

    /**
     * @throws Throwable
     */
    public function test_should_create_google_user()
    {
        $user = UserService::createGoogleUser($this->googleUser);

        $this->assertDatabaseHas('users', [
            'email' => $this->googleUser->email,
            'name' => $this->googleUser->user['given_name'],
            'last_name' => $this->googleUser->user['family_name'],
            'state' => config('epicollect.strings.user_state.active'),
            'server_role' => config('epicollect.strings.server_roles.basic')
        ]);

        $this->assertDatabaseHas('users_providers', [
            'email' => $this->googleUser->email,
            'user_id' => $user->id,
            'provider' => config('epicollect.strings.providers.google')
        ]);
    }

    /**
     * @throws Throwable
     */
    public function test_should_create_apple_user()
    {
        $name = 'Apple name';
        $lastName = 'Apple last name';
        $email = 'apple@email.com';
        $user = UserService::createAppleUser($name, $lastName, $email);

        $this->assertNotNull($user);
        // Assert that $user is an instance of the User model
        $this->assertInstanceOf(User::class, $user);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => $name,
            'last_name' => $lastName,
            'state' => config('epicollect.strings.user_state.active'),
            'server_role' => config('epicollect.strings.server_roles.basic')
        ]);

        $this->assertDatabaseHas('users_providers', [
            'email' => $email,
            'user_id' => $user->id,
            'provider' => config('epicollect.strings.providers.apple')
        ]);
    }

    /**
     * @throws Throwable
     */
    public function test_should_update_unverified_user_with_google()
    {
        /*
        Create a fake unverified user and save it to DB
        (when new users are added as members to projects)
        unverified users do not have a provider yet
        as they never logged in
        */
        $user = factory(User::class)->create(
            [
                'email' => $this->googleUser->email,
                'name' => '',
                'last_name' => '',
                'state' => config('epicollect.strings.user_state.unverified'),
                'server_role' => config('epicollect.strings.server_roles.basic')
            ]
        );

        $this->assertDatabaseHas('users', [
            'email' => $this->googleUser->email,
            'name' => '',
            'last_name' => '',
            'state' => config('epicollect.strings.user_state.unverified'),
            'server_role' => config('epicollect.strings.server_roles.basic')
        ]);

        $this->assertDatabaseMissing('users_providers', [
            'email' => $this->googleUser->email,
            'user_id' => $user->id
        ]);

        UserService::updateGoogleUser($this->googleUser);

        $this->assertDatabaseHas('users', [
            'email' => $this->googleUser->email,
            'name' => $this->googleUser->user['given_name'],
            'last_name' => $this->googleUser->user['family_name'],
            'state' => config('epicollect.strings.user_state.active'),
            'server_role' => config('epicollect.strings.server_roles.basic')
        ]);

        $this->assertDatabaseHas('users_providers', [
            'email' => $this->googleUser->email,
            'user_id' => $user->id,
            'provider' => config('epicollect.strings.providers.google')
        ]);
    }

    /**
     * @throws Throwable
     */
    public function test_should_update_apple_user_with_provider()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();

        $this->assertDatabaseHas('users', [
            'email' => $user->email,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'state' => config('epicollect.strings.user_state.active'),
            'server_role' => config('epicollect.strings.server_roles.basic')
        ]);

        $name = 'Apple name';
        $lastName = 'Apple last name';
        UserService::updateAppleUser($name, $lastName, $user->email, true);

        $this->assertDatabaseHas('users', [
            'email' => $user->email,
            'name' => $name,
            'last_name' => $lastName,
            'state' => config('epicollect.strings.user_state.active'),
            'server_role' => config('epicollect.strings.server_roles.basic')
        ]);

        $this->assertDatabaseHas('users_providers', [
            'email' => $user->email,
            'user_id' => $user->id,
            'provider' => config('epicollect.strings.providers.apple')
        ]);
    }

    /**
     * @throws Throwable
     */
    public function test_should_update_unverified_user_with_apple()
    {
        /*
        Create a fake unverified user and save it to DB
        (when new users are added as members to projects)
        unverified users do not have a provider yet
        as they never logged in
        */
        $name = 'Apple name';
        $lastName = 'Apple last name';
        $email = 'fake@email.com';
        $user = factory(User::class)->create(
            [
                'email' => $email,
                'name' => '',
                'last_name' => '',
                'state' => config('epicollect.strings.user_state.unverified')
            ]
        );

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => '',
            'last_name' => '',
            'state' => config('epicollect.strings.user_state.unverified'),
            'server_role' => config('epicollect.strings.server_roles.basic')
        ]);

        $this->assertDatabaseMissing('users_providers', [
            'email' => $email,
            'user_id' => $user->id
        ]);


        UserService::updateAppleUser(
            $name,
            $lastName,
            $email,
            true
        );

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => $name,
            'last_name' => $lastName,
            'state' => config('epicollect.strings.user_state.active'),
            'server_role' => config('epicollect.strings.server_roles.basic')
        ]);

        $this->assertDatabaseHas('users_providers', [
            'email' => $email,
            'user_id' => $user->id,
            'provider' => config('epicollect.strings.providers.apple')
        ]);
    }

    public function test_authentication_domain_allowed()
    {
        // Test with no allowed domains configured (all domains should be allowed)
        config(['auth.auth_allowed_domains' => []]);
        $this->assertTrue(UserService::isAuthenticationDomainAllowed('user@example.com'));
        $this->assertTrue(UserService::isAuthenticationDomainAllowed('user@gmail.com'));
        $this->assertTrue(UserService::isAuthenticationDomainAllowed('user@company.org'));

        // Test with specific allowed domains
        config(['auth.auth_allowed_domains' => ['gmail.com', 'company.org']]);
        $this->assertFalse(UserService::isAuthenticationDomainAllowed('user@example.com'));
        $this->assertTrue(UserService::isAuthenticationDomainAllowed('user@gmail.com'));
        $this->assertTrue(UserService::isAuthenticationDomainAllowed('user@company.org'));

        // Test with a single allowed domain
        config(['auth.auth_allowed_domains' => ['gmail.com']]);
        $this->assertFalse(UserService::isAuthenticationDomainAllowed('user@example.com'));
        $this->assertTrue(UserService::isAuthenticationDomainAllowed('user@gmail.com'));
        $this->assertFalse(UserService::isAuthenticationDomainAllowed('user@company.org'));

        // Test with subdomains
        config(['auth.auth_allowed_domains' => ['company.org']]);
        $this->assertTrue(UserService::isAuthenticationDomainAllowed('user@company.org'));
        $this->assertFalse(UserService::isAuthenticationDomainAllowed('user@sub.company.org'));
    }

    private function getRandomGoogleUserId(): int
    {
        $randomString = '';
        for ($i = 0; $i < 18; $i++) {
            $randomString .= $this->faker->numberBetween(0, 9); // Append a random digit (0-9)
        }
        // Cast the generated string to a number
        return (int)$randomString;
    }
}
