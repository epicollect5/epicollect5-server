<?php

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

use ec5\Models\Eloquent\User;
use ec5\Models\Eloquent\Project;

$factory->define(ec5\Models\Users\User::class, function (Faker\Generator $faker) {

    static $password;

    return [
        'name' => $faker->name,
        'email' => $faker->safeEmail,
        'password' => $password ?: $password = bcrypt('secret'),
        'remember_token' => str_random(10),
        'state' => 'active',
        'server_role' => 'basic'
    ];
});

$factory->define(ec5\Models\Eloquent\UserProvider::class, function (Faker\Generator $faker) {
    return [
        'user_id' => 123456789,
        'email' => $faker->safeEmail,
        'provider' => 'local'
    ];
});

$factory->define(ec5\Models\Eloquent\UserPasswordlessApi::class, function (Faker\Generator $faker, $params) {
    return [
        'email' => $params['email'],
        'code' => $params['code'],
        'expires_at' => $params['expires_at']
    ];
});

$factory->define(ec5\Models\Eloquent\UserPasswordlessWeb::class, function (Faker\Generator $faker, $params) {
    return [
        'email' => $params['email'],
        'token' => $params['token'],
        'expires_at' => $params['expires_at']
    ];
});

$factory->define(ec5\Models\Eloquent\ProjectRole::class, function (Faker\Generator $faker) {
    return [
        'user_id' => $this->faker->randomElement(User::pluck('id')->all()),
        'project_id' => $this->faker->randomElement(Project::pluck('id')->all()),
        'role' => 'collector'
    ];
});
