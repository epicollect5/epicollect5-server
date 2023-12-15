<?php

namespace Tests\Http\Controllers\Api\Project;

use ec5\Libraries\Utilities\Generators;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\ProjectStats;
use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Eloquent\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use League\Csv\Exception;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ec5\Traits\Assertions;


class ProjectControllerTest extends TestCase
{
    use DatabaseTransactions, Assertions;

    const DRIVER = 'web';

    public function setup()
    {
        parent::setUp();
    }

    public function test_project_exists()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => config('testing.UNIT_TEST_RANDOM_EMAIL')]
        );

        //create a fake project (use ref for name and slug to avoid uniqueness issues)
        $ref = Generators::projectRef();
        $project = factory(Project::class)->create([
            'name' => $ref,
            'slug' => $ref,
            'ref' => $ref,
            'access' => 'public',
            'created_by' => $user->id
        ]);

        $response = $this->actingAs($user, self::DRIVER)
            ->json('GET', 'api/internal/exists/' . $project->slug)
            ->assertStatus(200)
            ->assertExactJson([
                'data' => [
                    'type' => 'exists',
                    'id' => $project->slug,
                    'exists' => true
                ]
            ])
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'id',
                    'exists',
                ]
            ]);
        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        $this->assertKeysNotEmpty($responseData);
    }

    public function test_project_exists_but_not_logged_in()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => config('testing.UNIT_TEST_RANDOM_EMAIL')]
        );

        //create a fake project (use ref for name and slug to avoid uniqueness issues)
        $ref = Generators::projectRef();
        $project = factory(Project::class)->create([
            'name' => $ref,
            'slug' => $ref,
            'ref' => $ref,
            'access' => 'public',
            'created_by' => $user->id
        ]);

        $response = $this->json('GET', 'api/internal/exists/' . $project->slug)
            ->assertStatus(404)
            ->assertExactJson([
                'errors' => [
                    [
                        'code' => 'ec5_219',
                        'title' => 'Page not found.',
                        'source' => 'auth'
                    ]
                ]
            ])
            ->assertJsonStructure([
                'errors' => [
                    [
                        'code',
                        'title',
                        'source'
                    ]
                ]
            ]);
        $responseData = ($response->json())['errors']; // Convert the JSON data response to an array.
        $this->assertKeysNotEmpty($responseData);
    }

    public function test_project_does_not_exists()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => config('testing.UNIT_TEST_RANDOM_EMAIL')]
        );

        //do not create a project
        $ref = Generators::projectRef();


        $response = $this->actingAs($user, self::DRIVER)
            ->json('GET', 'api/internal/exists/' . $ref)
            ->assertStatus(200)
            ->assertExactJson([
                'data' => [
                    'type' => 'exists',
                    'id' => $ref,
                    'exists' => false
                ]
            ])
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'id',
                    'exists',
                ]
            ]);
        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        $this->assertKeysNotEmpty($responseData);
    }

    public function test_search_should_find_single_project()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => config('testing.UNIT_TEST_RANDOM_EMAIL')]
        );

        //create a fake project (use ref for name and slug to avoid uniqueness issues)
        $ref = Generators::projectRef();
        $project = factory(Project::class)->create([
            'name' => $ref,
            'slug' => $ref,
            'ref' => $ref,
            'access' => 'public',
            'created_by' => $user->id
        ]);

        $response = $this->json('GET', 'api/projects/' . $project->name)
            ->assertStatus(200)
            ->assertExactJson([
                'data' => [
                    [
                        'type' => 'project',
                        'id' => $ref,
                        'project' => [
                            'name' => $ref,
                            'slug' => $ref,
                            'access' => 'public',
                            'ref' => $ref
                        ]
                    ]
                ]
            ])
            ->assertJsonStructure(['data' => [
                [
                    'type',
                    'id',
                    'project' => [
                        'name',
                        'slug',
                        'access',
                        'ref'
                    ]
                ]
            ]]);
    }

    public function test_search_should_find_more_projects()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => config('testing.UNIT_TEST_RANDOM_EMAIL')]
        );

        $numOfProjects = 20;
        $needle = 'EC5 Unit ';
        //create fake projects (use 'EC5 Unit' to avoid uniqueness issues)
        for ($i = 0; $i < $numOfProjects; $i++) {
            $project = factory(Project::class)->create([
                'name' => 'EC5 Unit Tests ' . $i,
                'slug' => 'ec5-unit-tests' . $i,
                'access' => 'public',
                'created_by' => $user->id
            ]);
        }

        //assert structure of each element returned
        $response = $this->json('GET', 'api/projects/' . $needle)
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [
                [
                    'type',
                    'id',
                    'project' => [
                        'name',
                        'slug',
                        'access',
                        'ref'
                    ]
                ]
            ]]);

        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        $this->assertCount($numOfProjects, $responseData);
        $this->assertKeysNotEmpty($responseData);
    }

    public function test_search_should_skip_archived_projects()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => config('testing.UNIT_TEST_RANDOM_EMAIL')]
        );

        $numOfProjects = 20;
        $needle = 'EC5 Unit ';
        //create fake projects (use 'EC5 Unit' to avoid uniqueness issues)
        for ($i = 0; $i < $numOfProjects; $i++) {
            $project = factory(Project::class)->create([
                'name' => 'EC5 Unit Tests ' . $i,
                'slug' => 'ec5-unit-tests' . $i,
                'access' => 'public',
                'status' => 'archived',
                'created_by' => $user->id
            ]);
        }

        //assert structure of each element returned
        $response = $this->json('GET', 'api/projects/' . $needle)
            ->assertStatus(200)
            ->assertJsonStructure(['data' => []]);

        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        $this->assertCount(0, $responseData);
    }

    public function test_search_should_skip_trashed_projects()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => config('testing.UNIT_TEST_RANDOM_EMAIL')]
        );

        $numOfProjects = 20;
        $needle = 'EC5 Unit ';
        //create fake projects (use 'EC5 Unit' to avoid uniqueness issues)
        for ($i = 0; $i < $numOfProjects; $i++) {
            $project = factory(Project::class)->create([
                'name' => 'EC5 Unit Tests ' . $i,
                'slug' => 'ec5-unit-tests' . $i,
                'access' => 'public',
                'status' => 'trashed',
                'created_by' => $user->id
            ]);
        }

        //assert structure of each element returned
        $response = $this->json('GET', 'api/projects/' . $needle)
            ->assertStatus(200)
            ->assertJsonStructure(['data' => []]);

        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        $this->assertCount(0, $responseData);
    }

    public function test_search_should_return_empty_collection_if_no_name_passed()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => config('testing.UNIT_TEST_RANDOM_EMAIL')]
        );

        //create a fake project (use ref for name and slug to avoid uniqueness issues)
        $ref = Generators::projectRef();
        $project = factory(Project::class)->create([
            'name' => $ref,
            'slug' => $ref,
            'ref' => $ref,
            'access' => 'public',
            'created_by' => $user->id
        ]);

        $response = $this->json('GET', 'api/projects/')
            ->assertStatus(200)
            ->assertExactJson([
                'data' => []
            ]);

        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        $this->assertCount(0, $responseData);
    }

    public function test_should_get_project_version()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => config('testing.UNIT_TEST_RANDOM_EMAIL')]
        );

        //create a fake project (use ref for name and slug to avoid uniqueness issues)
        $ref = Generators::projectRef();
        $project = factory(Project::class)->create([
            'name' => $ref,
            'slug' => $ref,
            'ref' => $ref,
            'access' => 'public',
            'created_by' => $user->id
        ]);

        $projectStructure = factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $response = $this->json('GET', 'api/project-version/' . $project->slug)
            ->assertStatus(200)
            ->assertExactJson([
                'data' => [
                    'type' => 'project-version',
                    'id' => $project->slug,
                    'attributes' => [
                        'structure_last_updated' => $projectStructure->updated_at->toDateTimeString(),
                        'version' => (string)$projectStructure->updated_at->timestamp
                    ]
                ]
            ])
            ->assertJsonStructure(['data' => [
                'type',
                'id',
                'attributes' => [
                    'structure_last_updated',
                    'version',
                ]
            ]]);

        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        $this->assertKeysNotEmpty($responseData);

    }

    public function test_version_should_bail_if_project_not_found()
    {
        //look for a project that does not exist
        $ref = Generators::projectRef();

        $response = $this->json('GET', 'api/project-version/' . $ref)
            ->assertStatus(500)
            ->assertExactJson([
                'errors' => [
                    [
                        'code' => 'ec5_11',
                        'title' => 'Project does not exist.',
                        'source' => 'version'
                    ]
                ]
            ])
            ->assertJsonStructure(['errors' => [
                [
                    'code',
                    'title',
                    'source'
                ]
            ]]);

        $responseError = ($response->json())['errors']; // Convert the JSON data response to an array.
        $this->assertKeysNotEmpty($responseError);
    }

    public function test_should_return_project_definition_as_json()
    {
        $user = factory(User::class)->create();
        // 2- create mock project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);

        //assign the user to that project with the CREATOR role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //add fake stats
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0
        ]);

        //create fake structure, and rename to match the existing project
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);
        $projectDefinition['data']['project']['name'] = $project->name;
        $projectDefinition['data']['project']['slug'] = Str::slug($project->name);

        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id,
                //pass the "data" content as the controller tested will add it under "data" again
                'project_definition' => json_encode($projectDefinition['data'])
            ]
        );

        $response = [];
        try {
            $response[] = $this->json('GET', 'api/internal/project/' . $project->slug)
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
            $this->assertProjectResponse($jsonResponse);
        } catch (Exception $e) {
            dd($e->getMessage(), $response[0]->getContent());
        }
    }

    //test response by getting existing projects randomly
    public function test_should_assert_response_using_existing_projects()
    {
        $projects = Project::inRandomOrder()->take(100)->get();
        //use superadmin account to be able to access any project
        $superadmin = User::where('email', config('epicollect.setup.super_admin_user.email'))->first();

        foreach ($projects as $project) {
            $response = $this->actingAs($superadmin)
                ->json('GET', 'api/internal/project/' . $project->slug)
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
            $jsonResponse = json_decode($response->getContent(), true);
            $this->assertProjectResponse($jsonResponse);
        }
    }

    public function test_should_update_bulk_upload_status()
    {
        $canBulkUploadStatuses = config('epicollect.strings.can_bulk_upload');

        $user = factory(User::class)->create(
            ['email' => config('testing.UNIT_TEST_RANDOM_EMAIL')]
        );
        //create a fake project (use ref for name and slug to avoid uniqueness issues)
        $ref = Generators::projectRef();
        $project = factory(Project::class)->create([
            'name' => $ref,
            'slug' => $ref,
            'ref' => $ref,
            'access' => 'private',
            'created_by' => $user->id
        ]);
        //assign the user to that project with the CREATOR role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //add fake stats
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0
        ]);
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $desiredStatus = $canBulkUploadStatuses[array_rand($canBulkUploadStatuses)];
        $response = $this->actingAs($user)
            ->json('POST', 'api/internal/can-bulk-upload/' . $project->slug,
                ['can_bulk_upload' => $desiredStatus]
            )->assertStatus(200)
            ->assertExactJson([
                'data' => [
                    'message' => 'Bulk upload settings updated.'
                ]
            ]);

        $storedProject = Project::where('id', $project->id)->first();
        $responseData = ($response->json())['data'];
        $this->assertKeysNotEmpty($responseData);
        $this->assertEquals($storedProject->can_bulk_upload, $desiredStatus);
    }
}