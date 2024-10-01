<?php

namespace Tests\Http\Controllers;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use DatabaseTransactions;

    public const string DRIVER = 'web';
    private Generator $faker;
    private User $user;
    private Project $project;

    public function setUp(): void
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
        $this->project = $project;
    }

    /**
     * Test handling of OPTIONS request in maintenance mode.
     */
    public function test_options_request_during_maintenance()
    {
        // Enable maintenance mode
        $this->artisan('down');

        // Send an OPTIONS request to the formbuilder route
        $endpoint  = 'api/internal/formbuilder/'.$this->project->slug;
        $response = $this->json('OPTIONS', $endpoint);

        // Assert the response is a 200 and has no content
        $response->assertStatus(200);
        $response->assertJson([]);

        // Disable maintenance mode after the test
        $this->artisan('up');
    }

    /**
     * Test handling of POST request to formbuilder-store in maintenance mode.
     */
    public function test_post_request_to_formbuilder_store_during_maintenance()
    {
        // Enable maintenance mode
        $this->artisan('down');

        // Send a POST request to formbuilder-store
        $endpoint  = 'api/internal/formbuilder/'.$this->project->slug;
        $response = $this->json('POST', $endpoint);

        // Check if it returns the custom error code for maintenance mode
        $response->assertStatus(404);
        $response->assertExactJson(
            [
            "errors" => [
                [
                    "code" => "ec5_404",
                    "title" => config('epicollect.codes.ec5_404'),
                    "source" => "maintenance.mode"
                ],
            ]
        ]
        );

        // Disable maintenance mode after the test
        $this->artisan('up');
    }

    /**
     * Test handling of mobile uploads during maintenance mode.
     *
     */
    public function test_mobile_app_uploads_during_maintenance()
    {
        // Enable maintenance mode
        $this->artisan('down');

        // Send a POST request to api/upload route
        $response = $this->json('POST', '/api/upload/'.$this->project->slug, []);

        // Check if it returns the standard maintenance message
        $response->assertStatus(404);
        $response->assertExactJson(
            [
                "errors" => [
                    [
                        "code" => "ec5_252",
                        "title" => config('epicollect.codes.ec5_252'),
                        "source" => "maintenance.mode"
                    ],
                ]
            ]
        );

        // Disable maintenance mode after the test
        $this->artisan('up');
    }

    /**
     * Test handling of mobile project downloads during maintenance mode.
     *
     */
    public function test_mobile_app_project_downloads_during_maintenance()
    {
        // Enable maintenance mode
        $this->artisan('down');

        // Send a POST request to /api/project/ route
        $response = $this->json('GET', '/api/project/'.$this->project->slug, []);

        // Check if it returns the standard maintenance message
        $response->assertStatus(404);
        $response->assertExactJson(
            [
                "errors" => [
                    [
                        "code" => "ec5_252",
                        "title" => config('epicollect.codes.ec5_252'),
                        "source" => "maintenance.mode"
                    ],
                ]
            ]
        );

        // Disable maintenance mode after the test
        $this->artisan('up');
    }

    /**
     * Test handling of mobile project downloads during maintenance mode.
     */
    public function test_mobile_app_entries_downloads_during_maintenance()
    {
        // Enable maintenance mode
        $this->artisan('down');

        // Send a POST request to /api/entries/ route
        $response = $this->json('GET', '/api/entries/'.$this->project->slug, []);

        // Check if it returns the standard maintenance message
        $response->assertStatus(404);
        $response->assertExactJson(
            [
                "errors" => [
                    [
                        "code" => "ec5_252",
                        "title" => config('epicollect.codes.ec5_252'),
                        "source" => "maintenance.mode"
                    ],
                ]
            ]
        );

        // Disable maintenance mode after the test
        $this->artisan('up');
    }
}
