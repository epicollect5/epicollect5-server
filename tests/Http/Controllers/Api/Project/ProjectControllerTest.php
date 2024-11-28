<?php

namespace Tests\Http\Controllers\Api\Project;

use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private User $user;
    private Project $project;
    private ProjectStructure $projectStructure;
    public const string DRIVER = 'web';

    public function setup(): void
    {
        parent::setUp();

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
        $projectStructure = factory(ProjectStructure::class)->create(
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
        $this->project = $project;
        $this->projectStructure = $projectStructure;
    }

    public function test_project_exists()
    {
        $response = $this->actingAs($this->user, self::DRIVER)
            ->json('GET', 'api/internal/exists/' . $this->project->slug)
            ->assertStatus(200)
            ->assertExactJson([
                'data' => [
                    'type' => 'exists',
                    'id' => $this->project->slug,
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
        $response = $this->json('GET', 'api/internal/exists/' . $this->project->slug)
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
        $ref = Generators::projectRef();
        $response = $this->actingAs($this->user, self::DRIVER)
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
        $response = $this->json('GET', 'api/projects/' . $this->project->name)
            ->assertStatus(200)
            ->assertExactJson([
                'data' => [
                    [
                        'type' => 'project',
                        'id' => $this->project->ref,
                        'project' => [
                            'name' => $this->project->name,
                            'slug' => $this->project->slug,
                            'access' => $this->project->access,
                            'ref' => $this->project->ref
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
        $numOfProjects = 20;
        $needle = 'EC5 Unit';
        //create fake projects (use 'EC5 Unit' to avoid uniqueness issues)
        for ($i = 0; $i < $numOfProjects; $i++) {
            $project = factory(Project::class)->create([
                'name' => 'EC5 Unit Tests ' . $i,
                'slug' => 'ec5-unit-tests' . $i,
                'access' => 'public',
                'created_by' => $this->user->id
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
        $numOfProjects = 20;
        $needle = 'EC5 Unit';
        //create fake projects (use 'EC5 Unit' to avoid uniqueness issues)
        for ($i = 0; $i < $numOfProjects; $i++) {
            $project = factory(Project::class)->create([
                'name' => 'EC5 Unit Tests ' . $i,
                'slug' => 'ec5-unit-tests' . $i,
                'access' => 'public',
                'status' => 'archived',
                'created_by' => $this->user->id
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
        $numOfProjects = 20;
        $needle = 'EC5 Unit';
        //create fake projects (use 'EC5 Unit' to avoid uniqueness issues)
        for ($i = 0; $i < $numOfProjects; $i++) {
            $project = factory(Project::class)->create([
                'name' => 'EC5 Unit Tests ' . $i,
                'slug' => 'ec5-unit-tests' . $i,
                'access' => 'public',
                'status' => 'trashed',
                'created_by' => $this->user->id
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
        $response = $this->json('GET', 'api/project-version/' . $this->project->slug)
            ->assertStatus(200)
            ->assertExactJson([
                'data' => [
                    'type' => 'project-version',
                    'id' => $this->project->slug,
                    'attributes' => [
                        'structure_last_updated' => $this->projectStructure->updated_at->toDateTimeString(),
                        'version' => (string)$this->projectStructure->updated_at->timestamp
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

    public function test_should_return_public_project_definition_as_json()
    {
        $this->project->access = config('epicollect.strings.project_access.public');
        $this->project->save();

        $response = [];
        try {
            $response[] = $this->json('GET', 'api/internal/project/' . $this->project->slug);
            $response[0]->assertStatus(200)
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
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
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
        $desiredStatus = $canBulkUploadStatuses[array_rand($canBulkUploadStatuses)];
        $response = $this->actingAs($this->user)
            ->json(
                'POST',
                'api/internal/can-bulk-upload/' . $this->project->slug,
                ['can_bulk_upload' => $desiredStatus]
            )->assertStatus(200)
            ->assertExactJson([
                'data' => [
                    'message' => 'Bulk upload settings updated.'
                ]
            ]);

        $storedProject = Project::where('id', $this->project->id)->first();
        $responseData = ($response->json())['data'];
        $this->assertKeysNotEmpty($responseData);
        $this->assertEquals($storedProject->can_bulk_upload, $desiredStatus);
    }
}
