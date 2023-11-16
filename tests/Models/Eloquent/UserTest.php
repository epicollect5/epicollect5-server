<?php

namespace Tests\Models\Eloquent;


use Config;
use ec5\Models\Eloquent\User;
use ec5\Models\Eloquent\UserProvider;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Faker\Factory as Faker;

class UserTest extends TestCase
{
    use DatabaseTransactions;

    protected $faker;
    protected $googleUser;

    public function setup()
    {
        parent::setUp();
        $this->faker = Faker::create();

        $this->googleUser = (object)[
            'token' => "ya29.xxx.xxx",
            'refreshToken' => null,
            'expiresIn' => 3599,
            'id' => $this->getRandomGoogleUserId(),
            'nickname' => null,
            'name' => "Fake User",
            'email' => "fake.user@gmail.com",
            'avatar' => "https://lh3.googleusercontent.com/a/whatever",
            'user' => [
                'given_name' => 'The User Name',
                'last_name' => 'The User Last Name'
            ], // This array's contents are not specified in the provided data
            'avatar_original' => "https://lh3.googleusercontent.com/a/whatever",
        ];
    }

    public function test_should_create_google_user_with_empty_name_and_last_name()
    {
        $randomString = '';
        for ($i = 0; $i < 18; $i++) {
            $randomString .= $this->faker->numberBetween(0, 9); // Append a random digit (0-9)
        }
        // Cast the generated string to a number
        $randomId = (int)$randomString;

        $googleUser = (object)[
            'token' => "ya29.xxx.xxx",
            'refreshToken' => null,
            'expiresIn' => 3599,
            'id' => $randomId,
            'nickname' => null,
            'name' => "Fake User",
            'email' => "fake.user@gmail.com",
            'avatar' => "https://lh3.googleusercontent.com/a/whatever",
            'user' => [
            ], // This array's contents are not specified in the provided data
            'avatar_original' => "https://lh3.googleusercontent.com/a/whatever",
        ];

        $userModel = new User();
        $user = $userModel->createGoogleUser($googleUser);

        $this->assertDatabaseHas('users', [
            'email' => $googleUser->email,
            'name' => '',
            'last_name' => '',
            'state' => Config::get('ec5Strings.user_state.active'),
            'server_role' => Config::get('ec5Strings.server_roles.basic')
        ]);

        $this->assertDatabaseHas('users_providers', [
            'email' => $googleUser->email,
            'user_id' => $user->id,
            'provider' => Config::get('ec5Strings.providers.google')
        ]);
    }

    public function test_should_create_google_user()
    {


        $userModel = new User();
        $user = $userModel->createGoogleUser($this->googleUser);

        $this->assertDatabaseHas('users', [
            'email' => $this->googleUser->email,
            'name' => $this->googleUser->user['given_name'],
            'last_name' => $this->googleUser->user['last_name'],
            'state' => Config::get('ec5Strings.user_state.active'),
            'server_role' => Config::get('ec5Strings.server_roles.basic')
        ]);

        $this->assertDatabaseHas('users_providers', [
            'email' => $this->googleUser->email,
            'user_id' => $user->id,
            'provider' => Config::get('ec5Strings.providers.google')
        ]);
    }

    public function test_should_create_apple_user()
    {
        $userModel = new User();
        $name = 'Apple name';
        $lastName = 'Apple last name';
        $email = 'Apple email';
        $user = $userModel->createAppleUser($name, $lastName, $email);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => $name,
            'last_name' => $lastName,
            'state' => Config::get('ec5Strings.user_state.active'),
            'server_role' => Config::get('ec5Strings.server_roles.basic')
        ]);

        $this->assertDatabaseHas('users_providers', [
            'email' => $email,
            'user_id' => $user->id,
            'provider' => Config::get('ec5Strings.providers.apple')
        ]);
    }

    public function test_should_update_apple_user_with_provider()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();

        $this->assertDatabaseHas('users', [
            'email' => $user->email,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'state' => Config::get('ec5Strings.user_state.active'),
            'server_role' => Config::get('ec5Strings.server_roles.basic')
        ]);

        $userModel = new User();
        $name = 'Apple name';
        $lastName = 'Apple last name';
        $userModel->updateAppleUser($name, $lastName, $user->email, true);

        $this->assertDatabaseHas('users', [
            'email' => $user->email,
            'name' => $name,
            'last_name' => $lastName,
            'state' => Config::get('ec5Strings.user_state.active'),
            'server_role' => Config::get('ec5Strings.server_roles.basic')
        ]);

        $this->assertDatabaseHas('users_providers', [
            'email' => $user->email,
            'user_id' => $user->id,
            'provider' => Config::get('ec5Strings.providers.apple')
        ]);
    }

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
                'state' => Config::get('ec5Strings.user_state.unverified')
            ]

        );

        $this->assertDatabaseHas('users', [
            'email' => $this->googleUser->email,
            'name' => '',
            'last_name' => '',
            'state' => Config::get('ec5Strings.user_state.unverified'),
            'server_role' => Config::get('ec5Strings.server_roles.basic')
        ]);

        $userModel = new User();
        $userModel->updateGoogleUser($this->googleUser);

        $this->assertDatabaseHas('users', [
            'email' => $this->googleUser->email,
            'name' => $this->googleUser->user['given_name'],
            'last_name' => $this->googleUser->user['last_name'],
            'state' => Config::get('ec5Strings.user_state.active'),
            'server_role' => Config::get('ec5Strings.server_roles.basic')
        ]);

        $this->assertDatabaseHas('users_providers', [
            'email' => $this->googleUser->email,
            'user_id' => $user->id,
            'provider' => Config::get('ec5Strings.providers.google')
        ]);
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