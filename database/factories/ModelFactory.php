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
use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Projects\Project as LegacyProject;
use ec5\Models\Eloquent\UserPasswordlessApi;
use ec5\Models\Eloquent\UserPasswordlessWeb;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\ProjectFeatured;
use ec5\Models\Eloquent\ProjectStat;
use Illuminate\Support\Str;
use Webpatser\Uuid\Uuid;

/** @var \Illuminate\Database\Eloquent\Factory $factory */

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
        'user_id' => 123456789,//todo this must be passed in?
        'email' => $faker->safeEmail,
        'provider' => 'local'
    ];
});

$factory->define(UserPasswordlessApi::class, function (Faker\Generator $faker, $params) {
    return [
        'email' => $params['email'],
        'code' => $params['code'],
        'expires_at' => $params['expires_at']
    ];
});

$factory->define(UserPasswordlessWeb::class, function (Faker\Generator $faker, $params) {
    return [
        'email' => $params['email'],
        'token' => $params['token'],
        'expires_at' => $params['expires_at']
    ];
});

$factory->define(ProjectRole::class, function (Faker\Generator $faker) {
    return [
        'user_id' => $this->faker->randomElement(User::pluck('id')->all()),
        'project_id' => $this->faker->randomElement(Project::pluck('id')->all()),
        'role' => 'collector'
    ];
});

$factory->define(Project::class, function (Faker\Generator $faker) {

    $ec5Limits = Config::get('ec5Limits');
    $nameMin = $ec5Limits['project']['name']['min'];
    $nameMax = $ec5Limits['project']['name']['max'];
    $smallDescMin = (int)$ec5Limits['project']['small_desc']['min'];
    $smallDescMax = (int)$ec5Limits['project']['small_desc']['max'];
    $name = $faker->regexify('[A-Za-z_]{' . $nameMin . ',' . $nameMax . '}');

    return [
        'name' => $name,
        'slug' => Str::slug($name),
        'ref' => \ec5\Libraries\Utilities\Generators::projectRef(),
        'description' => $faker->sentence,
        'small_description' => $faker->text($smallDescMin) . $faker->text($smallDescMax - $smallDescMin),
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
        'name' => $name,
        'slug' => Str::slug($name),
        'ref' => \ec5\Libraries\Utilities\Generators::projectRef(),
        'description' => $faker->sentence,
        'small_description' => $faker->text($smallDescMin) . $faker->text($smallDescMax - $smallDescMin),
        'logo_url' => '',
        'access' => 'public',
        'visibility' => 'listed',
        'category' => 'general',
        'created_by' => User::where('email', env('SUPER_ADMIN_EMAIL'))->first()['id'],
        'status' => 'active'
    ];
});

