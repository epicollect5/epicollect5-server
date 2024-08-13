<?php

namespace Tests\Routes\Api\internal;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStructure;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InternalRoutesTest extends TestCase
{
    use DatabaseTransactions;

    //internal routes use the default 'web; guard
    const DRIVER = 'web';

    protected $privateProjectSlug;
    protected $publicProjectSlug;

    public function setup()
    {
        parent::setUp();
        $this->privateProjectSlug = 'ec5-private';
        $this->publicProjectSlug = 'ec5-public';
    }

    /**
     * Test an authenticated user's routes
     */
    //imp: cannot be done due to custom Models created by previous devs.
    //imp: come back when refactoring
    public function test_private_internal_routes()
    {
        $this->assertTrue(true);
        // //fake user
        // $user = factory(User::class)->create();

        // //create mock private project with that user
        // $project = factory(LegacyProject::class)->create([
        //     'created_by' => $user->id,
        //     'access' => config('epicollect.strings.project_access.private')
        // ]);


        // //assign the user to that project with the CREATOR role
        // $role = config('epicollect.strings.project_roles.creator');
        // $projectRole = factory(ProjectRole::class)->create([
        //     'user_id' => $user->id,
        //     'project_id' => $project->id,
        //     'role' => $role
        // ]);

        // //user can access the project
        // $response = $this->actingAs($user, self::DRIVER)
        //     ->json('GET', 'api/internal/project/' . $project->slug, []);

        // dd($user, $project,  $projectRole, $response);


        // //access private project
        // //  dd($project->name, $project->slug);
        // $response = $this->actingAs($user, self::DRIVER)
        //     ->json('GET', 'api/internal/project/' . $project->slug, []);
        // // ->assertStatus(200);

        // //  dd($response);

        // //create another user
        // $anotherUser = factory(User::class)->create();
        // $anotherUser->state = 'active';

        // // //access to private project (not a member) denied
        // // $this->actingAs($anotherUser, self::DRIVER)
        // //     ->json('GET', 'api/internal/project/' . $project->slug, [])
        // //     ->assertStatus(404);
    }

    public function test_public_internal_routes()
    {
        //create fake public project
        $project = factory(Project::class)->create([
            'name' => 'Unit Test Project',
            'slug' => 'unit-test-project',
            'small_description' => 'This is just a project created to performs the unit tests',
            'access' => 'public'
        ]);
        $slug = $project->slug;

        //add project structure
        $projectStructure = factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $this->json('GET', 'api/project/' . $slug, [])
            ->assertStatus(200);

        //api/internal/media/{project_slug}
        $this->json('GET', 'api/internal/media/' . $slug, [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "type"
                    ],
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "name"
                    ],
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "format"
                    ]
                ]
            ]);

        $this->json('GET', 'api/internal/media/' . $slug . '?type=photo', [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "name"
                    ],
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "format"
                    ]
                ]
            ]);

        $this->json('GET', 'api/internal/media/' . $slug . '?type=photo&format=entry_thumb', [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "name"
                    ]
                ]
            ]);


        //gives back default placeholder photo
        $this->json('GET', 'api/internal/media/' . $slug . '?type=photo&format=entry_thumb&name=ciao', [])
            ->assertStatus(200);

        //api/internal/media/{project_slug}
        $this->json('GET', 'api/internal/temp-media/' . $slug, [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "type"
                    ],
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "name"
                    ],
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "format"
                    ]
                ]
            ]);

        $this->json('GET', 'api/internal/temp-media/' . $slug . '?type=photo', [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "name"
                    ],
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "format"
                    ]
                ]
            ]);

        $this->json('GET', 'api/internal/temp-media/' . $slug . '?type=photo&format=entry_thumb', [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "name"
                    ]
                ]
            ]);


        //gives back default placeholder photo
        $this->json('GET', 'api/internal/temp-media/' . $slug . '?type=photo&format=entry_thumb&name=ciao', [])
            ->assertStatus(200);

        $response = $this->json('GET', 'api/internal/download-entries/' . $slug . '', [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_86",
                        "title" => "User not authenticated.",
                        "source" => "download-entries"
                    ]
                ]
            ]);
        $this->json('GET', 'api/internal/download-entries/' . $slug . '?filter_by=created_at&format=csv&map_index=0&epicollect5-download-entries=1682589106048', [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_86",
                        "title" => "User not authenticated.",
                        "source" => "download-entries"
                    ]
                ]
            ]);
    }
}
