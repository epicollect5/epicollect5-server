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
use ec5\Models\Projects\Project as LegacyProject;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\BranchEntry;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;
use Webpatser\Uuid\Uuid;

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

$factory->define(ec5\Models\Eloquent\Project::class, function (Faker\Generator $faker) {

    $ec5Limits = Config::get('ec5Limits');
    $nameMin = $ec5Limits['project']['name']['min'];
    $nameMax = $ec5Limits['project']['name']['max'];
    $smallDescMin = (int)$ec5Limits['project']['small_desc']['min'];
    $smallDescMax = (int)$ec5Limits['project']['small_desc']['max'];
    $name = $faker->regexify('[A-Za-z_]{' . $nameMin . ',' . $nameMax . '}');

    return [
        'name' =>  $name,
        'slug' => Str::slug($name),
        'ref' => str_replace('-', '', Uuid::generate(4)),
        'description' => $faker->sentence,
        'small_description' => $faker->text($smallDescMin) .  $faker->text($smallDescMax -  $smallDescMin),
        'logo_url' => '',
        'access' => 'public',
        'visibility' => 'listed',
        'category' => 'general',
        'created_by' => User::where('email', env('SUPER_ADMIN_EMAIL'))->first()['id'],
        'status' => 'active'
    ];
});

$factory->define(LegacyProject::class, function (Faker\Generator $faker) {

    $ec5Limits = Config::get('ec5Limits');
    $nameMin = $ec5Limits['project']['name']['min'];
    $nameMax = $ec5Limits['project']['name']['max'];
    $smallDescMin = (int)$ec5Limits['project']['small_desc']['min'];
    $smallDescMax = (int)$ec5Limits['project']['small_desc']['max'];
    $name = $faker->regexify('[A-Za-z_]{' . $nameMin . ',' . $nameMax . '}');

    return [
        'name' =>  $name,
        'slug' => Str::slug($name),
        'ref' => str_replace('-', '', Uuid::generate(4)),
        'description' => $faker->sentence,
        'small_description' => $faker->text($smallDescMin) .  $faker->text($smallDescMax -  $smallDescMin),
        'logo_url' => '',
        'access' => 'public',
        'visibility' => 'listed',
        'category' => 'general',
        'created_by' => User::where('email', env('SUPER_ADMIN_EMAIL'))->first()['id'],
        'status' => 'active'
    ];
});

$factory->define(ec5\Models\Eloquent\UserProvider::class, function (Faker\Generator $faker) {
    return [
        'user_id' => 123456789, //todo: is this not wrong?
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

$factory->define(ec5\Models\Eloquent\Entry::class, function (Faker\Generator $faker, $params) {
    return [
        'project_id' => 0,
        'uuid' => $faker->uuid(),
        'parent_uuid' => '',
        'form_ref' => '',
        'parent_form_ref' => '',
        'user_id' => 0,
        'platform' => '',
        'device_id' => '',
        'title' => $faker->word,
        'entry_data' => json_encode([]),
        'geo_json_data' => json_encode([]),
        'child_counts' => 0,
        'branch_counts' => json_encode([])
    ];
});

$factory->define(ec5\Models\Eloquent\BranchEntry::class, function (Faker\Generator $faker, $params) {
    return [
        'project_id' => null,
        'uuid' => $faker->uuid(),
        'owner_entry_id' => 0, //FK
        'owner_uuid' => '',
        'owner_input_ref' => '',
        'form_ref' => '',
        'user_id' => null,
        'platform' => '',
        'device_id' => '',
        'title' => $faker->word,
        'entry_data' => json_encode([]),
        'geo_json_data' => json_encode([])
    ];
});
