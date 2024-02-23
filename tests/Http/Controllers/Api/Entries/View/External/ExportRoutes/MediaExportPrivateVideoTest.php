<?php

namespace Http\Controllers\Api\Entries\View\External\ExportRoutes;

use ec5\Models\Entries\Entry;
use ec5\Models\OAuth\OAuthClientProject;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Services\Mapping\ProjectMappingService;
use ec5\Services\Project\ProjectExtraService;
use ec5\Traits\Assertions;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\Generators\EntryGenerator;
use Tests\Generators\MediaGenerator;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class MediaExportPrivateVideoTest extends TestCase
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
    public function test_videos_export_endpoint_private($params)
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

        //generate entries
        $formRef = array_get($projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($projectDefinition, 'data.project.forms.0.inputs');

        $videoRefs = array_values(array_map(function ($input) {
            return $input['ref'];
        }, array_filter($inputs, function ($input) {
            return $input['type'] === config('epicollect.strings.inputs_type.video');
        })));

        $entryPayloads = [];
        $videoAnswers = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $entryGenerator->createParentEntryPayload($formRef);

            $videoAnswers[] = $entryPayloads[0]['data']['entry']['answers'][$videoRefs[0]];

            $entryRowBundle = $entryGenerator->createParentEntryRow(
                $user,
                $project,
                $role,
                $projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //add the fake audio
        $filename = $videoAnswers[0]['answer'];

        //create a fake audio for the entry
        $videoContent = MediaGenerator::getFakeVideoContentBase64();
        Storage::disk('video')->put($project->ref . '/' . $filename, $videoContent);

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        if ($token === null) {
            $this->clearDatabase($params);
            $this->fail('token not received');
        }

        $entriesURL = config('testing.LOCAL_SERVER') . '/api/export/media/';
        $entriesClient = new Client([
            'headers' => [
                //imp: without this, does not work
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer ' . $token //this will last for 2 hours!
            ]
        ]);

        $queryString = '?type=video&name=' . $filename . '&format=video';

        try {
            $response = $entriesClient->request('GET', $entriesURL . $project->slug . $queryString);

            // Get the response headers
            $headers = $response->getHeaders();
            // Assert that the Content-Type header exists and has the expected value
            $this->assertArrayHasKey('Content-Type', $headers);
            $this->assertEquals(config('epicollect.media.content_type.video'), $headers['Content-Type'][0]);

            // Assert that the content length is greater than 0
            $this->assertGreaterThan(0, $response->getBody()->getSize());

            Storage::disk('video')->deleteDirectory($project->ref);
            $this->clearDatabase($params);
        } catch (GuzzleException $e) {
            $this->clearDatabase($params);
            $this->logTestError($e, []);
            return false;
        }
    }
}