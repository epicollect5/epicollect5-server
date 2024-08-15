<?php

namespace Tests\Http\Controllers\Api\Entries\View\External\ExportRoutes;

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
use Illuminate\Support\Facades\Storage;
use Image;
use Laravel\Passport\ClientRepository;
use Tests\Generators\EntryGenerator;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class MediaExportPrivatePhotoTest extends TestCase
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
    public function test_photos_export_endpoint_private($params)
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

        //generate entries
        $formRef = array_get($projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($projectDefinition, 'data.project.forms.0.inputs');


        $photoRefs = array_values(array_map(function ($input) {
            return $input['ref'];
        }, array_filter($inputs, function ($input) {
            return $input['type'] === config('epicollect.strings.inputs_type.photo');
        })));

        $entryPayloads = [];
        $photoAnswers = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $entryGenerator->createParentEntryPayload($formRef);

            $photoAnswers[] = $entryPayloads[0]['data']['entry']['answers'][$photoRefs[0]];

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

        //add the fake photo
        $filename = $photoAnswers[0]['answer'];

        //create a fake photo for the entry
        $landscapeWidth = config('epicollect.media.entry_original_landscape')[0];
        $landscapeHeight = config('epicollect.media.entry_original_landscape')[1];
        $image = Image::canvas($landscapeWidth, $landscapeHeight, '#ffffff'); // Width, height, and background color

        // Encode the image as JPEG or other formats
        $imageData = (string)$image->encode('jpg');
        Storage::disk('entry_original')->put($project->ref . '/' . $filename, $imageData);
        $imagePath = Storage::disk('entry_original')->getAdapter()->getPathPrefix() . $project->ref . '/' . $filename;

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

        $queryString = '?type=photo&name=' . $filename . '&format=entry_original';

        try {
            $response = $entriesClient->request('GET', $entriesURL . $project->slug . $queryString);

            // Get the response headers
            $headers = $response->getHeaders();
            // Assert that the Content-Type header exists and has the expected value
            $this->assertArrayHasKey('Content-Type', $headers);
            $this->assertEquals(config('epicollect.media.content_type.photo'), $headers['Content-Type'][0]);

            // Assert that the content type is as expected
            $this->assertStringContainsString('image', $response->getHeaderLine('Content-Type'));
            // Assert that the content length is greater than 0
            $this->assertGreaterThan(0, $response->getBody()->getSize());

            // Get the image content from the response
            $imageContent = (string)$response->getBody();
            // Create an Intervention Image instance from the image content
            $entryOriginal = Image::make($imageContent);
            $this->assertEquals($entryOriginal->width(), config('epicollect.media.entry_original_landscape')[0]);
            $this->assertEquals($entryOriginal->height(), config('epicollect.media.entry_original_landscape')[1]);
            // Get the size of the image content in bytes
            $fileSize = strlen($imageContent);
            $this->assertEquals($fileSize, filesize($imagePath));

            Storage::disk('entry_original')->deleteDirectory($project->ref);

            $this->clearDatabase($params);
        } catch (GuzzleException $e) {
            $this->clearDatabase($params);
            $this->logTestError($e, []);
            return false;
        }
    }
}