$factory->define(ProjectStructure::class, function (Faker\Generator $faker, $params) {
    //Get project
    $project = Project::where('id', $params['project_id'])->first();
    $projectRef = $project->ref;
    $formRef = $projectRef . '_' . uniqid();
    $inputRef = $formRef . '_' . uniqid();
    $formName = 'Test Form';
    $formSlug = 'test-form';

    //build minimal project definition
    return [
        'project_id' => $project->id,
        'project_definition' => json_encode([
            'id' => $projectRef,
            'type' => 'project',
            'project' => [
                'logo_url' => '',
                'category' => 'general',
                'forms' => [
                    [
                        'ref' => $formRef,
                        'name' => $formName,
                        'slug' => $formSlug,
                        'type' => 'hierarchy',
                        'inputs' => [
                            [
                                'regex' => null,
                                'verify' => false,
                                'default' => null,
                                'max' => null,
                                'uniqueness' => 'none',
                                'type' => 'text',
                                'set_to_current_datetime' => false,
                                'branch' => [
                                ],
                                'group' => [
                                ],
                                'is_required' => false,
                                'jumps' => [
                                ],
                                'is_title' => true,
                                'question' => 'Name',
                                'datetime_format' => null,
                                'ref' => $inputRef,
                                'possible_answers' => [
                                ],
                                'min' => null
                            ],
                            'name' => $formName,
                            'type' => 'hierarchy',
                            'ref' => $formRef
                        ]
                    ]
                ],
                'description' => $project->description,
                'small_description' => $project->small_description,
                'access' => $project->access,
                'entries_limits' => [],
                'slug' => $project->slug,
                'visibility' => $project->listed,
                'ref' => $projectRef,
                'name' => $project->name,
                'status' => $project->active
            ]
        ]),
        'project_extra' => json_encode([
            'forms' => [
                $formRef => [
                    'group' => [
                    ],
                    'lists' => [
                        'location_inputs' => [
                        ],
                        'multiple_choice_inputs' => [
                            'form' => [
                                'order' => [
                                ]
                            ],
                            'branch' => [
                            ]
                        ]
                    ],
                    'branch' => [
                    ],
                    'inputs' => [
                        $inputRef
                    ],
                    'details' => [
                        'ref' => $formRef,
                        'name' => $formName,
                        'slug' => $formSlug,
                        'type' => 'hierarchy',
                        'inputs' => [
                            [
                                'max' => null,
                                'min' => null,
                                'ref' => $inputRef,
                                'type' => 'text',
                                'group' => [
                                ],
                                'jumps' => [
                                ],
                                'regex' => null,
                                'branch' => [
                                ],
                                'verify' => false,
                                'default' => null,
                                'is_title' => false,
                                'question' => 'Name',
                                'uniqueness' => 'none',
                                'is_required' => false,
                                'datetime_format' => null,
                                'possible_answers' => [
                                ],
                                'set_to_current_datetime' => false
                            ]
                        ],
                        'has_location' => false
                    ]
                ]
            ],
            'inputs' => [
                $inputRef => [
                    'data' => [
                        'max' => null,
                        'min' => null,
                        'ref' => $inputRef,
                        'type' => 'text',
                        'group' => [
                        ],
                        'jumps' => [
                        ],
                        'regex' => null,
                        'branch' => [
                        ],
                        'verify' => false,
                        'default' => null,
                        'is_title' => false,
                        'question' => 'Name',
                        'uniqueness' => 'none',
                        'is_required' => false,
                        'datetime_format' => null,
                        'possible_answers' => [
                        ],
                        'set_to_current_datetime' => false
                    ]
                ]
            ],
            'project' => [
                'forms' => [
                    $formRef
                ],
                'details' => [
                    'ref' => $projectRef,
                    'name' => $project->name,
                    'slug' => $project->slug,
                    'access' => $project->access,
                    'status' => $project->status,
                    'category' => $project->category,
                    'logo_url' => '',
                    'visibility' => $project->category,
                    'description' => $project->description,
                    'small_description' => $project->small_description
                ],
                'entries_limits' => []
            ]
        ]),
        'project_mapping' => json_encode([[
            'name' => 'EC5_AUTO',
            'forms' => [
                $formRef => [
                    $inputRef => [
                        'hide' => false,
                        'group' => [
                        ],
                        'branch' => [
                        ],
                        'map_to' => '1_Name',
                        'possible_answers' => [
                        ]
                    ]
                ]
            ],
            'map_index' => 0,
            'is_default' => true
        ]])
    ];
});

$factory->define(Entry::class, function (Faker\Generator $faker, $params) {
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

$factory->define(BranchEntry::class, function (Faker\Generator $faker, $params) {
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

$factory->define(ProjectStat::class, function (Faker\Generator $faker) {
    return [
        'project_id' => null,
        'total_entries' => 0,
        'total_users' => 0,
        'form_counts' => json_encode([]),
        'branch_counts' => json_encode([])
    ];
});

$factory->define(ProjectFeatured::class, function (Faker\Generator $faker) {
    return [
        'project_id' => null,
    ];
});
