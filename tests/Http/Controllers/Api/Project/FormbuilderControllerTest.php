<?php

namespace Tests\Http\Controllers\Api\Project;

use Carbon\Carbon;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use League\Csv\Exception;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;


class FormbuilderControllerTest extends TestCase
{
    use DatabaseTransactions;

    private $user;
    private $projectDefinition;
    private $project;
    private $faker;

    public function setUp()
    {
        parent::setUp();

        $this->faker = Faker::create();
        //create fake user for testing
        $user = factory(User::class)->create();
        //create a project with custom project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'name' => array_get($projectDefinition, 'data.project.name'),
                'slug' => array_get($projectDefinition, 'data.project.slug'),
                'ref' => array_get($projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.private')
            ]
        );
        //add role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //create basic project definition
        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data'])
            ]
        );
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        $this->user = $user;
        $this->projectDefinition = $projectDefinition;
        $this->project = $project;
    }

    public function test_should_save_project()
    {
        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);
        try {
            $response->assertStatus(200);
        } catch (\Exception $exception) {
            dd($response, json_encode($this->projectDefinition));
        }

        $this->assertSame(json_decode($response->getContent(), true), $this->projectDefinition);
    }

    /* This method does not follow optimal testing practices
    since the application does not get rebooted before each request
    like a production environment,
    but these tests are still useful to find bugs when uploading
    a project definition

    be aware __construct() are called only the first time,
    so it might have some false positives or not detect
    some errors
    */
    public function test_multiple_projects()
    {
        //create some projects with custom project definition
        $n = rand(25, 50);
        for ($i = 0; $i < $n; $i++) {
            sleep(1);
            $this->test_should_save_project();
        }
    }

    public function test_should_catch_duplicated_form_name()
    {
        $formName = "Duplicated Form";
        $this->projectDefinition['data']['project']['forms'][0]['name'] = $formName;
        $this->projectDefinition['data']['project']['forms'][1]['name'] = $formName;

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455

        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_245",
                            "title" => "You cannot have two forms with the same name.",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_should_catch_duplicated_form_ref()
    {
        $form = $this->projectDefinition['data']['project']['forms'][0];
        $formName = $form['name'];
        $this->projectDefinition['data']['project']['forms'][1] = $form;
        $this->projectDefinition['data']['project']['forms'][1]['name'] = $formName . '2';

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_224",
                            "title" => "Duplicate Value found.",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_should_catch_missing_form_name()
    {
        $this->projectDefinition['data']['project']['forms'][0]['name'] = '';

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_246",
                            "title" => "The form name is missing.",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_should_catch_emoji_in_question()
    {
        $this->projectDefinition['data']['project']['forms'][0]['inputs'][0]['question'] = '😊';

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_323",
                            "title" => "No Emoji allowed.",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_should_catch_html_in_question()
    {
        $this->projectDefinition['data']['project']['forms'][0]['inputs'][0]['question'] = '<a href="ciao.com"></a>';

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_220",
                            "title" => "No < or > chars allowed.",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_should_catch_emoji_in_form_name()
    {
        $this->projectDefinition['data']['project']['forms'][0]['name'] = '😊';

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_323",
                            "title" => "No Emoji allowed.",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_should_catch_html_in_form_name()
    {
        $this->projectDefinition['data']['project']['forms'][0]['name'] = '<strong>Ciao</strong>';

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_220",
                            "title" => "No < or > chars allowed.",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_should_catch_emoji_in_possible_answer()
    {
        $inputs = $this->projectDefinition['data']['project']['forms'][0]['inputs'];
        $randomMultipleChoiceInput = $this->faker->randomElement(
            array_keys(
                [
                    'dropdown' => 'dropdown',
                    'radio' => 'radio',
                    'checkbox' => 'checkbox',
                    'searchsingle' => 'searchsingle',
                    'searchmultiple' => 'searchmultiple'
                ]));

        foreach ($inputs as $index => $input) {
            if ($input['type'] === $randomMultipleChoiceInput) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['possible_answers'][0]['answer'] = '😊';
                break;
            }
        }

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_323",
                            "title" => "No Emoji allowed.",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_should_catch_html_in_possible_answer()
    {
        $inputs = $this->projectDefinition['data']['project']['forms'][0]['inputs'];
        $randomMultipleChoiceInput = $this->faker->randomElement(
            array_keys(
                [
                    'dropdown' => 'dropdown',
                    'radio' => 'radio',
                    'checkbox' => 'checkbox',
                    'searchsingle' => 'searchsingle',
                    'searchmultiple' => 'searchmultiple'
                ]));

        foreach ($inputs as $index => $input) {
            if ($input['type'] === $randomMultipleChoiceInput) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['possible_answers'][0]['answer'] = '<header>Ciao</header>';
                break;
            }
        }

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_220",
                            "title" => "No < or > chars allowed.",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_should_catch_too_long_form_name()
    {
        $this->projectDefinition['data']['project']['forms'][0]['name'] = $this->faker->regexify('[A-Za-z0-9]{51}');

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_44",
                            "title" => "Value too long",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_should_catch_invalid_chars_in_form_name()
    {
        $this->projectDefinition['data']['project']['forms'][0]['name'] = '++Special#';

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_29",
                            "title" => "Value invalid.",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_should_catch_invalid_form_slug()
    {
        $this->projectDefinition['data']['project']['forms'][0]['slug'] = '¢¢¢¢';

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_29",
                            "title" => "Value invalid.",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_should_catch_invalid_form_ref()
    {
        $this->projectDefinition['data']['project']['forms'][0]['ref'] = 'gibberish';

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_243",
                            "title" => "Invalid input ref",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_should_catch_zero_forms()
    {
        $this->projectDefinition['data']['project']['forms'] = [];

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_67",
                            "title" => "No forms in this project.",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_should_catch_too_many_forms()
    {
        $projectDefinition = ProjectDefinitionGenerator::createProject(rand(6, 50));

        $this->projectDefinition = $projectDefinition;

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_263",
                            "title" => "Too many forms in this project.",
                            "source" => "validation"
                        ]
                    ]
                ]
            );
    }

    public function test_it_should_update_structures_by_removing_questions()
    {
        $beforeInputs = $this->projectDefinition['data']['project']['forms'][0]['inputs'];

        //remove some inputs
        $afterInputs = array_slice($beforeInputs, 0, -3);

        $this->projectDefinition['data']['project']['forms'][0]['inputs'] = $afterInputs;

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        try {
            $response->assertStatus(200);
        } catch (\Exception $exception) {
            dd($response, json_encode($this->projectDefinition));
        }

        $this->assertSame(json_decode($response->getContent(), true), $this->projectDefinition);

        $updatedStructures = ProjectStructure::where('project_id', $this->project->id)->first();

        $updatedProjectDefinition = json_decode($updatedStructures->project_definition, true);
        $updatedProjectExtra = json_decode($updatedStructures->project_extra, true);

        //todo: need to assert project extra but first we need to create the class that generates it


        $this->assertEquals($updatedProjectDefinition['project']['forms'][0]['inputs'], $afterInputs);
    }

    public function test_it_should_update_structures_updated_at()
    {
        $beforeUpdatedAt = ProjectStructure::where('project_id', $this->project->id)->pluck('updated_at')->first();
        sleep(5);

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        try {
            $response->assertStatus(200);
        } catch (\Exception $exception) {
            dd($response, json_encode($this->projectDefinition));
        }

        $this->assertSame(json_decode($response->getContent(), true), $this->projectDefinition);
        //assert updated_at changed
        $afterUpdatedAt = ProjectStructure::where('project_id', $this->project->id)->pluck('updated_at')->first();
        $this->assertLessThan(
            Carbon::parse($afterUpdatedAt, 'UTC'),
            Carbon::parse($beforeUpdatedAt, 'UTC')
        );

        //assert structure last updated in the api response
        $response = [];
        try {
            $response[] = $this->json('GET', 'api/internal/project/' . $this->project->slug)
                ->assertStatus(200)
                ->assertJsonStructure([
                    'meta' => [
                        'project_extra' => [],
                        'project_user' => [],
                        'project_mapping' => [],
                        'project_stats' => []
                    ],
                    'data' => config('testing.JSON_STRUCTURES_WITH_WILDCARD.project_definition')
                ]);
            $jsonResponse = json_decode($response[0]->getContent(), true);
            $this->assertEquals(
                $afterUpdatedAt->toDateTimeString(),
                $jsonResponse['meta']['project_stats']['structure_last_updated']
            );

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }

        //assert version
        $response = [];
        try {
            $response[] = $this->json('GET', 'api/project-version/' . $this->project->slug)
                ->assertStatus(200);

            $jsonResponse = json_decode($response[0]->getContent(), true);
            $this->assertEquals(
                $afterUpdatedAt->toDateTimeString(),
                $jsonResponse['data']['attributes']['structure_last_updated']
            );
            $this->assertEquals(
                (string)strtotime($afterUpdatedAt),
                $jsonResponse['data']['attributes']['version']
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_update_structures_by_adding_questions()
    {
        $beforeInputs = $this->projectDefinition['data']['project']['forms'][0]['inputs'];
        $formRef = $this->projectDefinition['data']['project']['forms'][0]['ref'];

        $additionalInputs = [
            ProjectDefinitionGenerator::createSimpleInput($formRef),
            ProjectDefinitionGenerator::createMediaInput($formRef)
        ];

        $afterInputs = array_merge($beforeInputs, $additionalInputs);

        $this->projectDefinition['data']['project']['forms'][0]['inputs'] = $afterInputs;

        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        try {
            $response->assertStatus(200);
        } catch (\Exception $exception) {
            dd($response, json_encode($this->projectDefinition));
        }

        $this->assertSame(json_decode($response->getContent(), true), $this->projectDefinition);

        $updatedStructures = ProjectStructure::where('project_id', $this->project->id)->first();

        $updatedProjectDefinition = json_decode($updatedStructures->project_definition, true);
        $updatedProjectExtra = json_decode($updatedStructures->project_extra, true);
        //todo: need to assert project extra but first we need to create the class that generates it

        $this->assertEquals($updatedProjectDefinition['project']['forms'][0]['inputs'], $afterInputs);
    }

    public function test_it_should_update_structures_by_adding_child_form()
    {
        $projectRef = $this->projectDefinition['data']['project']['ref'];
        $childFormRef = Generators::formRef($projectRef);

        $formName = 'Form 2';
        $form = [
            "ref" => $childFormRef,
            "name" => $formName,
            "slug" => Str::slug($formName),
            "type" => "hierarchy",
            "inputs" => [ProjectDefinitionGenerator::createSimpleInput($childFormRef),
                ProjectDefinitionGenerator::createMediaInput($childFormRef)]
        ];

        $this->projectDefinition['data']['project']['forms'][1] = $form;


        // Convert data array to JSON
        $jsonData = json_encode($this->projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($this->user)
            ->call('POST', 'api/internal/formbuilder/' . $this->project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);

        try {
            $response->assertStatus(200);
        } catch (\Exception $exception) {
            dd($response, json_encode($this->projectDefinition));
        }

        $this->assertSame(json_decode($response->getContent(), true), $this->projectDefinition);

        $updatedStructures = ProjectStructure::where('project_id', $this->project->id)->first();

        $updatedProjectDefinition = json_decode($updatedStructures->project_definition, true);
        $updatedProjectExtra = json_decode($updatedStructures->project_extra, true);
        //todo: need to assert project extra but first we need to create the class that generates it

        $this->assertEquals($updatedProjectDefinition['project']['forms'][1], $form);
    }

}