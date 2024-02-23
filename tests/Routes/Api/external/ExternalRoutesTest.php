<?php

namespace Tests\Routes\Api\external;


use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class ExternalRoutesTest extends TestCase
{
    use DatabaseTransactions, Assertions;

    private $project;
    private $user;

    /**
     * Test an authenticated user's routes
     * imp: avoid $this->actingAs($user, 'api_external');
     * imp: as that create a valid user object therefore bypassing
     * imp: jwt validation. We need to send a valid token per each request
     * imp: instead.
     */


    public function setup()
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

    public function test_can_download_private_project_with_jwt()
    {
        //imp: do not use the below on api_external guard
        //$this->actingAs($user, 'api_external');

        //Login user using external guard (JWT)
        Auth::guard('api_external')->login($this->user, false);
        $jwt = Auth::guard('api_external')->authorizationResponse()['jwt'];

        //token valid and user is a member? get in
        $response = $this->json('GET', 'api/project/' . $this->project->slug, [], [
            'Authorization' => 'Bearer ' . $jwt
        ]);
        $response->assertStatus(200);
    }

    public function test_private_external_routes_without_jwt()
    {
        //try to access without authenticating
        $response = $this->json('GET', 'api/project/' . $this->project->slug, [])
            ->assertStatus(404)
            ->assertExactJson(['errors' => [
                [
                    "code" => "ec5_77",
                    "title" => "This project is private. Please log in.",
                    "source" => "middleware"
                ]
            ]]);
    }

    /**
     * Test public user routes
     */
    public function testPublicExternalRoutes()
    {
        $this->project->access = config('epicollect.strings.project_access.public');
        $this->project->save();

        //try to access without authenticating
        $response = $this->json('GET', 'api/project/' . $this->project->slug, [])
            ->assertStatus(200);

        $jsonResponse = json_decode($response->getContent(), true);
        $this->assertProjectResponse($jsonResponse);
    }
}
