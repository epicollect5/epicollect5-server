<?php

namespace Tests\Http\Controllers\Api\Project;

use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Carbon\Carbon;
use ec5\Traits\Assertions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Tests\TestCase;
use Throwable;

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

        // Project search logo_base64 is gated by a feature flag (default off).
        // Enable it here so the existing search tests continue to assert the new payload.
        config(['epicollect.setup.api.project_search_mobile_logo_base64_enabled' => true]);
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
        $this->json('GET', 'api/projects/' . $this->project->name)
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
                            'ref' => $this->project->ref,
                            'logo_base64' => null
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
                        'ref',
                        'logo_base64'
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
            factory(Project::class)->create([
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
                        'ref',
                        'logo_base64'
                    ]
                ]
            ]]);

        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        $this->assertCount($numOfProjects, $responseData);

        foreach ($responseData as $item) {
            $this->assertArrayHasKey('logo_base64', $item['project']);
        }
    }

    public function test_search_should_skip_archived_projects()
    {
        $numOfProjects = 20;
        $needle = 'EC5 Unit';
        //create fake projects (use 'EC5 Unit' to avoid uniqueness issues)
        for ($i = 0; $i < $numOfProjects; $i++) {
            factory(Project::class)->create([
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
            factory(Project::class)->create([
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
                        'project_definition_version' => (string)$this->projectStructure->updated_at->timestamp,
                        'version' => (string)$this->projectStructure->updated_at->timestamp
                    ]
                ]
            ])
            ->assertJsonStructure(['data' => [
                'type',
                'id',
                'attributes' => [
                    'structure_last_updated',
                    'project_definition_version',
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
            ->assertStatus(400)
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

    public function test_project_version_should_bail_if_project_is_trashed()
    {
        $this->project->status = config('epicollect.strings.project_status.trashed');
        $this->project->save();
        $response = $this->json('GET', 'api/project-version/' . $this->project->slug)
            ->assertStatus(400)
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

    public function test_project_version_should_bail_if_project_is_archived()
    {
        $this->project->status = config('epicollect.strings.project_status.archived');
        $this->project->save();
        $response = $this->json('GET', 'api/project-version/' . $this->project->slug)
            ->assertStatus(400)
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
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    //test response by getting existing projects randomly
    public function test_should_assert_response_using_existing_projects()
    {
        //create a few public projects
        $projects = [];
        for ($i = 0; $i < 10; $i++) {
            $projects[] = factory(Project::class)->create([
                'access' => config('epicollect.strings.project_access.public')
            ]);
            factory(ProjectStructure::class)->create(['project_id' => $projects[$i]->id]);
            factory(ProjectStats::class)->create(['project_id' => $projects[$i]->id]);
            factory(ProjectRole::class)->create(['project_id' => $projects[$i]->id, 'user_id' => $this->user->id]);
        }


        foreach ($projects as $project) {
            $response = $this->actingAs($this->user)
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

    public function test_project_search_multiple_matches()
    {
        //Add a few projects with similar names
        $numOfProjects = rand(3, 10);
        foreach (range(0, $numOfProjects) as $index) {
            $projectName = $this->project->name . ' - ' . $index;
            factory(Project::class)->create(
                [
                    'created_by' => $this->user->id,
                    'name' => $projectName,
                    'slug' => Str::slug($projectName),
                    'ref' => Generators::projectRef(),
                    'access' => config('epicollect.strings.project_access.private')
                ]
            );
        }

        $response = $this->actingAs($this->user, self::DRIVER)
            ->json('GET', 'api/projects/' . $this->project->name)
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'id',
                        'project' => [
                            'name',
                            'slug',
                            'access',
                            'ref',
                            'logo_base64',
                        ],
                    ],
                ],
            ]);
        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.


        //closest match is always first
        $this->assertEquals($this->project->name, $responseData[0]['project']['name']);
        $this->assertEquals($this->project->slug, $responseData[0]['project']['slug']);
        //we always have more than one project
        $this->assertGreaterThan($numOfProjects, count($responseData));

        foreach ($responseData as $item) {
            $this->assertArrayHasKey('logo_base64', $item['project']);
        }
    }

    public function test_project_search_multiple_no_matches()
    {
        $this->actingAs($this->user, self::DRIVER)
            ->json('GET', 'api/projects/' . Generators::projectRef())
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
            ]);
    }

    public function test_project_search_empty()
    {
        $this->actingAs($this->user, self::DRIVER)
            ->json('GET', 'api/projects')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
            ]);
    }


    public function test_project_search_exact()
    {
        //Add a few projects with similar names
        $numOfProjects = rand(3, 10);
        foreach (range(0, $numOfProjects) as $index) {
            $projectName = $this->project->name . ' - ' . $index;
            factory(Project::class)->create(
                [
                    'created_by' => $this->user->id,
                    'name' => $projectName,
                    'slug' => Str::slug($projectName),
                    'ref' => Generators::projectRef(),
                    'access' => config('epicollect.strings.project_access.private')
                ]
            );
        }


        $response = $this->actingAs($this->user, self::DRIVER)
            ->json('GET', 'api/projects/' . $this->project->name . '?exact=true')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'id',
                        'project' => [
                            'name',
                            'slug',
                            'access',
                            'ref',
                            'logo_base64',
                        ],
                    ],
                ],
            ])
            ->assertExactJson([
                'data' => [
                    [
                    'type' => 'project',
                    'id' => $this->project->ref,
                    'project' => [
                        'name' => $this->project->name,
                        'slug' => $this->project->slug,
                        'access' => $this->project->access,
                        'ref' => $this->project->ref,
                        'logo_base64' => null
                    ],
                    ]
                ]
            ]);
        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        //Only one match
        $this->assertEquals(1, count($responseData));
    }

    public function test_project_search_exact_not_exists()
    {
        //Add a few projects with similar names
        $numOfProjects = rand(3, 10);
        foreach (range(0, $numOfProjects) as $index) {
            $projectName = $this->project->name . ' - ' . $index;
            factory(Project::class)->create(
                [
                    'created_by' => $this->user->id,
                    'name' => $projectName,
                    'slug' => Str::slug($projectName),
                    'ref' => Generators::projectRef(),
                    'access' => config('epicollect.strings.project_access.private')
                ]
            );
        }


        $response = $this->actingAs($this->user, self::DRIVER)
            ->json('GET', 'api/projects/' .  Generators::projectRef() . '?exact=true')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
            ])
            ->assertExactJson([
                'data' => []
            ]);
        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        //No match
        $this->assertEquals(0, count($responseData));
    }

    public function test_project_search_exact_empty()
    {
        //Add a few projects with similar names
        $numOfProjects = rand(3, 10);
        foreach (range(0, $numOfProjects) as $index) {
            $projectName = $this->project->name . ' - ' . $index;
            factory(Project::class)->create(
                [
                    'created_by' => $this->user->id,
                    'name' => $projectName,
                    'slug' => Str::slug($projectName),
                    'ref' => Generators::projectRef(),
                    'access' => config('epicollect.strings.project_access.private')
                ]
            );
        }


        $response = $this->actingAs($this->user, self::DRIVER)
            ->json('GET', 'api/projects/  ?exact=true')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
            ])
            ->assertExactJson([
                'data' => []
            ]);
        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        //No match
        $this->assertEquals(0, count($responseData));
    }

    public function test_search_caches_generated_logo_with_positive_ttl(): void
    {
        $this->project->access = config('epicollect.strings.project_access.public');
        $this->project->logo_url = 'logo.jpg';
        $this->project->save();

        Storage::fake('project');
        $image = Image::create(100, 100)->fill('#ff0000');
        Storage::disk('project')->put($this->project->ref . '/logo.jpg', $image->toJpeg());

        $version = $this->projectStructure->updated_at->timestamp;
        $positiveKey = 'project_mobile_logo_base64:' . $this->project->ref . ':version:' . $version;
        $negativeKey = 'project_mobile_logo_missing:' . $this->project->ref . ':version:' . $version;

        Cache::partialMock();
        Cache::shouldReceive('has')->with($negativeKey)->andReturnFalse();
        Cache::shouldReceive('get')->with($positiveKey)->andReturnNull();
        Cache::shouldReceive('put')->once()->withArgs(function ($key, $value, $ttl) use ($positiveKey) {
            $this->assertSame($positiveKey, $key);
            $this->assertStringStartsWith('data:image/webp;base64,', $value);
            $this->assertInstanceOf(Carbon::class, $ttl);
            $this->assertTrue(
                $ttl->greaterThanOrEqualTo(Carbon::now()->addDays(364))
                && $ttl->lessThanOrEqualTo(Carbon::now()->addDays(366)),
                'Positive logo cache TTL should be approximately 365 days'
            );

            return true;
        });

        $response = $this->json('GET', 'api/projects/' . $this->project->name . '?exact=true')
            ->assertStatus(200);

        $this->assertStringStartsWith(
            'data:image/webp;base64,',
            $response->json('data.0.project.logo_base64')
        );
    }

    public function test_search_negative_caches_missing_logo_with_short_ttl(): void
    {
        $this->project->access = config('epicollect.strings.project_access.public');
        $this->project->logo_url = 'logo.jpg';
        $this->project->save();

        Storage::fake('project');

        $version = $this->projectStructure->updated_at->timestamp;
        $positiveKey = 'project_mobile_logo_base64:' . $this->project->ref . ':version:' . $version;
        $negativeKey = 'project_mobile_logo_missing:' . $this->project->ref . ':version:' . $version;

        Cache::partialMock();
        Cache::shouldReceive('has')->with($negativeKey)->andReturnFalse();
        Cache::shouldReceive('get')->with($positiveKey)->andReturnNull();
        Cache::shouldReceive('put')->once()->withArgs(function ($key, $value, $ttl) use ($negativeKey) {
            $this->assertSame($negativeKey, $key);
            $this->assertTrue($value);
            $this->assertInstanceOf(Carbon::class, $ttl);
            $this->assertTrue(
                $ttl->greaterThanOrEqualTo(Carbon::now()->addMinutes(59))
                && $ttl->lessThanOrEqualTo(Carbon::now()->addMinutes(61)),
                'Negative logo cache TTL should be approximately 60 minutes'
            );

            return true;
        });

        $response = $this->json('GET', 'api/projects/' . $this->project->name . '?exact=true')
            ->assertStatus(200);

        $this->assertNull($response->json('data.0.project.logo_base64'));
    }

    public function test_search_with_logo_base64_flag_off_returns_pre_change_payload(): void
    {
        config(['epicollect.setup.api.project_search_mobile_logo_base64_enabled' => false]);

        $response = $this->json('GET', 'api/projects/' . $this->project->name . '?exact=true')
            ->assertStatus(200);

        $project = $response->json('data.0.project');
        $this->assertSame(
            ['name', 'slug', 'access', 'ref'],
            array_keys($project)
        );
    }

}
