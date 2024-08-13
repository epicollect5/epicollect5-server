<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Models\OAuth\OAuthAccessToken;
use ec5\Models\OAuth\OAuthClient;
use ec5\Models\OAuth\OAuthClientProject;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use SebastianBergmann\RecursionContext\Exception;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class ProjectAppsControllerTest extends TestCase
{
    use DatabaseTransactions;

    const DRIVER = 'web';
    private $faker;
    private $user;
    private $project;
    private $projectDefinition;

    public function setUp(): void
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

    public function test_apps_page_renders_correctly()
    {
        $this
            ->actingAs($this->user, self::DRIVER)
            ->get('myprojects/' . $this->project->slug . '/apps')
            ->assertStatus(200);
    }

    public function test_apps_page_redirect_if_not_logged_in()
    {
        $this
            ->get('myprojects/' . $this->project->slug . '/apps')
            ->assertStatus(302)
            ->assertRedirect(Route('login'));
    }

    public function test_app_is_created()
    {
        //imp: taken from t.ly/UpDyV, from() method
        $startingUrl = url('myprojects/' . $this->project->slug . '/apps'); // Set the starting URL
        $this->app['session']->setPreviousUrl($startingUrl);

        $payload = [
            '_token' => csrf_token(),
            'application_name' => 'Test App'
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/apps', $payload);
            $response[0]->assertStatus(302);

            $response[0]->assertRedirect('/myprojects/' . $this->project->slug . '/apps')
                ->assertSessionHas('message', 'ec5_259');

            //assert rows are created
            $this->assertCount(1, OAuthClientProject::where('project_id', $this->project->id)->get());

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_app_is_removed()
    {
        $startingUrl = url('myprojects/' . $this->project->slug . '/apps'); // Set the starting URL
        $this->app['session']->setPreviousUrl($startingUrl);

        //create a fake client app
        $app = factory(OAuthClientProject::class)->create([
            'project_id' => $this->project->id
        ]);

        $this->assertCount(1, OAuthClientProject::where('project_id', $this->project->id)->get());

        $payload = [
            'client_id' => $app->client_id
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/app-delete', $payload);
            $response[0]->assertStatus(302);
            $response[0]->assertRedirect('/myprojects/' . $this->project->slug . '/apps')
                ->assertSessionHas('message', 'ec5_399');
            //assert rows are removed
            $this->assertCount(0, OAuthClientProject::where('project_id', $this->project->id)->get());
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_token_response_works()
    {
        $response = [];
        try {
            $clientRepository = new ClientRepository();
            $client = $clientRepository->create(
                $this->user->id, 'Test App', ''
            )->makeVisible('secret');

            factory(OAuthClientProject::class)->create([
                'project_id' => $this->project->id,
                'client_id' => $client->id
            ]);

            $this->assertCount(
                1,
                OAuthClientProject::where('project_id', $this->project->id)
                    ->where('client_id', $client->id)
                    ->get());
            $this->assertCount(
                1,
                OAuthClient::where('user_id', $this->user->id)
                    ->where('id', $client->id)
                    ->get());

            //imp: mimic a client in 5.4 (actingAsClient() is added in newer Laravel versions)
            Passport::actingAs($this->user, [], 'api_external');
            //create a token
            $response[] = $this
                ->withoutMiddleware()//to avoid throttling
                ->post('api/oauth/token',
                    [
                        'grant_type' => 'client_credentials',
                        'client_id' => $client->id,
                        'client_secret' => $client->secret
                    ],
                    [
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ]
                );

            $response[0]->assertStatus(200)->assertJsonStructure([
                'token_type',
                'expires_in',
                'access_token'
            ]);
            //check access token entry is created
            $this->assertCount(1, OAuthAccessToken::where('client_id', $client->id)->get());
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_token_is_revoked()
    {
        $startingUrl = url('myprojects/' . $this->project->slug . '/apps'); // Set the starting URL
        $this->app['session']->setPreviousUrl($startingUrl);

        $response = [];
        try {
            $clientRepository = new ClientRepository();
            $client = $clientRepository->create(
                $this->user->id, 'Test App', ''
            )->makeVisible('secret');

            factory(OAuthClientProject::class)->create([
                'project_id' => $this->project->id,
                'client_id' => $client->id
            ]);

            factory(OAuthAccessToken::class)->create([
                'client_id' => $client->id
            ]);

            //assert all rows are present
            $this->assertCount(
                1,
                OAuthClientProject::where('project_id', $this->project->id)
                    ->where('client_id', $client->id)
                    ->get());
            $this->assertCount(
                1,
                OAuthClient::where('user_id', $this->user->id)
                    ->where('id', $client->id)
                    ->get());


            $this->assertCount(
                1,
                OAuthAccessToken::where('client_id', $client->id)
                    ->get());

            //revoke the token
            $payload = [
                'client_id' => $client->id
            ];
            $response[] = $this->actingAs($this->user)->post('myprojects/' . $this->project->slug . '/app-revoke', $payload);
            $response[0]->assertStatus(302);
            $response[0]->assertRedirect($startingUrl)
                ->assertSessionHas('message', 'ec5_398');
            //check access token is deleted
            $this->assertCount(0, OAuthAccessToken::where('client_id', $client->id)->get());
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }
}