<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Libraries\Utilities\Generators;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use Exception;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class ProjectMappingControllerTest extends TestCase
{
    use DatabaseTransactions, Assertions;

    const DRIVER = 'web';

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

        //post project definition to form,builder to create structures
        // Convert data array to JSON
        $jsonData = json_encode($projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($user)
            ->call('POST', 'api/internal/formbuilder/' . $project->slug,
                [],
                [],
                [],
                [], $base64EncodedData);
        try {
            $response->assertStatus(200);
        } catch (Exception $exception) {
            $this->logTestError($exception, $response);
        }

        $this->assertSame(json_decode($response->getContent(), true), $projectDefinition);

        $this->user = $user;
        $this->projectDefinition = $projectDefinition;
        $this->project = $project;
    }

    public function test_mapping_page_renders_correctly()
    {
        $response = [];
        try {
            $response[0] = $this
                ->actingAs($this->user, self::DRIVER)
                ->get('myprojects/' . $this->project->slug . '/mapping-data');

            $response[0]->assertStatus(200);
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_EC5_AUTO_mapping_is_created()
    {
        //get mapping
        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);

        $this->assertArrayHasKey('name', $projectMappings[0]);
        $this->assertEquals($projectMappings[0]['name'], config('epicollect.mappings.default_mapping_name'));
        $this->assertArrayHasKey('forms', $projectMappings[0]);
        $this->assertIsArray($projectMappings[0]['forms']);
        $this->assertArrayHasKey('map_index', $projectMappings[0]);
        $this->assertEquals(0, $projectMappings[0]['map_index']);
        $this->assertArrayHasKey('is_default', $projectMappings[0]);
        $this->assertEquals(true, $projectMappings[0]['is_default']);


        //assert forms?....

    }

    public function test_new_mapping_is_created()
    {
        //get mapping
        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(1, $projectMappings);

        $params = [
            'name' => 'Random name',
            "is_default" => false
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/mapping-data', $params);
            $response[0]->assertStatus(200);
            $response[0]->assertJsonStructure([
                'data' => [
                    'map_index',
                    'mapping' => ['*' => [
                        'name',
                        'forms',
                        'map_index',
                        'is_default'
                    ]]
                ]
            ]);

            //new map will be created with the default mapping
            $jsonResponse = json_decode($response[0]->getContent(), true);
            $this->assertEquals(
                $projectMappings[0]['forms'],
                $jsonResponse['data']['mapping'][1]['forms']
            );

            //check structures in the db were updated
            $projectStructures = ProjectStructure::where('project_id', $this->project->id)
                ->first();
            $projectMappings = json_decode($projectStructures->project_mapping, true);
            //check the new map was added
            $this->assertCount(2, $projectMappings);
            //check mapping matches response
            $this->assertEquals(
                $projectMappings[1]['forms'],
                $jsonResponse['data']['mapping'][1]['forms']
            );
            $this->assertEquals(
                $projectMappings[1]['name'],
                $params['name']
            );
            $this->assertEquals(
                $projectMappings[1]['is_default'],
                $params['is_default']
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_existing_mapping_is_updated()
    {
        //get mapping
        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(1, $projectMappings);

        //duplicate it and save it to the db
        $projectMappings[] = [
            'name' => 'Random Name',
            'forms' => $projectMappings[0]['forms'],
            'map_index' => 1,
            'is_default' => false
        ];

        //update it
        ProjectStructure::where('project_id', $this->project->id)->update([
                'project_mapping' => json_encode($projectMappings)
            ]
        );

        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(2, $projectMappings);

        $modifiedMapping = $this->getModifiedMapping($projectMappings[1]);

        $params = [
            'action' => 'update',
            'map_index' => 1,
            'mapping' => $modifiedMapping
        ];

        //post updated mapping
        $response = [];
        try {
            $jsonResponse = [];
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/mapping-data/update', $params);
            $response[0]->assertStatus(200);
            $response[0]->assertJsonStructure([
                'data' => [
                    'mapping' => ['*' => [
                        'name',
                        'forms',
                        'map_index',
                        'is_default'
                    ]]
                ]
            ]);

            //assert mapping is updated in the response
            $jsonResponse = json_decode($response[0]->getContent(), true);
            //get the latest mappings which should have the changes
            $projectStructures = ProjectStructure::where('project_id', $this->project->id)
                ->first();
            $projectMappings = json_decode($projectStructures->project_mapping, true);
            //compare against the modified mapping
            $this->assertEquals(
                $projectMappings[1]['forms'],
                $jsonResponse['data']['mapping'][1]['forms']
            );

            $this->assertEquals(
                $projectMappings[1],
                $modifiedMapping
            );

            //assert mapping is updated in the db
            //check structures in the db were updated
            $projectStructures = ProjectStructure::where('project_id', $this->project->id)
                ->first();
            $projectMappings = json_decode($projectStructures->project_mapping, true);
            //check mapping matches response
            $this->assertEquals(
                $projectMappings[1]['forms'],
                $jsonResponse['data']['mapping'][1]['forms']
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_existing_mapping_is_set_as_default()
    {
        //get mapping
        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(1, $projectMappings);

        //duplicate it and save it to the db
        // dd($projectMappings);
        $projectMappings[] = [
            'name' => 'Random name',
            'forms' => $projectMappings[0]['forms'],
            'map_index' => 1,
            'is_default' => false
        ];

        //update it
        ProjectStructure::where('project_id', $this->project->id)->update([
                'project_mapping' => json_encode($projectMappings)
            ]
        );

        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(2, $projectMappings);

        $projectMappings[1]['is_default'] = true;
        $params = [
            'action' => 'make-default',
            'map_index' => 1,
            'mapping' => $projectMappings[1]
        ];

        //post updated mapping
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/mapping-data/update', $params);
            $response[0]->assertStatus(200);
            $response[0]->assertJsonStructure([
                'data' => [
                    'mapping' => ['*' => [
                        'name',
                        'forms',
                        'map_index',
                        'is_default'
                    ]]
                ]
            ]);

            //assert mapping is updated in the response
            $jsonResponse = json_decode($response[0]->getContent(), true);
            $this->assertEquals(
                $projectMappings[1]['forms'],
                $jsonResponse['data']['mapping'][1]['forms']
            );

            //assert mapping is updated in the db
            //check structures in the db were updated
            $projectStructures = ProjectStructure::where('project_id', $this->project->id)
                ->first();
            $projectMappings = json_decode($projectStructures->project_mapping, true);
            //check mapping matches response
            $this->assertEquals(
                $projectMappings[1]['forms'],
                $jsonResponse['data']['mapping'][1]['forms']
            );
            $this->assertTrue(
                $projectMappings[1]['is_default']
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_existing_mapping_is_renamed()
    {
        //get mapping
        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(1, $projectMappings);

        //duplicate it and save it to the db
        $projectMappings[] = [
            'name' => 'Random name',
            'forms' => $projectMappings[0]['forms'],
            'map_index' => 1,
            'is_default' => false
        ];

        //update it
        ProjectStructure::where('project_id', $this->project->id)->update([
                'project_mapping' => json_encode($projectMappings)
            ]
        );

        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(2, $projectMappings);

        $params = [
            'action' => 'rename',
            'name' => 'ec5_' . $this->faker->regexify('^[A-Za-z0-9 \-\_]{3,5}$'),
            'map_index' => 1
        ];

        //post updated mapping
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/mapping-data/update', $params);
            $response[0]->assertStatus(200);
            $response[0]->assertJsonStructure([
                'data' => [
                    'mapping' => ['*' => [
                        'name',
                        'forms',
                        'map_index',
                        'is_default'
                    ]]
                ]
            ]);

            //assert mapping is updated in the response
            $jsonResponse = json_decode($response[0]->getContent(), true);
            $this->assertEquals(
                $projectMappings[1]['forms'],
                $jsonResponse['data']['mapping'][1]['forms']
            );

            //assert mapping is updated in the db
            //check structures in the db were updated
            $projectStructures = ProjectStructure::where('project_id', $this->project->id)
                ->first();
            $projectMappings = json_decode($projectStructures->project_mapping, true);
            //check mapping matches response
            $this->assertEquals(
                $projectMappings[1]['forms'],
                $jsonResponse['data']['mapping'][1]['forms']
            );
            //renaming does not affect "is_default"
            $this->assertFalse(
                $projectMappings[1]['is_default']
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_existing_mapping_is_deleted()
    {
        //get mapping
        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(1, $projectMappings);

        //duplicate it and save it to the db
        $projectMappings[] = [
            'name' => 'Random name',
            'forms' => $projectMappings[0]['forms'],
            'map_index' => 1,
            'is_default' => false
        ];

        //update it
        ProjectStructure::where('project_id', $this->project->id)->update([
                'project_mapping' => json_encode($projectMappings)
            ]
        );

        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(2, $projectMappings);

        $params = [
            'map_index' => 1,
        ];

        //post request to delete mapping
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/mapping-data/delete', $params);
            $response[0]->assertStatus(200);

            $response[0]->assertJsonStructure([
                'data' => [
                    'mapping' => ['*' => [
                        'name',
                        'forms',
                        'map_index',
                        'is_default'
                    ]]
                ]
            ]);

            //assert mapping is updated in the response
            $jsonResponse = json_decode($response[0]->getContent(), true);
            //assert mapping is deleted
            $projectStructures = ProjectStructure::where('project_id', $this->project->id)
                ->first();
            $projectMappings = json_decode($projectStructures->project_mapping, true);
            $this->assertCount(1, $projectMappings);
            $this->assertEquals(
                $projectMappings,
                $jsonResponse['data']['mapping']
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_existing_mapping_max_count_reached()
    {
        //get mapping
        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(1, $projectMappings);

        //duplicate it and save it to the db
        // dd($projectMappings);
        $projectMappings[] = [
            'name' => $this->faker->unique()->regexify('^[A-Za-z0-9 \-\_]{3,20}$'),
            'forms' => $projectMappings[0]['forms'],
            'map_index' => 1,
            'is_default' => false
        ];
        $projectMappings[] = [
            'name' => $this->faker->unique()->regexify('^[A-Za-z0-9 \-\_]{3,20}$'),
            'forms' => $projectMappings[0]['forms'],
            'map_index' => 2,
            'is_default' => false
        ];
        $projectMappings[] = [
            'name' => $this->faker->unique()->regexify('^[A-Za-z0-9 \-\_]{3,20}$'),
            'forms' => $projectMappings[0]['forms'],
            'map_index' => 3,
            'is_default' => false
        ];


        //update it
        ProjectStructure::where('project_id', $this->project->id)->update([
                'project_mapping' => json_encode($projectMappings)
            ]
        );

        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(4, $projectMappings);

        $params = [
            'name' => 'Random name',
            "is_default" => false
        ];

        //post updated mapping
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/mapping-data', $params);
            $response[0]->assertStatus(422);
            $response[0]->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source'
                    ]
                ]
            ]);
            $response[0]->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_229",
                            "title" => "Sorry, you have reached the max number of allowed maps.",
                            "source" => "mapping"
                        ]
                    ]
                ]);
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_mapping_names_must_be_unique()
    {
        //get mapping
        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(1, $projectMappings);

        $names = [
            $this->faker->unique()->regexify('^[A-Za-z0-9 \-\_]{3,20}$'),
            $this->faker->unique()->regexify('^[A-Za-z0-9 \-\_]{3,20}$'),
        ];

        //duplicate it and save it to the db
        $projectMappings[] = [
            'name' => $names[0],
            'forms' => $projectMappings[0]['forms'],
            'map_index' => 1,
            'is_default' => false
        ];
        $projectMappings[] = [
            'name' => $names[1],
            'forms' => $projectMappings[0]['forms'],
            'map_index' => 2,
            'is_default' => false
        ];

        //update it
        ProjectStructure::where('project_id', $this->project->id)->update([
                'project_mapping' => json_encode($projectMappings)
            ]
        );

        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(3, $projectMappings);

        $params = [
            'name' => $names[array_rand($names, 1)],
            "is_default" => false
        ];

        //post updated mapping
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/mapping-data', $params);
            $response[0]->assertStatus(422);
            $response[0]->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source'
                    ]
                ]
            ]);
            $response[0]->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_228",
                            "title" => "Mapping name already exists.",
                            "source" => "mapping"
                        ]
                    ]
                ]);
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_fail_delete_if_mapping_index_not_found()
    {
        //get mapping
        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(1, $projectMappings);


        $params = [
            'map_index' => rand(1, 100)
        ];

        //post updated mapping
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/mapping-data/delete', $params);
            $response[0]->assertStatus(422);
            $response[0]->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source'
                    ]
                ]
            ]);
            $response[0]->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_230",
                            "title" => "This map doesn't exist.",
                            "source" => "mapping"
                        ]
                    ]
                ]);
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_failed_update_when_form_ref_wrong()
    {
        //get mapping
        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(1, $projectMappings);

        //duplicate it and save it to the db
        // dd($projectMappings);
        $projectMappings[] = [
            'name' => 'Random name',
            'forms' => $projectMappings[0]['forms'],
            'map_index' => 1,
            'is_default' => false
        ];

        //update it
        ProjectStructure::where('project_id', $this->project->id)->update([
                'project_mapping' => json_encode($projectMappings)
            ]
        );

        $projectStructures = ProjectStructure::where('project_id', $this->project->id)
            ->first();
        $projectMappings = json_decode($projectStructures->project_mapping, true);
        $this->assertCount(2, $projectMappings);

        // Loop through the main array
        $wrongFormRef = Generators::formRef($this->project->ref);
        foreach ($projectMappings[1]['forms'] as $formRef => $inputRefs) {
            $projectMappings[1]['forms'][$wrongFormRef] = $projectMappings[1]['forms'][$formRef];
        }

        $params = [
            'action' => 'update',
            'map_index' => 1,
            'mapping' => $projectMappings[1]
        ];

        //post updated mapping
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/mapping-data/update', $params);
            $response[0]->assertStatus(422);
            $response[0]->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source'
                    ]
                ]
            ]);
            $response[0]->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_15",
                            "title" => "Form does not exist.",
                            "source" => $wrongFormRef
                        ]
                    ]
                ]);
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }
}