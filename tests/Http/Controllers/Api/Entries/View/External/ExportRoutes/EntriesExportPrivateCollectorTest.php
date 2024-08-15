<?php

namespace Tests\Http\Controllers\Api\Entries\View\External\ExportRoutes;

use Auth;
use ec5\Models\Entries\Entry;
use ec5\Models\OAuth\OAuthClientProject;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Services\Mapping\DataMappingService;
use ec5\Services\Mapping\ProjectMappingService;
use ec5\Services\Project\ProjectExtraService;
use ec5\Traits\Assertions;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Laravel\Passport\ClientRepository;
use Tests\Generators\EntryGenerator;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class EntriesExportPrivateCollectorTest extends TestCase
{
    use Assertions;

    /**
     * @throws Exception
     */
    public function test_getting_OAuth2_token()
    {
        $name = config('testing.WEB_UPLOAD_CONTROLLER_PROJECT.name');
        $slug = config('testing.WEB_UPLOAD_CONTROLLER_PROJECT.slug');
        $email = config('testing.UNIT_TEST_RANDOM_EMAIL');

        $this->clearDatabase(
            [
                'user' => User::where('email', $email)->first(),
                'project' => Project::where('slug', $slug)->first()
            ]
        );

        //create fake user for testing
        $user = factory(User::class)->create([
                'email' => config('testing.UNIT_TEST_RANDOM_EMAIL')
            ]
        );
        $role = config('epicollect.strings.project_roles.creator');

        //create a project with custom project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);
        $projectDefinition['data']['project']['name'] = $name;
        $projectDefinition['data']['project']['slug'] = $slug;
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
            'role' => $role
        ]);

        //create project structures
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($projectDefinition['data']);
        $projectMappingService = new ProjectMappingService();
        $projectMapping = [$projectMappingService->createEC5AUTOMapping($projectExtra)];

        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data']),
                'project_extra' => json_encode($projectExtra),
                'project_mapping' => json_encode($projectMapping)
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
            $user->id, 'Test App', ''
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

            $obj = json_decode($tokenResponse->getBody());

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
                'role' => $role,
                'projectDefinition' => $projectDefinition,
                'client_id' => $client->id,
                'entryGenerator' => new EntryGenerator($projectDefinition)
            ];
        } catch (GuzzleException $e) {
            $this->clearDatabase(
                [
                    'token' => null,
                    'user' => $user,
                    'project' => $project,
                    'client_id' => $client->id
                ]
            );
            $this->logTestError($e, []);
            return false;
        }
    }

    /**
     * @depends test_getting_OAuth2_token
     */
    public function test_entries_export_endpoint_private($params)
    {
        $token = $params['token'];
        $user = $params['user'];
        $project = $params['project'];
        $role = $params['role'];
        $projectDefinition = $params['projectDefinition'];
        /**
         * @var $entryGenerator EntryGenerator
         */
        $entryGenerator = $params['entryGenerator'];
        $dataMappingService = new DataMappingService();


        //add a collector user
        $collector = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $collector->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.collector')
        ]);

        //generate entries by that collector
        $formRef = array_get($projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::login($collector);
            $entryPayloads[$i] = $entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $entryGenerator->createParentEntryRow(
                $collector,
                $project,
                $role,
                $projectDefinition,
                $entryPayloads[$i]
            );
            Auth::logout();

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $projectStructure = ProjectStructure::where('project_id', $project->id)->first();

        if ($token === null) {
            $this->clearDatabase($params);
            $this->fail('token not received');
        }

        $entriesURL = config('testing.LOCAL_SERVER') . '/api/export/entries/';
        $entriesClient = new Client([
            'headers' => [
                //imp: without this, does not work
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer ' . $token //this will last for 2 hours!
            ]
        ]);

        //Get the project
        try {
            $response = $entriesClient->request('GET', $entriesURL . $project->slug);
            $json = $response->getBody();
            $jsonResponse = json_decode($json, true);

            $mapping = json_decode($projectStructure->project_mapping, true);
            $forms = array_get($projectDefinition, 'data.project.forms');

            $inputsFlattened = $dataMappingService->getInputsFlattened($forms, $formRef);
            // dd($inputsFlattened);
            $onlyMapTheseRefs = array_map(function ($input) {
                return $input['ref'];
            }, $inputsFlattened);

            $this->assertEntriesExportResponse($jsonResponse, $mapping, [
                'form_ref' => $formRef,
                'form_index' => 0,
                'onlyMapTheseRefs' => $onlyMapTheseRefs,
                'projectDefinition' => $projectDefinition
            ]);

            //assert the single entry response
            $entryFromResponse = $jsonResponse['data']['entries'][0];
            //dd($entryFromResponse);
            $this->assertEquals($entryFromDB->uuid, $entryFromResponse['ec5_uuid']);
            $this->assertEquals($entryFromDB->title, $entryFromResponse['title']);
            $this->assertEquals($collector->email, $entryFromResponse['created_by']);
            //timestamp
            $this->assertEquals(
                str_replace(' ', 'T', $entryFromDB->created_at) . '.000Z',
                $entryFromResponse['created_at']);
            $this->assertEquals(
                str_replace(' ', 'T', $entryFromDB->uploaded_at) . '.000Z',
                $entryFromResponse['uploaded_at']);

            $this->clearDatabase($params);

        } catch (GuzzleException $e) {
            $this->clearDatabase($params);
            $this->logTestError($e, []);
            return false;
        }
    }
}