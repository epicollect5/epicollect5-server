<?php

namespace Tests\Http\Controllers\Api\Project;

use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\OAuth\OAuthClientProject;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\ClientRepository;
use PHPUnit\Framework\Attributes\Depends;
use Tests\TestCase;
use Throwable;

class ProjectControllerExportTest extends TestCase
{
    use Assertions;

    private User $user;
    private Project $project;
    private ProjectStructure $projectStructure;
    public const string DRIVER = 'web';

    public function setup(): void
    {
        parent::setUp();
    }

    public function test_should_export_public_project()
    {
        $this->setupProject();

        $this->project->access = config('epicollect.strings.project_access.public');
        $this->project->save();
        $response = [];
        try {
            $response[] = $this->get('api/export/project/' . $this->project->slug);
            $response[0]->assertStatus(200);
            $jsonResponse = json_decode($response[0]->getContent(), true);
            $this->assertProjectExportResponse($jsonResponse);

            //assert project definition
            $json = ProjectStructure::where('project_id', $this->project->id)->value('project_definition');
            $projectDefinition = json_decode($json, true);
            $projectResponse = json_decode($response[0]->getContent(), true)['data'];

            //add any extra key added by the controller
            $projectDefinition['project']['created_at'] = $this->project->created_at;
            $homepage = config('app.url') . '/project/' . $this->project->slug;
            $projectDefinition['project']['homepage'] = $homepage;
            //compare
            $this->assertEquals($projectDefinition, $projectResponse);

            $this->clearDatabase(['user' => $this->user, 'project' => $this->project]);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
            $this->clearDatabase(['user' => $this->user, 'project' => $this->project]);
        }
    }

    public function test_should_get_token_OAuth2()
    {
        /**
         * imp: create a user and a project to pass down
         */
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

        //add the project and client
        $clientRepository = new ClientRepository();
        $client = $clientRepository->create(
            $user->id,
            'Test App',
            ''
        )->makeVisible('secret');

        factory(OAuthClientProject::class)->create([
            'project_id' => $project->id,
            'client_id' => $client->id
        ]);

        $tokenClient = new Client();
        //can expose localhost using ngrok if needed
        $tokenURL = config('testing.LOCAL_SERVER') . '/api/oauth/token';
        //get token first
        try {
            $tokenResponse = $tokenClient->request('POST', $tokenURL, [
                'headers' => ['Content-Type' => 'application/vnd.api+json'],
                'body' => json_encode([
                    'grant_type' => 'client_credentials',
                    'client_id' => $client->id,
                    'client_secret' => $client->secret
                ])
            ]);

            $body = $tokenResponse->getBody();
            $obj = json_decode($body);

            // Perform assertions
            $this->assertObjectHasProperty('token_type', $obj);
            $this->assertObjectHasProperty('expires_in', $obj);
            $this->assertObjectHasProperty('access_token', $obj);

            $this->assertEquals('Bearer', $obj->token_type);
            $this->assertIsInt($obj->expires_in);
            $this->assertIsString($obj->access_token);
            $this->assertGreaterThan(0, $obj->expires_in); // Ensure expires_in is positive
            $this->assertNotEmpty($obj->access_token);

            $token = $obj->access_token;

            //send params to the @depends test
            return [
                'token' => $token,
                'user' => $user,
                'project' => $project,
                'client_id' => $client->id
            ];
        } catch (GuzzleException $e) {
            $this->clearDatabase(
                ['token' => null,
                    'user' => $user,
                    'project' => $project,
                    'client_id' => $client->id
                ]
            );
            $this->logTestError($e, []);
            return false;
        }
    }

    #[Depends('test_should_get_token_OAuth2')] public function test_should_use_token_to_export_private_project($params)
    {
        $token = $params['token'];
        $project = $params['project'];

        if ($token === null) {
            $this->clearDatabase($params);
            $this->fail('token not received');
        }

        $projectURL = config('testing.LOCAL_SERVER') . '/api/export/project/';
        $projectClient = new Client([
            'headers' => [
                //imp: without this, does not work
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer ' . $token //this will last for 2 hours!
            ]
        ]);

        //Get the project
        try {
            $response = $projectClient->request('GET', $projectURL . $project->slug);
            $json = $response->getBody();
            $jsonResponse = json_decode($json, true);
            $this->assertProjectExportResponse($jsonResponse);
            return $params;
        } catch (GuzzleException $e) {
            $this->clearDatabase($params);
            $this->logTestError($e, []);
            return false;
        }
    }

    #[Depends('test_should_use_token_to_export_private_project')] public function test_should_fail_to_export_different_private_project($params)
    {
        $token = $params['token'];
        $user = $params['user'];

        $projectDefinition = ProjectDefinitionGenerator::createProject(1);
        $anotherProject = factory(Project::class)->create(
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
            'project_id' => $anotherProject->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //create basic project definition
        factory(ProjectStructure::class)->create(
            [
                'project_id' => $anotherProject->id,
                'project_definition' => json_encode($projectDefinition['data'])
            ]
        );
        factory(ProjectStats::class)->create(
            [
                'project_id' => $anotherProject->id,
                'total_entries' => 0
            ]
        );
        if ($token === null) {
            $this->clearDatabase($params);
            $this->fail('token not received');
        }

        $projectURL = config('testing.LOCAL_SERVER') . '/api/export/project/';
        $projectClient = new Client([
            'headers' => [
                //imp: without this, does not work
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer ' . $token //this will last for 2 hours!
            ]
        ]);

        try {
            $projectClient->request('GET', $projectURL . $anotherProject->slug);
        } catch (GuzzleException $e) {
            $this->assertEquals(404, $e->getResponse()->getStatusCode());
            $errorResponse = json_decode($e->getResponse()->getBody(), true);

            $this->assertEquals([
                "errors" => [
                    [
                        "code" => "ec5_257",
                        "title" => "Unauthorized client access.",
                        "source" => "middleware"
                    ]
                ]
            ], $errorResponse);
        }
        $this->clearDatabase($params);
        $this->clearDatabase([
            'user' => $user,
            'project' => $anotherProject
        ]);
    }

    public function test_should_catch_export_project_does_not_exist_logged_in()
    {
        $this->setupProject();

        //Login user using external guard (JWT)
        Auth::guard('api_external')->login($this->user, false);
        $jwt = Auth::guard('api_external')->authorizationResponse()['jwt'];

        $ref = Generators::projectRef();
        $response = $this->json('GET', 'api/export/project/' . $ref, [], [
            'Authorization' => 'Bearer ' . $jwt
        ]);

        $response->assertStatus(404)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_11",
                        "title" => "Project does not exist.",
                        "source" => "middleware"
                    ]
                ]
            ]);

        $this->clearDatabase(['user' => $this->user, 'project' => $this->project]);
    }

    public function test_should_catch_export_project_does_not_exist_logged_out()
    {
        $this->setupProject();

        $ref = Generators::projectRef();
        $response = $this->json('GET', 'api/export/project/' . $ref, [], [
        ]);

        $response->assertStatus(404)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_11",
                        "title" => "Project does not exist.",
                        "source" => "middleware"
                    ]
                ]
            ]);

        $this->clearDatabase(['user' => $this->user, 'project' => $this->project]);
    }

    //imp: the one below is a custom setup project method called only when needed
    //imp: since some tests do not need it, but we are not using transactions
    private function setupProject()
    {
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
}
