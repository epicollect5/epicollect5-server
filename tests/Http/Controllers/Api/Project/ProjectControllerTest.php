<?php

namespace Tests\Http\Controllers\Api\Project;

use ec5\Libraries\Utilities\Generators;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Users\User;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;


class ProjectControllerTest extends TestCase
{
    use DatabaseTransactions;

    const DRIVER = 'web';

    public function setup()
    {
        parent::setUp();
    }

    public function test_project_exists()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => Config::get('testing.UNIT_TEST_RANDOM_EMAIL')]
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
                "data" => [
                    "type" => "exists",
                    "id" => $project->slug,
                    "exists" => true
                ]
            ])
            ->assertJsonStructure([
                "data" => [
                    "type",
                    "id",
                    "exists",
                ]
            ]);
        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        $this->assertKeysNotEmpty($responseData);
    }

    public function test_project_exists_but_not_logged_in()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => Config::get('testing.UNIT_TEST_RANDOM_EMAIL')]
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
                "errors" => [
                    [
                        "code" => "ec5_219",
                        "title" => "Page not found.",
                        "source" => "auth"
                    ]
                ]
            ])
            ->assertJsonStructure([
                "errors" => [
                    [
                        "code",
                        "title",
                        "source"
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
            ['email' => Config::get('testing.UNIT_TEST_RANDOM_EMAIL')]
        );

        //do not create a project
        $ref = Generators::projectRef();


        $response = $this->actingAs($user, self::DRIVER)
            ->json('GET', 'api/internal/exists/' . $ref)
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "type" => "exists",
                    "id" => $ref,
                    "exists" => false
                ]
            ])
            ->assertJsonStructure([
                "data" => [
                    "type",
                    "id",
                    "exists",
                ]
            ]);
        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        $this->assertKeysNotEmpty($responseData);
    }

    public function test_search_should_find_single_project()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => Config::get('testing.UNIT_TEST_RANDOM_EMAIL')]
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
                "data" => [
                    [
                        "type" => "project",
                        "id" => $ref,
                        "project" => [
                            "name" => $ref,
                            "slug" => $ref,
                            "access" => "public",
                            "ref" => $ref
                        ]
                    ]
                ]
            ])
            ->assertJsonStructure(["data" => [
                [
                    "type",
                    "id",
                    "project" => [
                        "name",
                        "slug",
                        "access",
                        "ref"
                    ]
                ]
            ]]);
    }

    public function test_search_should_find_more_projects()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => Config::get('testing.UNIT_TEST_RANDOM_EMAIL')]
        );

        $numOfProjects = 20;
        $needle = 'EC5 Unit ';
        //create fake projects (use "EC5 Unit" to avoid uniqueness issues)
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
            ->assertJsonStructure(["data" => [
                [
                    "type",
                    "id",
                    "project" => [
                        "name",
                        "slug",
                        "access",
                        "ref"
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
            ['email' => Config::get('testing.UNIT_TEST_RANDOM_EMAIL')]
        );

        $numOfProjects = 20;
        $needle = 'EC5 Unit ';
        //create fake projects (use "EC5 Unit" to avoid uniqueness issues)
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
            ->assertJsonStructure(["data" => []]);

        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        $this->assertCount(0, $responseData);
    }

    public function test_search_should_skip_trashed_projects()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => Config::get('testing.UNIT_TEST_RANDOM_EMAIL')]
        );

        $numOfProjects = 20;
        $needle = 'EC5 Unit ';
        //create fake projects (use "EC5 Unit" to avoid uniqueness issues)
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
            ->assertJsonStructure(["data" => []]);

        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        $this->assertCount(0, $responseData);
    }

    public function test_search_should_return_empty_collection_if_no_name_passed()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => Config::get('testing.UNIT_TEST_RANDOM_EMAIL')]
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
                "data" => []
            ]);

        $responseData = ($response->json())['data']; // Convert the JSON data response to an array.
        $this->assertCount(0, $responseData);
    }

    public function test_should_get_project_version()
    {
        //create fake user
        $user = factory(User::class)->create(
            ['email' => Config::get('testing.UNIT_TEST_RANDOM_EMAIL')]
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
                "data" => [
                    "type" => "project-version",
                    "id" => $project->slug,
                    "attributes" => [
                        "structure_last_updated" => $projectStructure->updated_at->toDateTimeString(),
                        "version" => (string)$projectStructure->updated_at->timestamp
                    ]
                ]
            ])
            ->assertJsonStructure(["data" => [
                "type",
                "id",
                "attributes" => [
                    "structure_last_updated",
                    "version",
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
                "errors" => [
                    [
                        "code" => "ec5_11",
                        "title" => "Project does not exist.",
                        "source" => "version"
                    ]
                ]
            ])
            ->assertJsonStructure(["errors" => [
                [
                    "code",
                    "title",
                    "source"
                ]
            ]]);

        $responseError = ($response->json())['errors']; // Convert the JSON data response to an array.
        $this->assertKeysNotEmpty($responseError);
    }

    public function assertKeysNotEmpty(array $data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->assertKeysNotEmpty($value);
            } else {
                if ($value === null || $value === '') {
                    $this->fail("Key [$key] has an empty value.");
                }
            }
        }
    }
}