<?php

namespace Tests\Http\Controllers\Api\Project;

use ec5\Libraries\Utilities\Generators;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\ProjectStats;
use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Eloquent\User;
use Illuminate\Support\Arr;
use Tests\Generators\EntryGenerator;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Faker\Factory as Faker;


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
                'access' => config('ec5Strings.project_access.private')
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

//        $projectMapping = ProjectStructure::where('project_id', $this->project->id)->value('project_mapping');
//
//        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        //  dd(json_encode(EntryGenerator::create($this->projectDefinition, $formRef)));

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
}